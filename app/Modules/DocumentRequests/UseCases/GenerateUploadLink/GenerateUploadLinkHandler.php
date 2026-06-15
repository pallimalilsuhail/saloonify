<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\UseCases\GenerateUploadLink;

use App\Models\User;
use App\Modules\Common\QueryFilters\BelongsToBusiness;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\DTOs\IssuedUploadLink;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Events\UploadLinkGenerated;
use App\Modules\DocumentRequests\Models\UploadSession;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

final readonly class GenerateUploadLinkHandler implements RequestHandler
{
    /**
     * @param  GenerateUploadLink  $request
     */
    public function handle(Request $request): IssuedUploadLink
    {
        $customer = Customer::query()
            ->tap(new BelongsToBusiness($request->businessId))
            ->where('ulid', $request->customerId->toString())
            ->firstOrFail();

        $token = Token::generate();
        $expiresAt = now()->addMinutes($this->resolveExpiryMinutes($request));

        $session = UploadSession::create([
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'token_hash' => $token->hash(),
            'status' => UploadSessionStatus::Active->value,
            'max_files' => (int) config('uploads.max_files'),
            'max_bytes' => (int) config('uploads.max_bytes'),
            'allowed_mime' => (array) config('uploads.allowed_mime'),
            'expires_at' => $expiresAt,
            'created_by_id' => $this->resolveCreatorId($request),
        ]);

        $sessionId = Id::fromString($session->ulid);

        Event::dispatch(new UploadLinkGenerated(
            sessionId: $sessionId,
            businessId: $request->businessId,
            customerId: $request->customerId,
            generatedById: $request->generatedById,
        ));

        return new IssuedUploadLink(
            sessionId: $sessionId,
            rawToken: $token->urlSafe(),
            url: url('/u/'.$token->urlSafe()),
            expiresAt: CarbonImmutable::parse($expiresAt),
        );
    }

    private function resolveExpiryMinutes(GenerateUploadLink $request): int
    {
        if ($request->expiryMinutes !== null && $request->expiryMinutes > 0) {
            return $request->expiryMinutes;
        }

        return (int) config('uploads.expiry_minutes', 60);
    }

    private function resolveCreatorId(GenerateUploadLink $request): ?int
    {
        if (! $request->generatedById instanceof Id) {
            return null;
        }

        return User::query()
            ->where('ulid', $request->generatedById->toString())
            ->value('id');
    }
}
