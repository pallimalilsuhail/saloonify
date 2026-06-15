<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\BusinessRules\FileCountWithinLimit;
use App\Modules\Documents\Models\Document;

beforeEach(function () {
    $business = Business::factory()->create();
    $customer = Customer::factory()->for($business)->create();
    $this->session = UploadSession::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
    ]);
});

it('passes when no documents exist for the session', function () {
    expect((new FileCountWithinLimit($this->session->id, 5))->passes())->toBeTrue();
});

it('passes when document count is below the limit', function () {
    Document::factory()->count(3)->create([
        'business_id' => $this->session->business_id,
        'customer_id' => $this->session->customer_id,
        'upload_session_id' => $this->session->id,
    ]);

    expect((new FileCountWithinLimit($this->session->id, 5))->passes())->toBeTrue();
});

it('fails when document count equals the limit (next add would exceed)', function () {
    Document::factory()->count(5)->create([
        'business_id' => $this->session->business_id,
        'customer_id' => $this->session->customer_id,
        'upload_session_id' => $this->session->id,
    ]);

    expect((new FileCountWithinLimit($this->session->id, 5))->passes())->toBeFalse();
});

it('counts documents only for the supplied session', function () {
    Document::factory()->count(5)->create([
        'business_id' => $this->session->business_id,
        'customer_id' => $this->session->customer_id,
        'upload_session_id' => $this->session->id,
    ]);

    $other = UploadSession::factory()->create([
        'business_id' => $this->session->business_id,
        'customer_id' => $this->session->customer_id,
    ]);

    expect((new FileCountWithinLimit($other->id, 5))->passes())->toBeTrue();
});

it('exposes code + context', function () {
    $rule = new FileCountWithinLimit($this->session->id, 20);

    expect($rule->code())->toBe('document.count.exceeded')
        ->and($rule->context())->toBe(['upload_session_id' => $this->session->id, 'max_files' => 20]);
});
