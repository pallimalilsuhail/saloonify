<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\RevokeUploadLink;

use App\Models\User;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadLinkRevoked;
use App\Modules\DocumentRequests\Exceptions\CannotRevokeUploadLink;
use App\Modules\DocumentRequests\Models\UploadSession;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;

final readonly class RevokeUploadLinkHandler implements RequestHandler
{
    /**
     * @param  RevokeUploadLink  $request
     */
    public function handle(Request $request): Id
    {
        $session = UploadSession::query()
            ->where('ulid', $request->sessionId->toString())
            ->with('business', 'customer')
            ->firstOrFail();

        if ($session->status !== UploadSessionStatus::Active) {
            throw CannotRevokeUploadLink::notRevokable();
        }

        $this->authorise($request->revokedById, $session);

        $session->update([
            'status' => UploadSessionStatus::Revoked->value,
            'revoked_at' => now(),
        ]);

        Event::dispatch(new UploadLinkRevoked(
            sessionId: Id::fromString($session->ulid),
            businessId: Id::fromString($session->business->ulid),
            customerId: Id::fromString($session->customer->ulid),
            revokedById: $request->revokedById,
        ));

        return $request->sessionId;
    }

    private function authorise(?Id $actorId, UploadSession $session): void
    {
        if (! $actorId instanceof Id) {
            return;
        }

        $actor = User::query()
            ->where('ulid', $actorId->toString())
            ->first();

        if (! $actor) {
            throw CannotRevokeUploadLink::notAuthorisedForBusiness();
        }

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->business_id !== $session->business_id) {
            throw CannotRevokeUploadLink::notAuthorisedForBusiness();
        }
    }
}
