<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;
use JsonSerializable;

final readonly class Address implements Castable, JsonSerializable
{
    public function __construct(
        public string $street,
        public string $city,
        public string $emirate,
        public string $country,
    ) {
        foreach (['street' => $street, 'city' => $city, 'emirate' => $emirate] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("Address {$field} cannot be empty");
            }
        }

        if (strlen($country) !== 2) {
            throw new InvalidArgumentException('Address country must be a 2-letter ISO code');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            street: (string) ($data['street'] ?? ''),
            city: (string) ($data['city'] ?? ''),
            emirate: (string) ($data['emirate'] ?? ''),
            country: strtoupper((string) ($data['country'] ?? '')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'emirate' => $this->emirate,
            'country' => $this->country,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get($model, string $key, $value, array $attributes): ?Address
            {
                return $value === null ? null : Address::fromArray(json_decode($value, true));
            }

            public function set($model, string $key, $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                $address = $value instanceof Address ? $value : Address::fromArray((array) $value);

                return json_encode($address->toArray());
            }
        };
    }
}
