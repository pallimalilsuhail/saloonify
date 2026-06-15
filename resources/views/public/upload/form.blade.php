@extends('layouts.public', ['title' => __('Upload documents')])

@push('scripts')
    @vite('resources/js/public-upload.js')
@endpush

@php
    $config = [
        'token' => $rawToken,
        'presignUrl' => url('/api/u/'.$rawToken.'/presign'),
        'confirmUrl' => url('/api/u/'.$rawToken.'/confirm'),
        'maxFiles' => $session->maxFiles,
        'maxBytes' => $session->maxBytes,
        'allowedMime' => $session->allowedMime,
        'allowedExtensions' => (array) config('uploads.allowed_extensions', []),
    ];
    $maxBytesMb = round($session->maxBytes / 1024 / 1024);
@endphp

@section('content')
    <div x-data='uploader(@json($config))' class="rounded-2xl border border-zinc-200 bg-white shadow-sm overflow-hidden">

        {{-- Branded header --}}
        <div class="border-b border-zinc-200 bg-gradient-to-br from-zinc-50 to-white px-5 sm:px-8 py-6">
            <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-zinc-500">
                <span class="inline-flex size-1.5 rounded-full bg-green-500"></span>
                {{ __('Secure document upload') }}
            </div>
            <h1 class="mt-1 text-xl sm:text-2xl font-semibold text-zinc-900">
                {{ $session->businessName }}
            </h1>
            <p class="mt-1 text-sm text-zinc-600">
                {{ __('Upload your documents below — they go directly to :business secure storage.', ['business' => $session->businessName]) }}
            </p>
        </div>

        <div class="px-5 sm:px-8 py-6" x-show="!submitDone">
            {{-- Limits banner --}}
            <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500">
                <span class="inline-flex items-center gap-1">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ __('Link expires :when', ['when' => $session->expiresAt->diffForHumans()]) }}
                </span>
                <span class="text-zinc-300">•</span>
                <span>{{ __(':count files max', ['count' => $session->maxFiles]) }}</span>
                <span class="text-zinc-300">•</span>
                <span>{{ __(':size MB each', ['size' => $maxBytesMb]) }}</span>
            </div>

            {{-- Drop zone --}}
            <label
                class="mt-5 block cursor-pointer rounded-xl border-2 border-dashed p-8 sm:p-10 text-center transition active:bg-zinc-50"
                :class="dragOver ? 'border-zinc-900 bg-zinc-50' : 'border-zinc-300 hover:border-zinc-400'"
                x-on:dragover.prevent="dragOver = true"
                x-on:dragleave.prevent="dragOver = false"
                x-on:drop.prevent="onDrop($event)">
                <input
                    type="file"
                    multiple
                    class="sr-only"
                    :accept="acceptAttr"
                    x-on:change="onPick($event)" />

                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-zinc-100">
                    <svg class="size-6 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 7.5m0 0L7.5 12M12 7.5v9"/>
                    </svg>
                </div>
                <div class="mt-3 text-sm font-medium text-zinc-800">{{ __('Tap to choose files') }}</div>
                <div class="mt-1 text-xs text-zinc-500 hidden sm:block">{{ __('Or drop files anywhere in this box') }}</div>
                <div class="mt-2 text-xs text-zinc-400">{{ __('PDF, JPG, PNG, HEIC, DOCX') }}</div>
            </label>

            {{-- Submit error banner --}}
            <template x-if="submitError">
                <div class="mt-4 flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                    <svg class="size-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
                    <span x-text="submitError"></span>
                </div>
            </template>

            {{-- File list --}}
            <template x-if="items.length > 0">
                <div class="mt-5 space-y-2">
                    <template x-for="item in items" :key="item.id">
                        <div class="rounded-lg border border-zinc-200 bg-white p-3">
                            <div class="flex items-center gap-3">
                                {{-- Mime icon --}}
                                <div class="flex size-10 shrink-0 items-center justify-center rounded-md bg-zinc-50">
                                    <template x-if="item.mime.startsWith('image/')">
                                        <svg class="size-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                                    </template>
                                    <template x-if="item.mime === 'application/pdf'">
                                        <svg class="size-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    </template>
                                    <template x-if="!item.mime.startsWith('image/') && item.mime !== 'application/pdf'">
                                        <svg class="size-5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                    </template>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-zinc-900" x-text="item.name"></div>
                                    <div class="text-xs text-zinc-500">
                                        <span x-text="humanSize(item.size)"></span>
                                        <template x-if="item.status === 'uploading'">
                                            <span class="ml-1 text-zinc-400">• <span x-text="`${item.progress}%`"></span></span>
                                        </template>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <template x-if="item.status === 'done'">
                                        <span class="inline-flex items-center gap-1 text-xs font-medium text-green-700">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                                            {{ __('Uploaded') }}
                                        </span>
                                    </template>
                                    <template x-if="item.status === 'error'">
                                        <button type="button"
                                                class="inline-flex h-8 items-center rounded px-2 text-xs font-medium text-zinc-700 hover:bg-zinc-100"
                                                x-on:click="retry(item)">
                                            {{ __('Retry') }}
                                        </button>
                                    </template>
                                    <button type="button"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded text-zinc-400 hover:text-red-600 active:bg-zinc-100"
                                            x-on:click="remove(item)"
                                            x-show="item.status !== 'uploading'"
                                            aria-label="{{ __('Remove file') }}">
                                        <span class="text-lg leading-none">&times;</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Progress bar --}}
                            <template x-if="item.status === 'uploading' || item.status === 'queued'">
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-zinc-100">
                                    <div class="h-full bg-zinc-900 transition-all duration-200" :style="`width: ${item.progress}%`"></div>
                                </div>
                            </template>
                            <template x-if="item.status === 'error' && item.error">
                                <div class="mt-2 text-xs text-red-700" x-text="item.error"></div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            {{-- Footer: counter + submit --}}
            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-t border-zinc-100 pt-5">
                <div class="text-xs text-zinc-500">
                    <template x-if="items.length > 0">
                        <span><span x-text="successCount"></span> / <span x-text="items.length"></span> {{ __('uploaded') }}</span>
                    </template>
                    <template x-if="items.length === 0">
                        <span>{{ __('Add at least one file to continue.') }}</span>
                    </template>
                </div>

                <button type="button"
                        class="inline-flex w-full sm:w-auto items-center justify-center gap-2 rounded-lg bg-zinc-900 px-5 py-3 sm:py-2.5 text-sm font-medium text-white shadow-sm hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-40"
                        x-on:click="submit()"
                        :disabled="!canSubmit"
                        x-show="!submitDone">
                    <span x-show="!submitting">{{ __('Send to :business', ['business' => $session->businessName]) }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                        {{ __('Sending...') }}
                    </span>
                </button>
            </div>
        </div>

        {{-- Success state --}}
        <template x-if="submitDone">
            <div class="border-t border-green-200 bg-green-50 px-5 sm:px-8 py-8 text-center">
                <div class="mx-auto flex size-12 items-center justify-center rounded-full bg-green-100">
                    <svg class="size-6 text-green-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <h2 class="mt-3 text-lg font-semibold text-green-900">{{ __('Documents received') }}</h2>
                <p class="mt-1 text-sm text-green-800">{{ __(':business has your files. You can close this page.', ['business' => $session->businessName]) }}</p>
            </div>
        </template>

        {{-- Trust footer --}}
        <div class="border-t border-zinc-100 bg-zinc-50/60 px-5 sm:px-8 py-3 text-center">
            <p class="text-xs text-zinc-500 inline-flex items-center gap-1.5">
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                {{ __('Encrypted upload • Only :business staff can view your files', ['business' => $session->businessName]) }}
            </p>
        </div>
    </div>
@endsection
