<?php

declare(strict_types=1);

namespace App\Modules\Staff\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * A staff business-rule violation (e.g. demoting the last active admin,
 * mutating a terminated user). Renders as HTTP 409.
 */
final class StaffPolicyException extends RuntimeException
{
    public static function lastActiveAdmin(): self
    {
        return new self('A business must keep at least one active business admin.');
    }

    public static function terminatedIsTerminal(): self
    {
        return new self('A terminated user cannot be modified or reactivated.');
    }

    public static function locationAgentNeedsLocation(): self
    {
        return new self('A location agent must be assigned to at least one location.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
