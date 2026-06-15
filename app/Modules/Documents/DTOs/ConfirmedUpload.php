<?php

declare(strict_types=1);

namespace App\Modules\Documents\DTOs;

use Shared\ValueObjects\Id;

/**
 * Result of a confirm call. Returned by the use case + serialised back
 * to the browser. \"submitted\" tells the page whether the session was
 * fully sealed (all documents confirmed) or whether some are still
 * pending (the customer can retry the failed PUTs and re-submit).
 *
 * \"missing\" lists document_ids the server could not verify on S3 — the
 * upload either never reached the bucket or the presigned URL expired
 * before the PUT completed.
 */
final readonly class ConfirmedUpload
{
    /**
     * @param  array<int, Id>  $confirmed
     * @param  array<int, Id>  $missing
     */
    public function __construct(
        public Id $sessionId,
        public bool $submitted,
        public array $confirmed,
        public array $missing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toJson(): array
    {
        return [
            'session_id' => $this->sessionId->toString(),
            'submitted' => $this->submitted,
            'confirmed' => array_map(fn (Id $id) => $id->toString(), $this->confirmed),
            'missing' => array_map(fn (Id $id) => $id->toString(), $this->missing),
        ];
    }
}
