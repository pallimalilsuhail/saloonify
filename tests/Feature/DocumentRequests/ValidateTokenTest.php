<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\DTOs\ValidatedUploadSession;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Exceptions\InvalidUploadToken;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\DocumentRequests\UseCases\ValidateToken\ValidateToken;
use AvoqadoDev\UseCase\Facades\Mediator;
use Shared\ValueObjects\Token;

function makeSessionForToken(Token $token, array $overrides = []): UploadSession
{
    $business = Business::factory()->create($overrides['business'] ?? []);
    $customer = Customer::factory()->for($business)->create();

    return UploadSession::factory()->create(array_merge([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'token_hash' => $token->hash(),
    ], $overrides['session'] ?? []));
}

it('returns a ValidatedUploadSession DTO for an active session', function () {
    $token = Token::generate();
    makeSessionForToken($token, ['business' => ['name' => 'Acme Insurance']]);

    $result = Mediator::dispatch(new ValidateToken($token));

    expect($result)->toBeInstanceOf(ValidatedUploadSession::class)
        ->and($result->businessName)->toBe('Acme Insurance')
        ->and($result->status)->toBe(UploadSessionStatus::Active)
        ->and($result->isActive())->toBeTrue();
});

it('throws InvalidUploadToken when no session matches the hash', function () {
    Mediator::dispatch(new ValidateToken(Token::generate()));
})->throws(InvalidUploadToken::class);

it('reports Expired status when expires_at is in the past', function () {
    $token = Token::generate();
    makeSessionForToken($token, ['session' => [
        'status' => UploadSessionStatus::Active->value,
        'expires_at' => now()->subMinute(),
    ]]);

    $result = Mediator::dispatch(new ValidateToken($token));

    expect($result->status)->toBe(UploadSessionStatus::Expired)
        ->and($result->isExpired())->toBeTrue()
        ->and($result->isActive())->toBeFalse();
});

it('reports Submitted status when the session has been submitted', function () {
    $token = Token::generate();
    makeSessionForToken($token, ['session' => [
        'status' => UploadSessionStatus::Submitted->value,
        'submitted_at' => now()->subMinute(),
    ]]);

    $result = Mediator::dispatch(new ValidateToken($token));

    expect($result->isSubmitted())->toBeTrue()
        ->and($result->isActive())->toBeFalse();
});

it('reports Revoked status when the session has been revoked', function () {
    $token = Token::generate();
    makeSessionForToken($token, ['session' => [
        'status' => UploadSessionStatus::Revoked->value,
        'revoked_at' => now()->subMinute(),
    ]]);

    $result = Mediator::dispatch(new ValidateToken($token));

    expect($result->isRevoked())->toBeTrue()
        ->and($result->isActive())->toBeFalse();
});

it('exposes the configured limits but no customer info', function () {
    $token = Token::generate();
    makeSessionForToken($token, ['session' => [
        'max_files' => 10,
        'max_bytes' => 5 * 1024 * 1024,
        'allowed_mime' => ['application/pdf'],
    ]]);

    $result = Mediator::dispatch(new ValidateToken($token));

    expect($result->maxFiles)->toBe(10)
        ->and($result->maxBytes)->toBe(5 * 1024 * 1024)
        ->and($result->allowedMime)->toBe(['application/pdf'])
        ->and(get_object_vars($result))->not->toHaveKey('customerName')
        ->and(get_object_vars($result))->not->toHaveKey('customerEmail');
});
