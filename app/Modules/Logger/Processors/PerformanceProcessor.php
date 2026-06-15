<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class PerformanceProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $record->extra['used_bytes'] = memory_get_usage(true);
        $record->extra['peak_bytes'] = memory_get_peak_usage(true);

        if (defined('LARAVEL_START')) {
            $record->extra['execution_time_ms'] = round((microtime(true) - LARAVEL_START) * 1000, 2);
        }

        $record->extra['cpu_usage'] = sys_getloadavg()[0] ?? null;

        return $record;
    }
}
