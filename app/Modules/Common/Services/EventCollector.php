<?php

declare(strict_types=1);

namespace App\Modules\Common\Services;

use Illuminate\Support\Facades\Event;

/**
 * Buffers events during a multi-step handler so they only fire after every
 * pipe has succeeded. Avoids partial-event-leakage when a later pipe throws.
 *
 * Inject as a constructor dep on a handler, hand the same instance to every
 * pipe via the Passable, then call dispatchAll() in the pipeline's then().
 */
final class EventCollector
{
    /** @var array<int, object> */
    private array $events = [];

    public function collect(object $event): void
    {
        $this->events[] = $event;
    }

    public function dispatchAll(): void
    {
        foreach ($this->events as $event) {
            Event::dispatch($event);
        }

        $this->events = [];
    }

    public function hasEvents(): bool
    {
        return ! empty($this->events);
    }

    public function count(): int
    {
        return count($this->events);
    }

    /**
     * @return array<int, object>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
