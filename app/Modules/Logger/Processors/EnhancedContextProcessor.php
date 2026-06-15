<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Illuminate\Log\Logger;

final class EnhancedContextProcessor
{
    private array $processors = [
        TraceIdProcessor::class,
        UserProcessor::class,
        RequestProcessor::class,
        PerformanceProcessor::class,
        SourceProcessor::class,
    ];

    public function __invoke(Logger $logger): void
    {
        foreach ($this->processors as $processorClass) {
            $logger->pushProcessor(new $processorClass);
        }
    }
}
