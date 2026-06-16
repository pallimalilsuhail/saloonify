<?php

declare(strict_types=1);

namespace App\Modules\Staff\DTOs;

final readonly class StaffCreated
{
    public function __construct(
        public string $userId,
        public string $email,
    ) {}
}
