<?php

declare(strict_types=1);

namespace App\Services\CloudStorage;

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Illuminate\Support\Str;

final class S3CloudStorageService implements CloudStorageService
{
    private S3Client $client;

    private string $bucket;

    private string $region;

    public function __construct()
    {
        $this->bucket = (string) config('filesystems.disks.s3.bucket');
        $this->region = (string) config('filesystems.disks.s3.region');

        $config = [
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => [
                'key' => (string) config('filesystems.disks.s3.key'),
                'secret' => (string) config('filesystems.disks.s3.secret'),
            ],
        ];

        $endpoint = config('filesystems.disks.s3.endpoint');
        if (! empty($endpoint)) {
            $config['endpoint'] = $endpoint;
            $config['use_path_style_endpoint'] = (bool) config('filesystems.disks.s3.use_path_style_endpoint', false);
        }

        $this->client = new S3Client($config);
    }

    public function generatePresignedUploadUrl(
        string $folder,
        string $filename,
        string $mimeType,
        int $maxSizeBytes,
        int $expiryMinutes = 5,
    ): PresignedUploadResult {
        $s3Key = $this->generateS3Key($folder, $filename);
        $expiresAt = now()->addMinutes($expiryMinutes);

        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $s3Key,
            'ContentType' => $mimeType,
        ]);

        $request = $this->client->createPresignedRequest($cmd, "+{$expiryMinutes} minutes");

        return new PresignedUploadResult(
            uploadUrl: (string) $request->getUri(),
            filePath: $s3Key,
            expiresAt: $expiresAt->toIso8601String(),
            method: 'PUT',
        );
    }

    public function objectExists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);

            return true;
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 404) {
                return false;
            }

            throw $e;
        }
    }

    public function generatePresignedDownloadUrl(string $key, int $expiryMinutes = 60, ?string $downloadAs = null): string
    {
        $args = [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ];

        if ($downloadAs !== null && $downloadAs !== '') {
            // Quote-escape any quotes/backslashes per RFC 6266 quoted-string.
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $downloadAs);
            $args['ResponseContentDisposition'] = 'attachment; filename="'.$escaped.'"';
        }

        $cmd = $this->client->getCommand('GetObject', $args);

        $request = $this->client->createPresignedRequest($cmd, "+{$expiryMinutes} minutes");

        return (string) $request->getUri();
    }

    public function tagObject(string $key, array $tags): void
    {
        $tagSet = array_map(
            fn (string $tagKey, string $tagValue) => ['Key' => $tagKey, 'Value' => $tagValue],
            array_keys($tags),
            array_values($tags),
        );

        $this->client->putObjectTagging([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Tagging' => ['TagSet' => $tagSet],
        ]);
    }

    private function generateS3Key(string $folder, string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $sanitisedName = Str::slug(pathinfo($filename, PATHINFO_FILENAME));
        $uniqueId = Str::random(8);

        $base = sprintf(
            '%s/%s-%s',
            rtrim($folder, '/'),
            $sanitisedName !== '' ? $sanitisedName : 'file',
            $uniqueId,
        );

        return $extension !== '' ? "{$base}.{$extension}" : $base;
    }
}
