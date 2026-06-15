<?php

declare(strict_types=1);

namespace App\Modules\Documents\BusinessRules;

use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;

final readonly class MimeAllowed implements BusinessRule
{
    /**
     * @param  array<int, string>  $allowed
     */
    public function __construct(
        public string $mime,
        public array $allowed,
    ) {}

    public function passes(): bool
    {
        return in_array($this->mime, $this->allowed, true);
    }

    public function message(): string
    {
        return "MIME type {$this->mime} is not allowed for this upload session.";
    }

    public function code(): string
    {
        return 'document.mime.not_allowed';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'mime' => $this->mime,
            'allowed' => $this->allowed,
        ];
    }
}
