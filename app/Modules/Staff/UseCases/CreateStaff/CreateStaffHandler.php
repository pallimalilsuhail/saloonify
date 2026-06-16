<?php

declare(strict_types=1);

namespace App\Modules\Staff\UseCases\CreateStaff;

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Staff\DTOs\StaffCreated;
use App\Modules\Staff\Support\SyntheticEmail;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final class CreateStaffHandler implements RequestHandler
{
    /**
     * @param  CreateStaff  $request
     */
    public function handle(Request $request): StaffCreated
    {
        $business = Business::findOrFail($request->businessId);

        $email = $request->email
            ?? SyntheticEmail::make((string) $request->username, $business->slug);

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'username' => $request->username,
            'password' => $request->password,
            'role' => $request->role->value,
            'status' => UserStatus::Active->value,
            'business_id' => $business->id,
        ]);

        // business_admin spans all locations (no membership rows);
        // a location_agent is pinned to its assigned locations.
        if ($request->role === UserRole::LocationAgent) {
            $user->locations()->sync($request->locationIds);
        }

        return new StaffCreated($user->ulid, $email);
    }
}
