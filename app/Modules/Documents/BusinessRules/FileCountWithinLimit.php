<?php

declare(strict_types=1);

namespace App\Modules\Documents\BusinessRules;

use App\Modules\Documents\Models\Document;
use AvoqadoDev\UseCase\BusinessRules\Contracts\BusinessRule;

final readonly class FileCountWithinLimit implements BusinessRule
{
    public function __construct(
        public int $uploadSessionId,
        public int $maxFiles,
    ) {}

    public function passes(): bool
    {
        $current = Document::query()
            ->where('upload_session_id', $this->uploadSessionId)
            ->count();

        return $current < $this->maxFiles;
    }

    public function message(): string
    {
        return "This upload link has reached its limit of {$this->maxFiles} files.";
    }

    public function code(): string
    {
        return 'document.count.exceeded';
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'upload_session_id' => $this->uploadSessionId,
            'max_files' => $this->maxFiles,
        ];
    }
}
