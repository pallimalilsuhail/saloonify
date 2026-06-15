<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\SuspendBusiness;

use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Shared\ValueObjects\Id;

final readonly class SuspendBusinessHandler implements RequestHandler
{
    /**
     * @param  SuspendBusiness  $request
     */
    public function handle(Request $request): Id
    {
        Business::query()
            ->where('ulid', $request->businessId->toString())
            ->firstOrFail()
            ->update(['status' => BusinessStatus::Suspended->value]);

        return $request->businessId;
    }
}
