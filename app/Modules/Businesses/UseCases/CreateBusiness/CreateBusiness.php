<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\CreateBusiness;

use AvoqadoDev\UseCase\Contracts\Request;
use Shared\ValueObjects\Id;

/**
 * @see CreateBusinessHandler
 *
 * @implements Request<Id>
 */
final readonly class CreateBusiness implements Request
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
    ) {}
}
