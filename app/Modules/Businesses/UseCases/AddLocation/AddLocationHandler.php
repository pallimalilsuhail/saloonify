<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\AddLocation;

use App\Modules\Businesses\DTOs\LocationCreated;
use App\Modules\Businesses\Models\Business;
use App\Modules\Businesses\Models\Location;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class AddLocationHandler implements RequestHandler
{
    /**
     * @param  AddLocation  $request
     */
    public function handle(Request $request): LocationCreated
    {
        $business = Business::query()->where('ulid', $request->businessUlid)->first();

        if ($business === null) {
            throw (new ModelNotFoundException)->setModel(Business::class, [$request->businessUlid]);
        }

        $location = Location::create([
            'business_id' => $business->id,
            'name' => $request->name,
            'address_json' => $request->address->toArray(),
            'opening_hours_json' => $request->openingHours->toArray(),
        ]);

        return new LocationCreated($location->ulid);
    }
}
