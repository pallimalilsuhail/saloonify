<?php

declare(strict_types=1);

namespace App\Modules\Documents\BusinessRules;

use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;

final readonly class FileSizeWithinLimit implements BusinessRule
{
    public function __construct(
        public int $sizeBytes,
        public int $maxBytes,
    ) {}

    public function passes(): bool
    {
        return $this->sizeBytes > 0 && $this->sizeBytes <= $this->maxBytes;
    }

    public function message(): string
    {
        $maxMb = round($this->maxBytes / 1024 / 1024, 1);

        return "File exceeds the {$maxMb} MB per-file limit.";
    }

    public function code(): string
    {
        return 'document.size.exceeded';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'size_bytes' => $this->sizeBytes,
            'max_bytes' => $this->maxBytes,
        ];
    }
}
