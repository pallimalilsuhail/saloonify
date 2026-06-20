<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\Id;
use Shared\ValueObjects\OpeningHours;

final class AddLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isSuperAdmin();
    }

    /**
     * Surface the route business id to the validator so a malformed id is a 422, not a 500.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['business' => $this->route('business')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $time = ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];

        return [
            'business' => ['required', 'ulid'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'array'],
            'address.street' => ['required', 'string'],
            'address.city' => ['required', 'string'],
            'address.emirate' => ['required', 'string'],
            'address.country' => ['required', 'string', 'size:2'],
            // Each day holds a list of same-day ranges (split shifts), e.g. [{open,close},{open,close}].
            'opening_hours' => ['required', 'array', 'min:1'],
            'opening_hours.*' => ['required', 'array', 'min:1'],
            'opening_hours.*.*.open' => $time,
            'opening_hours.*.*.close' => $time,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Skip if the per-field time rules already failed — only structural data reaches here.
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The OpeningHours value object enforces open<close and (via spatie) per-day non-overlap.
            try {
                OpeningHours::fromArray((array) $this->input('opening_hours', []));
            } catch (InvalidArgumentException $e) {
                $validator->errors()->add('opening_hours', $e->getMessage());
            }
        });
    }

    public function businessId(): Id
    {
        return $this->asRouteId('business');
    }

    public function locationName(): string
    {
        return $this->asString('name');
    }

    public function address(): Address
    {
        return Address::fromArray((array) $this->input('address'));
    }

    public function openingHours(): OpeningHours
    {
        return OpeningHours::fromArray((array) $this->input('opening_hours'));
    }
}
