<?php

declare(strict_types=1);

namespace App\Modules\Businesses\Http\Requests;

use App\Http\Requests\FormRequest;
use Illuminate\Validation\Validator;
use Shared\ValueObjects\Address;
use Shared\ValueObjects\OpeningHours;

final class AddLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isSuperAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $time = ['required', 'string', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'array'],
            'address.street' => ['required', 'string'],
            'address.city' => ['required', 'string'],
            'address.emirate' => ['required', 'string'],
            'address.country' => ['required', 'string', 'size:2'],
            'opening_hours' => ['required', 'array', 'min:1'],
            'opening_hours.*.open' => $time,
            'opening_hours.*.close' => $time,
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var array<string, array<string, string>> $hours */
            $hours = (array) $this->input('opening_hours', []);

            foreach ($hours as $day => $window) {
                $open = is_array($window) ? ($window['open'] ?? null) : null;
                $close = is_array($window) ? ($window['close'] ?? null) : null;

                if (is_string($open) && is_string($close) && $open >= $close) {
                    $validator->errors()->add("opening_hours.{$day}", "Opening time must be before closing time for {$day}.");
                }
            }
        });
    }

    public function businessUlid(): string
    {
        return (string) $this->route('business');
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
