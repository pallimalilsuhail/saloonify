<?php

declare(strict_types=1);

namespace App\Modules\Common\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate routes to authenticated users who have been assigned to a business
 * (any role: owner or member). Super admins are not members of a single
 * business, so they're rejected here.
 */
final class EnsureBusinessMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->business_id) {
            abort(403, 'You must be assigned to a business to access this page.');
        }

        return $next($request);
    }
}
