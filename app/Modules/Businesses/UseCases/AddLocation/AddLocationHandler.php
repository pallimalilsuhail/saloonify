<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\AddLocation;

use App\Modules\Businesses\DTOs\LocationCreated;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Shared\ValueObjects\Id;

final class AddLocationHandler implements RequestHandler
{
    /**
     * @param  AddLocation  $request
     */
    public function handle(Request $request): LocationCreated
    {
        $business = Business::query()
            ->where('ulid', $request->businessId->toString())
            ->firstOrFail();

        // Generate the id up front so we own it without reading the row back.
        $locationId = Id::generate();

        Location::create([
            'ulid' => $locationId->toString(),
            'business_id' => $business->id,
            'name' => $request->name,
            'address_json' => $request->address->toArray(),
            'opening_hours_json' => $request->openingHours->toArray(),
        ]);

        return new LocationCreated($locationId);
    }
}
