<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Shared\Concerns\BelongsToBusiness;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;

/**
 * @property int $id
 * @property string $ulid
 * @property int $business_id
 * @property string $name
 * @property array<array-key, mixed> $address_json
 * @property array<array-key, mixed> $opening_hours_json
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Business|null $business
 * @property-read Collection<int, User> $staff
 * @property-read int|null $staff_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereAddressJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereOpeningHoursJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereUlid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Location withoutTrashed()
 *
 * @mixin \Eloquent
 */
final class Location extends Model
{
    use BelongsToBusiness, HasUlid, SoftDeletes, Unguarded;

    protected $table = 'locations';

    protected $casts = [
        'address_json' => 'array',
        'opening_hours_json' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'location_user');
    }
}
