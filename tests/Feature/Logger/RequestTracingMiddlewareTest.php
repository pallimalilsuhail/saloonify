<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/__tracing-probe', function () {
        return response()->json([
            'context_request_id' => Context::get('request_id'),
        ]);
    });
});

it('generates a UUID request_id when no header is provided and echoes it back', function () {
    $response = $this->get('/__tracing-probe');

    $headerId = $response->headers->get('X-Request-Id');

    expect($headerId)->toBeString()
        ->and($headerId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i')
        ->and($response->json('context_request_id'))->toBe($headerId);
});

it('reuses an inbound X-Request-Id header verbatim', function () {
    $response = $this->withHeaders(['X-Request-Id' => 'inbound-id-1'])
        ->get('/__tracing-probe');

    expect($response->headers->get('X-Request-Id'))->toBe('inbound-id-1')
        ->and($response->json('context_request_id'))->toBe('inbound-id-1');
});

it('falls back to X-Correlation-Id when X-Request-Id is absent', function () {
    $response = $this->withHeaders(['X-Correlation-Id' => 'corr-1'])
        ->get('/__tracing-probe');

    expect($response->headers->get('X-Request-Id'))->toBe('corr-1')
        ->and($response->json('context_request_id'))->toBe('corr-1');
});

it('falls back to X-Trace-Id when neither X-Request-Id nor X-Correlation-Id is present', function () {
    $response = $this->withHeaders(['X-Trace-Id' => 'trace-1'])
        ->get('/__tracing-probe');

    expect($response->headers->get('X-Request-Id'))->toBe('trace-1')
        ->and($response->json('context_request_id'))->toBe('trace-1');
});
