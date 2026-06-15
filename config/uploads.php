<?php

declare(strict_types=1);

/**
 * Defaults for upload sessions. Per-business overrides land in #23
 * (revoke + regenerate) — when a business needs different limits, we'll
 * add a businesses_settings table or columns on businesses_businesses.
 */
return [

    'expiry_minutes' => (int) env('UPLOADS_EXPIRY_MINUTES', 60),

    'max_files' => (int) env('UPLOADS_MAX_FILES', 20),

    'max_bytes' => (int) env('UPLOADS_MAX_BYTES', 25 * 1024 * 1024),

    'allowed_mime' => [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/heic',
        'image/heif',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],

    /**
     * Extensions browsers should be hinted to show in the file picker.
     * Mirrors allowed_mime where possible; some pickers (notably iOS
     * Safari) won't show HEIC/HEIF unless the picker is hinted with
     * the extension as well.
     */
    'allowed_extensions' => [
        '.pdf',
        '.jpg',
        '.jpeg',
        '.png',
        '.heic',
        '.heif',
        '.docx',
    ],

];
