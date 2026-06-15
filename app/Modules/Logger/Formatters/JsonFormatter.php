<?php

declare(strict_types=1);

namespace App\Modules\Logger\Formatters;

use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\LogRecord;

class JsonFormatter extends MonologJsonFormatter
{
    public function format(LogRecord $record): string
    {
        $extra = $record->extra;

        $data = [
            'message' => $record->message,
            'level' => strtolower($record->level->name),
            'timestamp' => $record->datetime->format('c'),
            'environment' => config('app.env'),
        ];

        // Context section
        if (! empty($record->context)) {
            $data['context'] = $record->context;
        }

        // Request section
        $data['request'] = $this->buildRequestSection($extra);

        // User section
        if (isset($extra['ulid'])) {
            $data['user'] = [
                'ulid' => $extra['ulid'],
            ];
        }

        // Performance section
        $data['performance'] = $this->buildPerformanceSection($extra);

        // Runtime section
        $data['runtime'] = $this->buildRuntimeSection($extra);

        // Remove empty sections
        $data = array_filter($data, fn ($value) => ! empty($value));

        return $this->toJson($data).($this->appendNewline ? "\n" : '');
    }

    private function buildRequestSection(array $extra): array
    {
        $request = [];

        if (isset($extra['request_id'])) {
            $request['id'] = $extra['request_id'];
        }

        if (isset($extra['http_method'])) {
            $request['method'] = $extra['http_method'];
        }

        if (isset($extra['url'])) {
            $request['url'] = $extra['url'];
        }

        if (isset($extra['route_name']) || isset($extra['route_action'])) {
            $request['route'] = array_filter([
                'name' => $extra['route_name'] ?? null,
                'action' => $extra['route_action'] ?? null,
            ]);
        }

        if (isset($extra['ip'])) {
            $request['ip'] = $extra['ip'];
        }

        if (isset($extra['user_agent'])) {
            $request['user_agent'] = $extra['user_agent'];
        }

        if (isset($extra['referrer'])) {
            $request['referrer'] = $extra['referrer'];
        }

        if (isset($extra['session_id'])) {
            $request['session_id'] = $extra['session_id'];
        }

        return $request;
    }

    private function buildPerformanceSection(array $extra): array
    {
        $performance = [];

        if (isset($extra['execution_time_ms'])) {
            $performance['execution_time_ms'] = $extra['execution_time_ms'];
        }

        if (isset($extra['cpu_usage'])) {
            $performance['cpu_usage'] = $extra['cpu_usage'];
        }

        if (isset($extra['used_bytes']) || isset($extra['peak_bytes'])) {
            $memory = [];

            if (isset($extra['used_bytes'])) {
                $memory['used_mb'] = round($extra['used_bytes'] / 1024 / 1024, 2);
            }

            if (isset($extra['peak_bytes'])) {
                $memory['peak_mb'] = round($extra['peak_bytes'] / 1024 / 1024, 2);
            }

            $performance['memory'] = $memory;
        }

        return $performance;
    }

    private function buildRuntimeSection(array $extra): array
    {
        $runtime = [];

        if (isset($extra['runtime'])) {
            $runtime['type'] = $extra['runtime'];
        }

        if (isset($extra['file'])) {
            $runtime['file'] = $extra['file'];
        }

        if (isset($extra['line'])) {
            $runtime['line'] = $extra['line'];
        }

        if (isset($extra['class'])) {
            $runtime['class'] = $extra['class'];
        }

        if (isset($extra['function'])) {
            $runtime['function'] = $extra['function'];
        }

        return $runtime;
    }
}
