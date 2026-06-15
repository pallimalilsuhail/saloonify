<?php

declare(strict_types=1);

namespace App\Services\CloudStorage;

interface CloudStorageService
{
    /**
     * Generate a pre-signed URL for uploading a file directly to cloud storage.
     *
     * @param  string  $folder  folder path in cloud storage (e.g., 'business/{ulid}/customer/...')
     * @param  string  $filename  filename to use
     * @param  string  $mimeType  MIME type of the file
     * @param  int  $maxSizeBytes  maximum file size in bytes (advisory; the bucket policy enforces it)
     * @param  int  $expiryMinutes  how long the URL should be valid (default: 5)
     */
    public function generatePresignedUploadUrl(
        string $folder,
        string $filename,
        string $mimeType,
        int $maxSizeBytes,
        int $expiryMinutes = 5,
    ): PresignedUploadResult;

    /**
     * Verify an object exists in cloud storage. Used by the confirm flow
     * to prove a presigned PUT actually completed before marking the
     * document confirmed server-side.
     */
    public function objectExists(string $key): bool;

    /**
     * Generate a presigned GET URL for in-browser viewing / downloading
     * of a stored object. Caller is responsible for any RBAC + tenancy
     * checks before requesting the URL — this layer only signs.
     *
     * Pass $downloadAs to force a Content-Disposition: attachment with
     * the supplied filename when the browser fetches the URL. Without
     * it the browser will display inline if the MIME allows.
     *
     * @param  int  $expiryMinutes  how long the URL should be valid (default: 60)
     */
    public function generatePresignedDownloadUrl(
        string $key,
        int $expiryMinutes = 60,
        ?string $downloadAs = null,
    ): string;

    /**
     * Tag an existing object so a bucket lifecycle policy can purge it
     * after a grace period. Used for soft-deletion: we mark the object
     * pending-delete here, then the bucket rule removes it after N days.
     *
     * @param  array<string, string>  $tags
     */
    public function tagObject(string $key, array $tags): void;
}
