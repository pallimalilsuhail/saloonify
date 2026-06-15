<?php

declare(strict_types=1);

namespace App\Support\Extractors;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Shared\ValueObjects\Id;

/**
 * Id value object extractor with advanced features (defaults, nullability).
 */
final class IdExtractor
{
    private bool $nullable = false;

    private ?Id $default = null;

    public function __construct(
        private readonly Request $request,
        private readonly string $attribute,
    ) {}

    public function nullable(): self
    {
        $this->nullable = true;

        return $this;
    }

    public function default(Id $value): self
    {
        $this->default = $value;
        $this->nullable = true;

        return $this;
    }

    public function get(): ?Id
    {
        $value = $this->request->input($this->attribute);

        if ($value === null || $value === '') {
            if ($this->default instanceof Id) {
                return $this->default;
            }

            if ($this->nullable) {
                return null;
            }

            throw new InvalidArgumentException(
                "Field '{$this->attribute}' is required but was not provided"
            );
        }

        return Id::fromString((string) $value);
    }

    public function __invoke(): ?Id
    {
        return $this->get();
    }
}
