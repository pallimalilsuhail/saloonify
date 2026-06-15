<?php

declare(strict_types=1);

namespace App\Modules\DocumentRequests\Models;

use App\Models\User;
use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Enums\UploadSessionStatus;
use Database\Factories\DocumentRequests\UploadSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;
use Shared\ValueObjects\Id;

/**
 * @property int $id
 * @property string $ulid
 * @property int $business_id
 * @property int $customer_id
 * @property string $token_hash
 * @property UploadSessionStatus $status
 * @property int $max_files
 * @property int $max_bytes
 * @property array<array-key, mixed> $allowed_mime
 * @property Carbon $expires_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $revoked_at
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read User|null $createdBy
 * @property-read Customer|null $customer
 *
 * @method static \Database\Factories\DocumentRequests\UploadSessionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereAllowedMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereCreatedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereMaxBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereMaxFiles($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereSubmittedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UploadSession whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class UploadSession extends Model
{
    /** @use HasFactory<UploadSessionFactory> */
    use HasFactory, HasUlid, Unguarded;

    protected $table = 'document_requests_upload_sessions';

    protected $casts = [
        'status' => UploadSessionStatus::class,
        'allowed_mime' => 'array',
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
        'revoked_at' => 'datetime',
        'max_files' => 'integer',
        'max_bytes' => 'integer',
    ];

    public function id(): Id
    {
        return Id::fromString($this->ulid);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function isActive(): bool
    {
        return $this->status->is(UploadSessionStatus::Active) && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->status->is(UploadSessionStatus::Submitted);
    }

    public function isRevoked(): bool
    {
        return $this->status->is(UploadSessionStatus::Revoked);
    }

    protected static function newFactory(): UploadSessionFactory
    {
        return UploadSessionFactory::new();
    }
}
