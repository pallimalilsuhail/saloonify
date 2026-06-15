<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\ListBusinesses;

use App\Modules\Businesses\DTOs\BusinessSummary;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListBusinessesHandler implements RequestHandler
{
    /**
     * @param  ListBusinesses  $request
     */
    public function handle(Request $request): LengthAwarePaginator
    {
        return Business::query()
            ->when($request->search, function ($query, string $term): void {
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('slug', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($request->perPage)
            ->through(fn (Business $b) => BusinessSummary::fromModel($b));
    }
}
