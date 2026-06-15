<?php

declare(strict_types=1);

use App\Modules\Documents\BusinessRules\MimeAllowed;

it('passes when mime is in the allow-list', function () {
    expect((new MimeAllowed('application/pdf', ['application/pdf', 'image/png']))->passes())->toBeTrue();
});

it('fails when mime is not in the allow-list', function () {
    expect((new MimeAllowed('application/x-evil', ['application/pdf']))->passes())->toBeFalse();
});

it('exposes a stable code and includes the offending mime in context', function () {
    $rule = new MimeAllowed('image/gif', ['application/pdf']);

    expect($rule->code())->toBe('document.mime.not_allowed')
        ->and($rule->context())->toBe(['mime' => 'image/gif', 'allowed' => ['application/pdf']]);
});
