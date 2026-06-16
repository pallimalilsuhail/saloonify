<?php

declare(strict_types=1);

namespace App\Modules\Staff\UseCases\UpdateStaff;

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Enums\UserStatus;
use App\Modules\Staff\DTOs\StaffUpdated;
use App\Modules\Staff\Exceptions\StaffPolicyException;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;

final class UpdateStaffHandler implements RequestHandler
{
    /**
     * @param  UpdateStaff  $request
     */
    public function handle(Request $request): StaffUpdated
    {
        $user = User::findOrFail($request->userId);

        if ($user->status === UserStatus::Terminated) {
            throw StaffPolicyException::terminatedIsTerminal();
        }

        $newRole = $request->role ?? $user->role;
        $newStatus = $request->status ?? $user->status;

        $this->guardLastActiveAdmin($user, $newRole, $newStatus);
        $this->guardLocationAgentHasLocation($user, $request->role, $newRole, $request->locationIds);

        if ($request->name !== null) {
            $user->name = $request->name;
        }
        $user->role = $newRole;
        $user->status = $newStatus;
        $user->save();

        if ($newRole === UserRole::LocationAgent) {
            if ($request->locationIds !== null) {
                $user->locations()->sync($request->locationIds);
            }
        } else {
            $user->locations()->sync([]);
        }

        return new StaffUpdated($user->ulid);
    }

    private function guardLastActiveAdmin(User $user, UserRole $newRole, UserStatus $newStatus): void
    {
        $wasActiveAdmin = $user->role === UserRole::BusinessAdmin && $user->status === UserStatus::Active;
        $willBeActiveAdmin = $newRole === UserRole::BusinessAdmin && $newStatus === UserStatus::Active;

        if (! $wasActiveAdmin || $willBeActiveAdmin) {
            return;
        }

        $otherActiveAdmins = User::query()
            ->where('business_id', $user->business_id)
            ->where('role', UserRole::BusinessAdmin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->getKey())
            ->count();

        if ($otherActiveAdmins === 0) {
            throw StaffPolicyException::lastActiveAdmin();
        }
    }

    /**
     * Only enforced when this update actually touches the agent's role or
     * locations — a status-only edit of an existing agent is left alone.
     *
     * @param  array<int, int>|null  $locationIds
     */
    private function guardLocationAgentHasLocation(User $user, ?UserRole $requestedRole, UserRole $newRole, ?array $locationIds): void
    {
        if ($newRole !== UserRole::LocationAgent) {
            return;
        }

        $changingToAgent = $requestedRole === UserRole::LocationAgent && $user->role !== UserRole::LocationAgent;
        $changingLocations = $locationIds !== null;

        if (! $changingToAgent && ! $changingLocations) {
            return;
        }

        $count = $locationIds !== null
            ? count($locationIds)
            : $user->locations()->count();

        if ($count === 0) {
            throw StaffPolicyException::locationAgentNeedsLocation();
        }
    }
}
