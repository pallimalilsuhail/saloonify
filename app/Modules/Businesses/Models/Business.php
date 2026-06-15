<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use App\Modules\Businesses\Enums\BusinessStatus;
use Database\Factories\Businesses\BusinessFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;
use Shared\ValueObjects\Id;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property BusinessStatus $status
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\Businesses\BusinessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, HasUlid, SoftDeletes, Unguarded;

    protected $table = 'businesses_businesses';

    protected $casts = [
        'status' => BusinessStatus::class,
        'deleted_at' => 'datetime',
    ];

    public function id(): Id
    {
        return Id::fromString($this->ulid);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status->is(BusinessStatus::Active);
    }

    protected static function newFactory(): BusinessFactory
    {
        return BusinessFactory::new();
    }
}
