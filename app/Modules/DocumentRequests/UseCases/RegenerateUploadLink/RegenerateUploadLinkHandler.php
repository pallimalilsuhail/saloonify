<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\RegenerateUploadLink;

use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\GenerateUploadLink\GenerateUploadLink;
use App\Modules\DocumentRequests\UseCases\RevokeUploadLink\RevokeUploadLink;
use AvoqadoDev\UseCase\Contracts\Mediator;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Shared\ValueObjects\Id;

/**
 * Convenience use case that revokes any active upload links for a
 * customer, then issues a new one. Composes RevokeUploadLink +
 * GenerateUploadLink so each fires its own event (audit listeners
 * see both actions distinctly when #32 lands).
 */
final readonly class RegenerateUploadLinkHandler implements RequestHandler
{
    public function __construct(
        private Mediator $mediator,
    ) {}

    /**
     * @param  RegenerateUploadLink  $request
     */
    public function handle(Request $request): IssuedUploadLink
    {
        $customer = Customer::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->customerId->toString())
            ->firstOrFail();

        $activeSessions = UploadSession::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('customer_id', $customer->id)
            ->where('status', UploadSessionStatus::Active->value)
            ->get();

        foreach ($activeSessions as $session) {
            $this->mediator->dispatch(new RevokeUploadLink(
                sessionId: Id::fromString($session->ulid),
                revokedById: $request->generatedById,
            ));
        }

        return $this->mediator->dispatch(new GenerateUploadLink(
            businessId: $request->businessId,
            customerId: $request->customerId,
            generatedById: $request->generatedById,
            expiryMinutes: $request->expiryMinutes,
        ));
    }
}
