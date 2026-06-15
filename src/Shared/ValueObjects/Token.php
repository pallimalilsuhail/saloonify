<?php

declare(strict_types=1);

namespace Shared\ValueObjects;

use InvalidArgumentException;
use SensitiveParameter;

/**
 * Opaque random token suitable for one-time URLs (upload links, magic links, invites).
 *
 * Always stored hashed (SHA-256). The raw value is only known at generation time
 * and when received from an inbound request — never persisted in the clear.
 */
final readonly class Token
{
    private const BYTES = 32;

    private function __construct(
        #[SensitiveParameter] private string $raw,
    ) {}

    public static function generate(): self
    {
        return new self(random_bytes(self::BYTES));
    }

    public static function fromUrlSafe(#[SensitiveParameter] string $urlSafe): self
    {
        $padded = strtr($urlSafe, '-_', '+/');
        $padding = strlen($padded) % 4;
        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($padded, true);
        if ($decoded === false || strlen($decoded) !== self::BYTES) {
            throw new InvalidArgumentException('Invalid token format.');
        }

        return new self($decoded);
    }

    public function urlSafe(): string
    {
        return rtrim(strtr(base64_encode($this->raw), '+/', '-_'), '=');
    }

    public function hash(): string
    {
        return hash('sha256', $this->raw);
    }

    public function equalsHash(string $hexHash): bool
    {
        return hash_equals($hexHash, $this->hash());
    }
}
