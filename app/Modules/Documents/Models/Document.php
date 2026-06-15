<?php

declare(strict_types=1);

namespace App\Modules\Documents\Models;

use App\Modules\Businesses\Models\Business;
use App\Modules\Customers\Models\Customer;
use App\Modules\DocumentRequests\Models\UploadSession;
use App\Modules\Documents\Enums\DocumentStatus;
use Database\Factories\Documents\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;
use Shared\ValueObjects\Id;

/**
 * @property int $id
 * @property string $ulid
 * @property int $business_id
 * @property int $customer_id
 * @property int $upload_session_id
 * @property string $s3_key
 * @property string $original_name
 * @property string $mime
 * @property int $size_bytes
 * @property string|null $checksum
 * @property DocumentStatus $status
 * @property Carbon|null $uploaded_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Customer|null $customer
 * @property-read UploadSession $uploadSession
 *
 * @method static \Database\Factories\Documents\DocumentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereChecksum($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereMime($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereOriginalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereS3Key($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereSizeBytes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUploadSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUploadedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUlid, SoftDeletes, Unguarded;

    protected $table = 'documents_documents';

    protected $casts = [
        'status' => DocumentStatus::class,
        'uploaded_at' => 'datetime',
        'deleted_at' => 'datetime',
        'size_bytes' => 'integer',
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

    public function uploadSession(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class);
    }

    public function isPending(): bool
    {
        return $this->status->is(DocumentStatus::Pending);
    }

    public function isConfirmed(): bool
    {
        return $this->status->is(DocumentStatus::Confirmed);
    }

    protected static function newFactory(): DocumentFactory
    {
        return DocumentFactory::new();
    }
}
