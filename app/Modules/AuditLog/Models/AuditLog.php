<?php

declare(strict_types=1);

namespace App\Modules\AuditLog\Models;

use App\Models\User;
use App\Modules\Businesses\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;
use Shared\ValueObjects\Id;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $business_id
 * @property int|null $actor_id
 * @property string $actor_type
 * @property string $action
 * @property string|null $target_type
 * @property string|null $target_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property array<array-key, mixed>|null $meta
 * @property Carbon $created_at
 * @property-read User|null $actor
 * @property-read Business|null $business
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereAction($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereActorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereActorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereIp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereMeta($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereTargetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereTargetType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuditLog whereUserAgent($value)
 *
 * @mixin \Eloquent
 */
final class AuditLog extends Model
{
    use HasUlid, Unguarded;

    protected $table = 'audit_logs';

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
        'meta' => 'array',
    ];

    public function id(): Id
    {
        return Id::fromString($this->ulid);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
