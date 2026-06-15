<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's business to the container so
 * BusinessScope can auto-filter tenant queries. Super-admins bind
 * nothing (they see all). Guests pass through untouched.
 */
final class TenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isSuperAdmin()) {
            if ($user->business_id === null) {
                abort(403, 'No business context.');
            }

            app()->instance('tenant.business_id', $user->business_id);
        }

        return $next($request);
    }
}
