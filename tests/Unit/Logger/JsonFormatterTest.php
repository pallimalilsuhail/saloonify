<?php

declare(strict_types=1);

use App\Modules\Logger\Formatters\JsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;

it('formats a LogRecord as a single-line JSON envelope', function () {
    $formatter = new JsonFormatter;

    $record = new LogRecord(
        datetime: new DateTimeImmutable('2026-05-09T10:00:00+00:00'),
        channel: 'test',
        level: Level::Info,
        message: 'something happened',
        context: ['foo' => 'bar'],
        extra: [
            'request_id' => 'req-123',
            'http_method' => 'POST',
            'url' => 'http://share.test/login',
            'used_bytes' => 1024 * 1024 * 4,
        ],
    );

    $output = $formatter->format($record);

    expect($output)->toEndWith("\n");

    $decoded = json_decode(trim($output), associative: true);

    expect($decoded)
        ->toMatchArray([
            'message' => 'something happened',
            'level' => 'info',
            'context' => ['foo' => 'bar'],
        ])
        ->and($decoded['request']['id'])->toBe('req-123')
        ->and($decoded['request']['method'])->toBe('POST')
        ->and($decoded['performance']['memory']['used_mb'])->toBe(4.0);
});

it('omits empty top-level sections', function () {
    $formatter = new JsonFormatter;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'test',
        level: Level::Debug,
        message: 'plain',
        context: [],
        extra: [],
    );

    $decoded = json_decode(trim($formatter->format($record)), associative: true);

    expect($decoded)
        ->not->toHaveKey('context')
        ->not->toHaveKey('user')
        ->not->toHaveKey('performance')
        ->not->toHaveKey('runtime');
});
