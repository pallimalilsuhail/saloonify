<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Controllers;

use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Shared\ValueObjects\Token;

final class AcceptInviteController
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        try {
            $parsed = Token::fromUrlSafe($token);
        } catch (\InvalidArgumentException) {
            abort(404, 'Invitation link is invalid.');
        }

        $invitation = Invitation::query()
            ->where('token_hash', $parsed->hash())
            ->first();

        if (! $invitation) {
            abort(404, 'Invitation link is invalid.');
        }

        if ($invitation->status !== InvitationStatus::Pending || $invitation->isExpired()) {
            abort(410, 'This invitation is no longer valid.');
        }

        $request->session()->put('pending_invitation_token', $token);
        $request->session()->put('pending_invitation_email', $invitation->email);

        return redirect()->route('login');
    }
}
