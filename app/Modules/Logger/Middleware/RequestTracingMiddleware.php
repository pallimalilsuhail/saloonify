<?php

declare(strict_types=1);

namespace App\Modules\Logger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestTracingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->getOrGenerateRequestId($request);

        Context::add('request_id', $requestId);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function getOrGenerateRequestId(Request $request): string
    {
        // Check headers in order of preference
        $requestId = $request->header('X-Request-Id');
        if (! empty($requestId)) {
            return $requestId;
        }

        $requestId = $request->header('X-Correlation-Id');
        if (! empty($requestId)) {
            return $requestId;
        }

        $requestId = $request->header('X-Trace-Id');
        if (! empty($requestId)) {
            return $requestId;
        }

        // Generate UUID if no valid header found
        return Str::uuid()->toString();
    }
}
