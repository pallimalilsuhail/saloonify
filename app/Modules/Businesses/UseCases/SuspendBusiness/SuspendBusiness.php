<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\SuspendBusiness;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see SuspendBusinessHandler
 *
 * @implements Request<Id>
 */
final readonly class SuspendBusiness implements Request
{
    public function __construct(
        public Id $businessId,
    ) {}
}
