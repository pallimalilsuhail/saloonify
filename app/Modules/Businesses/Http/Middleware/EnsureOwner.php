<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isOwner() || ! $user->business_id) {
            abort(403, 'Owner access required.');
        }

        return $next($request);
    }
}
