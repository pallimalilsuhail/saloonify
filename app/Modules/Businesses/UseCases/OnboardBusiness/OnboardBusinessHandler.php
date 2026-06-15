<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\OnboardBusiness;

use App\Models\User;
use App\Modules\Businesses\DTOs\OnboardedBusiness;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Businesses\Models\Business;
use App\Modules\Staff\Support\SyntheticEmail;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final class OnboardBusinessHandler implements RequestHandler
{
    /**
     * Creates the business + its first admin. Locations are added via the
     * dedicated AddLocation endpoint (decoupled). Wrapped in a DB
     * transaction by the WithDatabaseTransaction middleware.
     *
     * @param  OnboardBusiness  $request
     */
    public function handle(Request $request): OnboardedBusiness
    {
        $business = Business::create([
            'name' => $request->name,
            'trn' => $request->trn,
        ]);

        $isEmail = filter_var($request->login, FILTER_VALIDATE_EMAIL) !== false;

        User::create([
            'name' => $request->adminName,
            'email' => $isEmail ? $request->login : SyntheticEmail::make($request->login, $business->slug),
            'username' => $isEmail ? null : $request->login,
            'password' => $request->password,
            'role' => UserRole::BusinessAdmin->value,
            'status' => UserStatus::Active->value,
            'business_id' => $business->id,
        ]);

        return new OnboardedBusiness($business->ulid, $request->login);
    }
}
