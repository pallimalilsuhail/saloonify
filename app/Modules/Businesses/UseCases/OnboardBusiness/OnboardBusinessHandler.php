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
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;

final class OnboardBusinessHandler implements RequestHandler
{
    /**
     * @param  OnboardBusiness  $request
     */
    public function handle(Request $request): OnboardedBusiness
    {
        $businessId = Id::generate();

        // Capture the row: we still need its auto-increment id (FK) and the boot-generated slug.
        $business = Business::create([
            'ulid' => $businessId->toString(),
            'name' => $request->name,
            'trn' => $request->trn,
        ]);

        $email = Email::tryFrom($request->login);

        User::create([
            'name' => $request->adminName,
            'email' => $email?->toString() ?? SyntheticEmail::make($request->login, $business->slug),
            'username' => $email !== null ? null : $request->login,
            'password' => $request->password,
            'role' => UserRole::BusinessAdmin->value,
            'status' => UserStatus::Active->value,
            'business_id' => $business->id,
        ]);

        return new OnboardedBusiness($businessId, $request->login);
    }
}
