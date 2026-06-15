<?php

declare(strict_types=1);

namespace App\Modules\Logger;

use App\Modules\Logger\Formatters\JsonFormatter;
use App\Modules\Logger\Listeners\EventLoggingSubscriber;
use App\Modules\Logger\Processors\EnhancedContextProcessor;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class LoggerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/logger.php',
            'modules.logger'
        );
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/config/logger.php' => config_path('modules/logger.php'),
        ], 'logger-config');

        if ($this->app['config']->get('modules.logger.auto_log_events', true)) {
            Event::subscribe(EventLoggingSubscriber::class);
        }

        $this->registerLogChannels();
    }

    protected function registerLogChannels(): void
    {
        // Override the default single channel to use JSON format with enhanced context
        $this->app['config']->set('logging.channels.single', [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.json'),
            'level' => config('logging.channels.single.level', 'debug'),
            'formatter' => JsonFormatter::class,
            'tap' => [EnhancedContextProcessor::class],
            'replace_placeholders' => true,
        ]);

        // Also update daily channel if used
        $this->app['config']->set('logging.channels.daily', [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.json'),
            'level' => config('logging.channels.daily.level', 'debug'),
            'days' => config('logging.channels.daily.days', 14),
            'formatter' => JsonFormatter::class,
            'tap' => [EnhancedContextProcessor::class],
            'replace_placeholders' => true,
        ]);
    }
}
