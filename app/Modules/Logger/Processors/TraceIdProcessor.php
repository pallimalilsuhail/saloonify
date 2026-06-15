<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Illuminate\Support\Facades\Context;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class TraceIdProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $contextData = Context::all();
        $traceFields = ['request_id', 'trace_id', 'span_id', 'parent_span_id', 'correlation_id'];

        foreach ($traceFields as $field) {
            if (isset($contextData[$field]) && ! isset($record->extra[$field])) {
                // Only add if not already present in extra
                $record->extra[$field] = $contextData[$field];
            }
        }

        return $record;
    }
}
