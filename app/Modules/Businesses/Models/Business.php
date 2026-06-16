<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Models;

use App\Models\User;
use Database\Factories\Businesses\BusinessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
 * @property string $tax_rate
 * @property array<string, mixed>|null $invoice_template_settings_json
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
