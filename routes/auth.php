<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Businesses\Enums\UserRole;
use App\Modules\Businesses\Exceptions\InvitationException;
use App\Modules\Businesses\UseCases\ConsumeInvite\ConsumeInvite;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\WorkOS\Http\Requests\AuthKitAuthenticationRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLoginRequest;
use Laravel\WorkOS\Http\Requests\AuthKitLogoutRequest;
use Laravel\WorkOS\User as WorkOSUser;
use Shared\ValueObjects\Token;

Route::middleware(['guest'])->group(function (): void {
    Route::get('login', fn (AuthKitLoginRequest $request) => $request->redirect())->name('login');

    Route::get('authenticate', function (AuthKitAuthenticationRequest $request) {
        $pendingToken = $request->session()->pull('pending_invitation_token');
        $pendingEmail = $request->session()->pull('pending_invitation_email');

        $createUsing = function (WorkOSUser $workOsUser) use ($pendingToken, $pendingEmail): User {
            $allowed = (array) config('services.super_admin.emails', []);
            $emailLower = strtolower((string) $workOsUser->email);
            $isSuperAdmin = in_array($emailLower, array_map('strtolower', $allowed), true);
            $hasInvite = $pendingToken && $pendingEmail
                && strtolower((string) $pendingEmail) === $emailLower;

            if (! $isSuperAdmin && ! $hasInvite) {
                abort(403, 'You need an invitation to sign up. Ask the business owner for an invite link.');
            }

            return User::create([
                'name' => trim(($workOsUser->firstName ?? '').' '.($workOsUser->lastName ?? '')) ?: $workOsUser->email,
                'email' => $workOsUser->email,
                'email_verified_at' => now(),
                'workos_id' => $workOsUser->id,
                'avatar' => $workOsUser->avatar ?? '',
                'role' => $isSuperAdmin ? UserRole::SuperAdmin->value : UserRole::Member->value,
            ]);
        };

        $response = tap(
            redirect()->intended(route('dashboard')),
            fn () => $request->authenticate(createUsing: $createUsing),
        );

        if ($pendingToken && Auth::check()) {
            $consume = app(ConsumeInvite::class, [
                'token' => Token::fromUrlSafe((string) $pendingToken),
                'user' => Auth::user(),
            ]);

            try {
                Mediator::dispatch($consume);
            } catch (InvitationException) {
                // Invite no longer valid — user already authenticated; ignore.
            }
        }

        return $response;
    });
});

Route::post('logout', fn (AuthKitLogoutRequest $request) => $request->logout())
    ->middleware(['auth'])->name('logout');
