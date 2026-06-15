<?php

declare(strict_types=1);

namespace App\Modules\Logger\Processors;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

class RequestProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (app()->runningInConsole()) {
            $record->extra['runtime'] = 'cli';
            $record->extra['command'] = $_SERVER['argv'] ?? [];

            return $record;
        }

        $request = request();

        $record->extra['runtime'] = 'web';
        $record->extra['url'] = $request->fullUrl();
        $record->extra['ip'] = $request->ip();
        $record->extra['http_method'] = $request->method();
        $record->extra['route_name'] = $request->route()?->getName();
        $record->extra['route_action'] = $request->route()?->getActionName();

        if ($userAgent = $request->userAgent()) {
            $record->extra['user_agent'] = $userAgent;
        }

        if ($referer = $request->header('referer')) {
            $record->extra['referrer'] = $referer;
        }

        if ($request->hasSession()) {
            $record->extra['session_id'] = $request->session()->getId();
        }

        return $record;
    }
}
