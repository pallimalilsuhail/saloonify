<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\Extractors\IdExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest as BaseFormRequest;
use Shared\ValueObjects\Email;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\PhoneNumber;

/**
 * Base Form Request with type-safe extraction methods.
 *
 * Extractors are added to the underlying Request via Request::mixin(new RequestMixin)
 * in AppServiceProvider, then surfaced here so IDEs can autocomplete them.
 *
 * @method Id asId(string $attribute) Extract required Id value object from request input
 * @method Id|null asIdOrNull(string $attribute) Extract optional Id value object from request input (returns null if empty)
 * @method IdExtractor extractId(string $attribute) Get Id extractor for advanced usage (defaults, validation)
 * @method Id asRouteId(string $parameter) Extract required Id value object from route parameter
 * @method string asString(string $attribute) Extract required string value from request input
 * @method string|null asStringOrNull(string $attribute) Extract optional string value from request input (returns null if empty)
 * @method Email asEmail(string $attribute) Extract required Email value object from request input
 * @method Email|null asEmailOrNull(string $attribute) Extract optional Email value object from request input (returns null if empty)
 * @method PhoneNumber asPhoneNumber(string $attribute) Extract required PhoneNumber value object from request input
 * @method PhoneNumber|null asPhoneNumberOrNull(string $attribute) Extract optional PhoneNumber value object from request input (returns null if empty)
 * @method CarbonImmutable asCarbonImmutable(string $attribute) Extract required CarbonImmutable from request input
 * @method CarbonImmutable|null asCarbonImmutableOrNull(string $attribute) Extract optional CarbonImmutable from request input (returns null if empty)
 */
abstract class FormRequest extends BaseFormRequest
{
    //
}
