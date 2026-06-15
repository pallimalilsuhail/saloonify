<?php

declare(strict_types=1);

namespace App\Services\CloudStorage;

final readonly class PresignedUploadResult
{
    /**
     * @param  string  $uploadUrl  The presigned URL where the file should be uploaded
     * @param  string  $filePath  The S3 key/path where the file will be stored
     * @param  string  $expiresAt  ISO 8601 timestamp when the upload URL expires
     * @param  string  $method  HTTP method to use for upload (PUT)
     */
    public function __construct(
        public string $uploadUrl,
        public string $filePath,
        public string $expiresAt,
        public string $method = 'PUT',
    ) {}
}
