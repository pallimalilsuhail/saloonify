<?php

declare(strict_types=1);

namespace App\Modules\Documents\Exceptions;

use RuntimeException;

final class DocumentAccessDenied extends RuntimeException
{
    public static function notInBusiness(): self
    {
        return new self('You are not authorised to view this document.');
    }
}
