<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use Database\Factories\Businesses\BusinessFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Shared\Traits\HasUlid;
use Shared\Traits\Unguarded;

/**
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $slug
 * @property string $trn
 * @property string $country
 * @property string $currency
 * @property numeric $tax_rate
 * @property array<array-key, mixed>|null $invoice_template_settings_json
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Location> $locations
 * @property-read int|null $locations_count
 * @property-read Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\Businesses\BusinessFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereInvoiceTemplateSettingsJson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTaxRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Business whereTrn($value)
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

    protected $table = 'businesses';

    protected $casts = [
        'tax_rate' => 'decimal:2',
        'invoice_template_settings_json' => 'array',
    ];

    protected static function booted(): void
    {
        self::creating(function (Business $business): void {
            if (empty($business->slug)) {
                $business->slug = self::uniqueSlug((string) $business->name);
            }
        });
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'business';
        $slug = $base;
        $suffix = 1;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    protected static function newFactory(): BusinessFactory
    {
        return BusinessFactory::new();
    }
}
