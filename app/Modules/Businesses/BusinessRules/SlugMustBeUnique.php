<?php

declare(strict_types=1);

namespace App\Modules\Businesses\BusinessRules;

use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;

final readonly class SlugMustBeUnique implements BusinessRule
{
    public function __construct(public string $slug) {}

    public function passes(): bool
    {
        return ! Business::query()->where('slug', $this->slug)->exists();
    }

    public function message(): string
    {
        return "A business with the slug '{$this->slug}' already exists.";
    }

    public function code(): string
    {
        return 'business.slug.duplicate';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['slug' => $this->slug];
    }
}
