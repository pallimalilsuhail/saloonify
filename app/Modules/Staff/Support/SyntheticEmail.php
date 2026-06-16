<?php

declare(strict_types=1);

namespace App\Modules\Staff\Support;

use Illuminate\Support\Str;

/**
 * Generates a synthetic email for emailless staff so `users.email`
 * stays populated + unique. Format: <username>@<business-slug>.saloonify.local
 */
final class SyntheticEmail
{
    public static function make(string $username, string $businessSlug): string
    {
        $user = Str::slug($username);
        $slug = Str::slug($businessSlug);

        return "{$user}@{$slug}.saloonify.local";
    }
}
