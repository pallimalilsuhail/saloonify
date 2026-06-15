/**
 * Direct-to-S3 uploader for the public /u/{token} page.
 *
 * Flow per file:
 *   1. POST /api/u/{token}/presign with {filename, mime, size}
 *      -> { document_id, upload_url, s3_key, expires_at, method }
 *   2. PUT file body to upload_url with Content-Type header,
 *      streaming progress events from XHR.upload
 *   3. On success: keep document_id for the eventual /confirm call (#22)
 *
 * Failures are per-file and retryable. Network errors don't tank the
 * whole batch — each file lives in its own state slot.
 */
import Alpine from 'alpinejs';

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
    Alpine.data('uploader', (config) => ({
        token: config.token,
        presignUrl: config.presignUrl,
        confirmUrl: config.confirmUrl,
        maxFiles: Number(config.maxFiles ?? 20),
        maxBytes: Number(config.maxBytes ?? 25 * 1024 * 1024),
        allowedMime: Array.isArray(config.allowedMime) ? config.allowedMime : [],
        allowedExtensions: Array.isArray(config.allowedExtensions) ? config.allowedExtensions : [],

        /** @type {Array<{id:string, file:File, name:string, size:number, mime:string, status:string, progress:number, error:string|null, documentId:string|null}>} */
        items: [],
        dragOver: false,
        submitting: false,
        submitError: null,
        submitDone: false,

        get acceptAttr() {
            const parts = [...this.allowedMime, ...this.allowedExtensions];
            return parts.length ? parts.join(',') : '*/*';
        },

        get successCount() {
            return this.items.filter((i) => i.status === 'done').length;
        },

        get pendingCount() {
            return this.items.filter((i) => i.status === 'queued' || i.status === 'uploading').length;
        },

        get hasFailures() {
            return this.items.some((i) => i.status === 'error');
        },

        get canSubmit() {
            return this.items.length > 0 && this.successCount === this.items.length && !this.submitting && !this.submitDone;
        },

        isFileAllowed(file) {
            if (!this.allowedMime.length && !this.allowedExtensions.length) return true;
            if (file.type && this.allowedMime.includes(file.type)) return true;
            if (this.allowedExtensions.length) {
                const lower = (file.name ?? '').toLowerCase();
                if (this.allowedExtensions.some((ext) => lower.endsWith(ext.toLowerCase()))) return true;
            }
            // Some mobile browsers report empty mime for HEIC + odd Office files.
            // Let it through; the server-side MimeAllowed check is authoritative.
            if (!file.type) return true;
            return false;
        },

        humanSize(bytes) {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
            return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
        },

        onDrop(event) {
            event.preventDefault();
            this.dragOver = false;
            this.addFiles(event.dataTransfer?.files ?? []);
        },

        onPick(event) {
            this.addFiles(event.target.files ?? []);
            event.target.value = '';
        },

        addFiles(fileList) {
            const files = Array.from(fileList);

            for (const file of files) {
                if (this.items.length >= this.maxFiles) {
                    this.submitError = `You can upload at most ${this.maxFiles} files.`;
                    break;
                }
                if (file.size > this.maxBytes) {
                    this.items.push(this.makeItem(file, 'error', `File exceeds ${this.humanSize(this.maxBytes)} limit.`));
                    continue;
                }
                if (!this.isFileAllowed(file)) {
                    this.items.push(this.makeItem(file, 'error', `Type ${file.type || 'unknown'} not allowed.`));
                    continue;
                }
                this.items.push(this.makeItem(file, 'queued', null));
                this.uploadItem(this.items[this.items.length - 1]);
            }
        },

        makeItem(file, status, error) {
            return {
                id: crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`,
                file,
                name: file.name,
                size: file.size,
                mime: file.type || this.guessMime(file.name),
                status,
                progress: 0,
                error,
                documentId: null,
            };
        },

        guessMime(name) {
            // Mobile browsers (especially WhatsApp / Instagram in-app, some
            // Android Chrome builds) hand HEIC and DOCX files to the page
            // with an empty file.type. Fall back to extension lookup so the
            // presign payload carries an accepted mime instead of
            // application/octet-stream.
            const lower = (name ?? '').toLowerCase();
            const map = {
                '.pdf': 'application/pdf',
                '.jpg': 'image/jpeg',
                '.jpeg': 'image/jpeg',
                '.png': 'image/png',
                '.heic': 'image/heic',
                '.heif': 'image/heif',
                '.docx': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            };
            for (const [ext, mime] of Object.entries(map)) {
                if (lower.endsWith(ext)) return mime;
            }
            return 'application/octet-stream';
        },

        async uploadItem(item) {
            item.status = 'uploading';
            item.progress = 0;
            item.error = null;

            try {
                const presign = await this.presign(item);
                item.documentId = presign.document_id;
                await this.putToS3(item, presign.upload_url, presign.method ?? 'PUT');
                item.status = 'done';
                item.progress = 100;
            } catch (err) {
                item.status = 'error';
                item.error = err?.message ?? 'Upload failed.';
            }
        },

        retry(item) {
            const idx = this.items.findIndex((i) => i.id === item.id);
            if (idx !== -1) {
                this.uploadItem(this.items[idx]);
            }
        },

        remove(item) {
            this.items = this.items.filter((i) => i.id !== item.id);
        },

        async presign(item) {
            const response = await fetch(this.presignUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
                body: JSON.stringify({
                    filename: item.name,
                    mime: item.mime,
                    size: item.size,
                }),
            });

            if (!response.ok) {
                const body = await response.json().catch(() => ({}));
                throw new Error(body.message ?? `Presign failed (${response.status}).`);
            }

            return response.json();
        },

        putToS3(item, url, method) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open(method, url, true);
                xhr.setRequestHeader('Content-Type', item.mime);

                xhr.upload.addEventListener('progress', (event) => {
                    if (event.lengthComputable) {
                        item.progress = Math.round((event.loaded / event.total) * 100);
                    }
                });
                xhr.addEventListener('load', () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        resolve();
                    } else {
                        reject(new Error(`S3 rejected upload (${xhr.status}).`));
                    }
                });
                xhr.addEventListener('error', () => reject(new Error('Network error during upload.')));
                xhr.addEventListener('abort', () => reject(new Error('Upload aborted.')));
                xhr.send(item.file);
            });
        },

        async submit() {
            if (!this.canSubmit) return;
            this.submitting = true;
            this.submitError = null;

            try {
                const documentIds = this.items.map((i) => i.documentId).filter(Boolean);
                const response = await fetch(this.confirmUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                    },
                    body: JSON.stringify({ document_ids: documentIds }),
                });

                const body = await response.json().catch(() => ({}));

                if (response.status === 200 && body.submitted) {
                    this.submitDone = true;
                    return;
                }

                if (response.status === 207) {
                    // Partial — flag the missing documents so the user can retry them.
                    const missing = new Set(body.missing ?? []);
                    for (const item of this.items) {
                        if (item.documentId && missing.has(item.documentId)) {
                            item.status = 'error';
                            item.error = 'Server could not verify this upload. Please retry.';
                        }
                    }
                    this.submitError = 'Some files were not received. Retry the marked files and submit again.';
                    return;
                }

                throw new Error(body.message ?? `Submit failed (${response.status}).`);
            } catch (err) {
                this.submitError = err?.message ?? 'Submit failed.';
            } finally {
                this.submitting = false;
            }
        },
    }));
});

Alpine.start();
