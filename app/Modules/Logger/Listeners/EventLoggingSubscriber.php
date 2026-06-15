<?php

declare(strict_types=1);

namespace App\Modules\Logger\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Throwable;

class EventLoggingSubscriber
{
    private array $excludedEvents = [
        'Illuminate\*',
        'Laravel\*',
        'eloquent.*',
        'bootstrapped:*',
        'bootstrapping:*',
        'creating:*',
        'composing:*',
    ];

    public function __construct()
    {
        $this->excludedEvents = config('modules.logger.excluded_events', $this->excludedEvents);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            '*' => 'handleEvent',
        ];
    }

    public function handleEvent(string $eventName, array $data): void
    {
        if ($this->shouldSkipEvent($eventName)) {
            return;
        }

        try {
            $event = $data[0] ?? null;

            if (! $event || ! ($event instanceof JsonSerializable)) {
                return;
            }

            $eventData = $event->jsonSerialize();
            $eventData = is_array($eventData) ? $eventData : ['data' => $eventData];

            Log::info("Event raised: {$eventName}", [
                'event_name' => $eventName,
                'event_data' => $eventData,
                'timestamp' => now()->toIso8601String(),
            ]);
        } catch (Throwable) {
            // Silently fail to avoid breaking the application
        }
    }

    private function shouldSkipEvent(string $eventName): bool
    {
        foreach ($this->excludedEvents as $pattern) {
            $regex = '/^'.str_replace('\*', '.*', preg_quote((string) $pattern, '/')).'$/';
            if (preg_match($regex, $eventName)) {
                return true;
            }
        }

        return false;
    }
}
