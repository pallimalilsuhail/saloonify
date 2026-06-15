<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use App\Modules\Businesses\Enums\InvitationStatus;
use App\Modules\Businesses\Enums\UserRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;

/**
 * @property int $id
 * @property string $ulid
 * @property int $business_id
 * @property string $email
 * @property UserRole $role
 * @property string $token_hash
 * @property InvitationStatus $status
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property int|null $invited_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read User|null $invitedBy
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereAcceptedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereInvitedById($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereRevokedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereRole($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereTokenHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Invitation whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Invitation extends Model
{
    use HasUlid, Unguarded;

    protected $table = 'businesses_invitations';

    protected $casts = [
        'status' => InvitationStatus::class,
        'role' => UserRole::class,
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function isPending(): bool
    {
        return $this->status->is(InvitationStatus::Pending) && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
