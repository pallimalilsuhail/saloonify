<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Exceptions;

use RuntimeException;

/**
 * Thrown when the inbound upload token cannot be parsed or does not match
 * any session in the DB. Treated as 404 by the public landing controller
 * — same code path as a missing session so we don't leak which case
 * actually occurred.
 */
final class InvalidUploadToken extends RuntimeException
{
    public static function unparseable(): self
    {
        return new self('Invalid upload token format.');
    }

    public static function notFound(): self
    {
        return new self('Upload link not found.');
    }
}
