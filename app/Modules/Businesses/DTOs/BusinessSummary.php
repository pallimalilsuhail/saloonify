<?php

declare(strict_types=1);

namespace App\Modules\Businesses\DTOs;

use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use Carbon\CarbonImmutable;
use Shared\ValueObjects\Id;

/**
 * Lightweight projection of a Business for the super-admin list view.
 */
final readonly class BusinessSummary
{
    public function __construct(
        public Id $id,
        public string $name,
        public string $slug,
        public BusinessStatus $status,
        public CarbonImmutable $createdAt,
    ) {}

    public static function fromModel(Business $business): self
    {
        return new self(
            id: Id::fromString($business->ulid),
            name: $business->name,
            slug: $business->slug,
            status: $business->status,
            createdAt: $business->created_at?->toImmutable() ?? CarbonImmutable::now(),
        );
    }
}
