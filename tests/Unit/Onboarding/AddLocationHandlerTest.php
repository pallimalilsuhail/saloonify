<?php

declare(strict_types=1);

use App\Modules\Businesses\DTOs\LocationCreated;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use App\Modules\Businesses\UseCases\AddLocation\AddLocation;
use App\Modules\Businesses\UseCases\AddLocation\AddLocationHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\OpeningHours;

// Exercises the handler directly (no Mediator, no HTTP) — just handle() against the DB.
uses(RefreshDatabase::class);

function addLocationRequestFor(string $businessUlid): AddLocation
{
    return new AddLocation(
        businessId: Id::fromString($businessUlid),
        name: 'Branch',
        address: new Address('1 St', 'Dubai', 'Dubai', 'AE'),
        openingHours: OpeningHours::fromArray(['mon' => [['open' => '09:00', 'close' => '21:00']]]),
    );
}

test('handle() creates a location for an existing business and returns the DTO', function (): void {
    $business = Business::factory()->create();

    $result = (new AddLocationHandler)->handle(addLocationRequestFor($business->ulid));

    expect($result)->toBeInstanceOf(LocationCreated::class);

    $location = Location::withoutGlobalScopes()->firstOrFail();
    expect($location->ulid)->toBe($result->locationId->toString())
        ->and($location->business_id)->toBe($business->id);
});

test('handle() throws when the business does not exist', function (): void {
    (new AddLocationHandler)->handle(addLocationRequestFor('00000000000000000000000000'));
})->throws(ModelNotFoundException::class);
