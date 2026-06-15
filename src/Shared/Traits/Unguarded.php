<?php

declare(strict_types=1);

namespace Shared\Traits;

trait Unguarded
{
    public function initializeUnguarded(): void
    {
        $this->guarded = [];
    }
}
