<?php

declare(strict_types=1);

namespace App\Modules\Documents\UseCases\ConfirmDocumentUpload;

use App\Modules\Documents\DTOs\ConfirmedUpload;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\UsesDatabaseTransaction;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\Token;

/**
 * @see ConfirmDocumentUploadHandler
 *
 * @implements Request<ConfirmedUpload>
 */
final readonly class ConfirmDocumentUpload implements Request, UsesDatabaseTransaction
{
    /**
     * @param  array<int, Id>  $documentIds  ids of documents the browser believes it has uploaded
     */
    public function __construct(
        public Token $token,
        public array $documentIds,
    ) {}

    public function transactionAttempts(): int
    {
        return 1;
    }
}
