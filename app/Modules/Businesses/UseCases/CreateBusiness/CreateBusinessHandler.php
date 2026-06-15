<?php

declare(strict_types=1);

namespace App\Modules\Businesses\UseCases\CreateBusiness;

use App\Modules\Businesses\BusinessRules\SlugMustBeUnique;
use App\Modules\Businesses\Enums\BusinessStatus;
use App\Modules\Businesses\Models\Business;
use AvoqadoDev\UseCase\BusinessRules\Contracts\GuardsRules;
use AvoqadoDev\UseCase\Contracts\Request;
use AvoqadoDev\UseCase\Contracts\RequestHandler;
use Illuminate\Support\Str;
use Shared\ValueObjects\Id;

final readonly class CreateBusinessHandler implements RequestHandler
{
    public function __construct(private GuardsRules $guards) {}

    /**
     * @param  CreateBusiness  $request
     */
    public function handle(Request $request): Id
    {
        $slug = $this->resolveSlug($request);

        $this->guards->guard(new SlugMustBeUnique($slug));

        $business = Business::create([
            'name' => $request->name,
            'slug' => $slug,
            'status' => BusinessStatus::Active->value,
        ]);

        return Id::fromString($business->ulid);
    }

    private function resolveSlug(CreateBusiness $request): string
    {
        if ($request->slug !== null && $request->slug !== '') {
            return Str::slug($request->slug);
        }

        return Str::slug($request->name).'-'.Str::lower(Str::random(6));
    }
}
