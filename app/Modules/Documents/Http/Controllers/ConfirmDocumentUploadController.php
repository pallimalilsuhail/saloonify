<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers;

use App\Modules\Documents\Exceptions\UploadSessionNotAccepting;
use App\Modules\Documents\Http\Requests\ConfirmDocumentUploadRequest;
use App\Modules\Documents\UseCases\ConfirmDocumentUpload\ConfirmDocumentUpload;
use AvoqadoDev\UseCase\Facades\Mediator;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

final class ConfirmDocumentUploadController
{
    public function __invoke(ConfirmDocumentUploadRequest $request, string $token): JsonResponse
    {
        try {
            $parsedToken = Token::fromUrlSafe($token);
        } catch (InvalidArgumentException) {
            return response()->json(['message' => 'Upload link not found.'], 404);
        }

        $documentIds = [];
        foreach ((array) $request->input('document_ids', []) as $rawId) {
            try {
                $documentIds[] = Id::fromString((string) $rawId);
            } catch (InvalidArgumentException) {
                return response()->json([
                    'message' => 'One or more document_ids are invalid.',
                ], 422);
            }
        }

        try {
            $result = Mediator::dispatch(new ConfirmDocumentUpload(
                token: $parsedToken,
                documentIds: $documentIds,
            ));
        } catch (UploadSessionNotAccepting $e) {
            return response()->json(['message' => $e->getMessage()], 410);
        }

        return response()->json($result->toJson(), $result->submitted ? 200 : 207);
    }
}
