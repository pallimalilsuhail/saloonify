<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Exceptions;

use RuntimeException;

final class CannotRevokeUploadLink extends RuntimeException
{
    public static function notAuthorisedForBusiness(): self
    {
        return new self('You are not authorised to revoke this upload link.');
    }

    public static function notRevokable(): self
    {
        return new self('Only active upload links can be revoked.');
    }
}
