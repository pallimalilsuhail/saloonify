<?php

declare(strict_types=1);

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use App\Modules\DocumentRequests\Models\UploadSession;
use Shared\ValueObjects\Token;

function makeSessionAt(Token $token, string $status, ?string $expiresAt = null, ?string $businessName = null): UploadSession
{
    $business = Business::factory()->create($businessName ? ['name' => $businessName] : []);
    $customer = Customer::factory()->for($business)->create(['name' => 'Sensitive Customer Name']);

    return UploadSession::factory()->create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'token_hash' => $token->hash(),
        'status' => $status,
        'expires_at' => $expiresAt ?? now()->addHour(),
    ]);
}

it('renders the form view for an active session and shows the business name', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Active->value, businessName: 'Acme Insurance');

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertOk()
        ->assertSee('Acme Insurance')
        ->assertSee('Send to Acme Insurance')
        ->assertDontSee('Sensitive Customer Name');
});

it('wires the Alpine uploader with token + presign + confirm urls + limits', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Active->value);

    $response = $this->get('/u/'.$token->urlSafe());

    $html = stripslashes($response->getContent());

    expect($html)
        ->toContain('x-data')
        ->toContain('uploader(')
        ->toContain('/api/u/'.$token->urlSafe().'/presign')
        ->toContain('/api/u/'.$token->urlSafe().'/confirm');
});

it('renders the submitted view (200) when the session has already been submitted', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Submitted->value);

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertOk()
        ->assertSee('Documents already received');
});

it('renders the expired view with HTTP 410 when expires_at is past', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Active->value, expiresAt: now()->subMinute()->toDateTimeString());

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertStatus(410)
        ->assertSee('This upload link is no longer active');
});

it('renders the expired view with HTTP 410 when revoked', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Revoked->value);

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertStatus(410)
        ->assertSee('This upload link is no longer active');
});

it('renders the invalid view with HTTP 404 when the token does not match any session', function () {
    $token = Token::generate();

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertNotFound()
        ->assertSee('Upload link not found');
});

it('renders the invalid view with HTTP 404 when the token is malformed', function () {
    $response = $this->get('/u/not-a-real-token');

    $response->assertNotFound()
        ->assertSee('Upload link not found');
});

it('exposes no app chrome on the public upload page', function () {
    $token = Token::generate();
    makeSessionAt($token, UploadSessionStatus::Active->value);

    $response = $this->get('/u/'.$token->urlSafe());

    $response->assertDontSee('flux:sidebar')
        ->assertDontSee('Dashboard')
        ->assertDontSee('Settings');
});
