<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class SourceProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($backtrace as $trace) {
            if ($this->isApplicationCode($trace)) {
                $record->extra['file'] = $this->getRelativePath($trace['file']);
                $record->extra['line'] = $trace['line'] ?? null;
                $record->extra['class'] = $trace['class'] ?? null;
                $record->extra['function'] = $trace['function'];
                break;
            }
        }

        return $record;
    }

    private function isApplicationCode(array $trace): bool
    {
        if (! isset($trace['file'])) {
            return false;
        }

        $excludedPaths = [
            '/vendor/',
            '/Modules/Logger/',
            'illuminate/log',
            'monolog/monolog',
        ];

        foreach ($excludedPaths as $path) {
            if (str_contains($trace['file'], $path)) {
                return false;
            }
        }

        return true;
    }

    private function getRelativePath(string $file): string
    {
        $basePath = base_path();

        if (str_starts_with($file, $basePath)) {
            return str_replace($basePath.DIRECTORY_SEPARATOR, '', $file);
        }

        return $file;
    }
}
