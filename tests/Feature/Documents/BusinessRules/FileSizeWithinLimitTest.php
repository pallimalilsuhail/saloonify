<?php

declare(strict_types=1);

use App\Modules\Documents\BusinessRules\FileSizeWithinLimit;

it('passes when size is within the limit', function () {
    expect((new FileSizeWithinLimit(1024, 2048))->passes())->toBeTrue();
});

it('passes when size equals the limit (boundary)', function () {
    expect((new FileSizeWithinLimit(2048, 2048))->passes())->toBeTrue();
});

it('fails when size exceeds the limit by 1 byte', function () {
    expect((new FileSizeWithinLimit(2049, 2048))->passes())->toBeFalse();
});

it('fails when size is zero', function () {
    expect((new FileSizeWithinLimit(0, 2048))->passes())->toBeFalse();
});

it('exposes code + context', function () {
    $rule = new FileSizeWithinLimit(5000, 2048);

    expect($rule->code())->toBe('document.size.exceeded')
        ->and($rule->context())->toBe(['size_bytes' => 5000, 'max_bytes' => 2048]);
});
