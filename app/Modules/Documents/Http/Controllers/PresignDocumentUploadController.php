<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Http\Requests\PresignDocumentUploadRequest;
use App\Modules\Documents\UseCases\PresignDocumentUpload\PresignDocumentUpload;
use AvoqadoDev\UseCase\BusinessRules\Exceptions\BusinessRuleException;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Shared\ValueObjects\Token;

final class PresignDocumentUploadController
{
    public function __invoke(PresignDocumentUploadRequest $request, string $token): JsonResponse
    {
        try {
            $parsedToken = Token::fromUrlSafe($token);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Upload link not found.'], 404);
        }

        try {
            $result = Mediator::dispatch(new PresignDocumentUpload(
                token: $parsedToken,
                filename: $request->asString('filename'),
                mime: $request->asString('mime'),
                sizeBytes: (int) $request->input('size'),
            ));
        } catch (UploadSessionNotAccepting $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        } catch (BusinessRuleException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->brokenRule()->code(),
                'context' => $e->brokenRule()->context(),
            ], 422);
        }

        return response()->json($result->toJson(), 201);
    }
}
