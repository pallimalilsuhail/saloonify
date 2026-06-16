<?php

declare(strict_types=1);

namespace App\Modules\Staff\DTOs;

final readonly class StaffUpdated
{
    public function __construct(
        public string $userId,
    ) {}
}
