<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\CloudStorage\CloudStorageService;
use App\Services\CloudStorage\S3CloudStorageService;
use App\Support\RequestMixin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CloudStorageService::class, S3CloudStorageService::class);
    }

    public function boot(): void
    {
        Request::mixin(new RequestMixin);

        // 20 presign calls per 5 minutes per token. Token is in the URL
        // path so we key the limiter on the route parameter.
        RateLimiter::for('upload-presign', function (Request $request) {
            $token = (string) $request->route('token');

            return Limit::perMinutes(5, 20)->by('upload-presign:'.$token);
        });
    }
}
