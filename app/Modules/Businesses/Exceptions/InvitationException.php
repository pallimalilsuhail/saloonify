<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Exceptions;

use RuntimeException;

final class InvitationException extends RuntimeException
{
    public static function notAuthorisedToInvite(): self
    {
        return new self('You are not authorised to invite users.');
    }

    public static function notAuthorisedForBusiness(): self
    {
        return new self('You are not authorised to manage this business.');
    }

    public static function alreadyInvited(string $email): self
    {
        return new self("A pending invitation already exists for {$email}.");
    }

    public static function invalidToken(): self
    {
        return new self('This invitation link is invalid or has expired.');
    }

    public static function emailMismatch(): self
    {
        return new self('This invitation was issued to a different email address.');
    }

    public static function alreadyConsumed(): self
    {
        return new self('This invitation has already been used.');
    }

    public static function memberNotInBusiness(): self
    {
        return new self('That user is not a member of this business.');
    }

    public static function cannotChangeOwnRole(): self
    {
        return new self('You cannot change your own role.');
    }
}
