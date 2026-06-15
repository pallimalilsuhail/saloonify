<?php

declare(strict_types=1);

use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\SuspendBusiness\SuspendBusiness;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Shared\ValueObjects\Id;

it('flips an active business to suspended', function () {
    $business = Business::create([
        'name' => 'Acme',
        'slug' => 'acme',
        'status' => BusinessStatus::Active->value,
    ]);

    Mediator::dispatch(new SuspendBusiness(businessId: Id::fromString($business->ulid)));

    expect($business->fresh()->status)->toBe(BusinessStatus::Suspended);
});

it('throws when business does not exist', function () {
    Mediator::dispatch(new SuspendBusiness(businessId: Id::generate()));
})->throws(ModelNotFoundException::class);
