<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Http\Controllers;

use App\Modules\DocumentRequests\Exceptions\InvalidUploadToken;
use App\Modules\DocumentRequests\UseCases\ValidateToken\ValidateToken;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use InvalidArgumentException;
use Shared\ValueObjects\Token;

final class ShowUploadController
{
    public function __invoke(string $token): View|Response
    {
        try {
            $parsedToken = Token::fromUrlSafe($token);
        } catch (InvalidArgumentException) {
            return $this->notFound();
        }

        try {
            $validated = Mediator::dispatch(new ValidateToken($parsedToken));
        } catch (InvalidUploadToken) {
            return $this->notFound();
        }

        if ($validated->isSubmitted()) {
            return response()->view('public.upload.submitted', ['session' => $validated]);
        }

        if ($validated->isRevoked() || $validated->isExpired()) {
            return response()->view('public.upload.expired', ['session' => $validated], 410);
        }

        return response()->view('public.upload.form', [
            'session' => $validated,
            'rawToken' => $token,
        ]);
    }

    private function notFound(): Response
    {
        return response()->view('public.upload.invalid', [], 404);
    }
}
