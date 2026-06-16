<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;

/**
 * @property int $id
 * @property string $ulid
 * @property int $business_id
 * @property string $name
 * @property array<string, mixed> $address_json
 * @property array<string, mixed> $opening_hours_json
 */
final class Location extends Model
{
    use HasUlid, SoftDeletes, Unguarded;

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
