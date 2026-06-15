# Logger Module - Event Logging

## Overview

The Logger module provides automatic event logging for your Laravel application. It automatically captures and logs all events that implement the `JsonSerializable` interface using Laravel's built-in Log facade.

## What It Does

- **Automatically logs events** - Any event implementing `JsonSerializable` is logged
- **Filters framework events** - Excludes Laravel/Illuminate internal events  
- **Single log file** - Events are stored in `storage/logs/events.log`
- **Zero configuration** - Works out of the box
- **Uses Laravel Log facade** - No custom logger class, just Laravel's native logging

## Installation

The module is already registered in your application. To verify it's working:

```bash
php artisan tinker
>>> event(new \App\Events\SomeEvent()); // If you have an event
>>> // Check storage/logs/events-{date}.log
```

## Usage

### Creating Loggable Events

Simply implement `JsonSerializable` on your event classes:

```php
<?php

namespace App\Events;

use JsonSerializable;

class UserRegistered implements JsonSerializable
{
    public function __construct(
        public readonly User $user,
        public readonly string $source
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'name' => $this->user->name,
            'source' => $this->source,
            'registered_at' => now()->toIso8601String(),
        ];
    }
}
```

### Firing Events

```php
// The event will be automatically logged
event(new UserRegistered($user, 'web'));

// Check the log file
// storage/logs/events.log
```

### Log Output Format

```
[2024-01-10 15:30:45] events.INFO: Event: App\Events\UserRegistered {
    "event_name": "App\\Events\\UserRegistered",
    "event_data": {
        "user_id": 123,
        "email": "user@example.com",
        "name": "John Doe",
        "source": "web",
        "registered_at": "2024-01-10T15:30:45+00:00"
    },
    "timestamp": "2024-01-10T15:30:45+00:00"
}
```

## Configuration

### Environment Variables

```env
# Enable/disable automatic event logging
LOGGER_AUTO_LOG_EVENTS=true
```

### Configuration File

Publish the config file if you need to customize:

```bash
php artisan vendor:publish --tag=logger-config
```

Edit `config/modules/logger.php` to:
- Add/remove excluded event patterns
- Customize which events to skip

### Excluded Events

By default, these events are NOT logged:
- All Laravel/Illuminate framework events
- Eloquent model events
- View composing events
- Cache events

## File Structure

```
app/Modules/Logger/
├── LoggerServiceProvider.php     # Service provider
├── Listeners/
│   └── EventLoggingSubscriber.php # Event listener (uses Log facade)
└── config/
    └── logger.php                # Configuration
```

## How It Works

1. **EventLoggingSubscriber** listens to all events (`*`)
2. Filters out framework events based on patterns
3. Checks if event implements `JsonSerializable`
4. Logs the event data using Laravel's Log facade to the 'events' channel
5. Daily log rotation keeps logs organized by date

## Testing

```php
use Illuminate\Support\Facades\Event;

class EventLoggingTest extends TestCase
{
    public function test_events_are_logged()
    {
        Event::fake();
        
        // Fire your event
        event(new UserRegistered($user, 'api'));
        
        // Assert event was dispatched
        Event::assertDispatched(UserRegistered::class);
    }
}
```

## Next Steps

This is a minimal implementation focused only on event logging. You can incrementally add:
- Request tracing with X-Request-Id
- Context propagation
- Performance monitoring
- Structured JSON formatting
- Multiple log channels
- Audit logging

## Troubleshooting

### Events not being logged?

1. Check if event implements `JsonSerializable`
2. Verify `LOGGER_AUTO_LOG_EVENTS=true` in `.env`
3. Check if event matches excluded patterns in config
4. Look for the log file: `storage/logs/events.log`
5. Check file permissions on `storage/logs/` directory