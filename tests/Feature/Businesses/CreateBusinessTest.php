<?php

declare(strict_types=1);

use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\UseCases\CreateBusiness\CreateBusiness;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Id;

it('creates an active business with an auto-generated slug', function () {
    $id = Mediator::dispatch(new CreateBusiness(name: 'Acme Insurance'));

    expect($id)->toBeInstanceOf(Id::class);

    $business = Business::query()->where('ulid', $id->toString())->firstOrFail();

    expect($business->name)->toBe('Acme Insurance')
        ->and($business->status)->toBe(BusinessStatus::Active)
        ->and($business->slug)->toStartWith('acme-insurance-')
        ->and($business->ulid)->toBe($id->toString());
});

it('honours an explicit slug when provided', function () {
    Mediator::dispatch(new CreateBusiness(name: 'Acme Insurance', slug: 'Custom Slug'));

    expect(Business::where('slug', 'custom-slug')->exists())->toBeTrue();
});

it('rejects a duplicate slug via guard rule', function () {
    Mediator::dispatch(new CreateBusiness(name: 'First', slug: 'shared'));

    Mediator::dispatch(new CreateBusiness(name: 'Second', slug: 'shared'));
})->throws(BusinessRuleException::class);
