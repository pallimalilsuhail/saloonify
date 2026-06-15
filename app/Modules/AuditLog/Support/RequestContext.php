<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Support;

use Illuminate\Container\Container;
use Illuminate\Http\Request;

/**
 * Pulls IP + user agent from the active HTTP request when one exists,
 * so audit listeners can record them without each call site passing
 * them explicitly. Returns null fields when running outside HTTP
 * (queue, console, tests without a request).
 */
final readonly class RequestContext
{
    /**
     * @return array{ip: ?string, userAgent: ?string}
     */
    public static function pull(): array
    {
        if (! Container::getInstance()->bound('request')) {
            return ['ip' => null, 'userAgent' => null];
        }

        $request = Container::getInstance()->make('request');

        if (! $request instanceof Request) {
            return ['ip' => null, 'userAgent' => null];
        }

        $userAgent = $request->userAgent();

        return [
            'ip' => $request->ip(),
            'userAgent' => $userAgent !== null ? mb_substr($userAgent, 0, 512) : null,
        ];
    }
}
