<?php

declare(strict_types=1);

use App\Modules\Businesses\DTOs\LocationCreated;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use App\Modules\Businesses\UseCases\AddLocation\AddLocation;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\OpeningHours;

function addLocationRequest(string $businessUlid): AddLocation
{
    return new AddLocation(
        businessUlid: $businessUlid,
        name: 'Branch',
        address: new Address('1 St', 'Dubai', 'Dubai', 'AE'),
        openingHours: OpeningHours::fromArray(['mon' => ['open' => '09:00', 'close' => '21:00']]),
    );
}

test('AddLocation creates a location for an existing business', function (): void {
    $business = Business::factory()->create();

    $result = Mediator::dispatch(addLocationRequest($business->ulid));

    expect($result)->toBeInstanceOf(LocationCreated::class);

    $location = Location::withoutGlobalScopes()->where('business_id', $business->id)->firstOrFail();
    expect($location->ulid)->toBe($result->locationId);
});

test('AddLocation throws when the business does not exist', function (): void {
    Mediator::dispatch(addLocationRequest('01JUNKULIDDOESNOTEXIST00'));
})->throws(ModelNotFoundException::class);
