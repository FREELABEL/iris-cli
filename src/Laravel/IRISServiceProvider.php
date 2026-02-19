<?php

declare(strict_types=1);

namespace IRIS\SDK\Laravel;

use Illuminate\Support\ServiceProvider;
use IRIS\SDK\IRIS;

/**
 * IRIS Service Provider for Laravel
 *
 * Registers the IRIS SDK as a singleton in the Laravel container.
 */
class IRISServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config.php', 'iris');

        $this->app->singleton(IRIS::class, function ($app) {
            $config = [
                'api_key' => config('iris.api_key'),
                'base_url' => config('iris.base_url'),
                'iris_url' => config('iris.iris_url'),
                'timeout' => config('iris.timeout', 30),
                'retries' => config('iris.retries', 3),
                'webhook_secret' => config('iris.webhook_secret'),
                'debug' => config('iris.debug', false),
            ];

            // Auto-detect user_id from authenticated user
            if ($app->bound('auth') && $app['auth']->check()) {
                $config['user_id'] = $app['auth']->id();
            }

            return new IRIS($config);
        });

        // Alias for convenience
        $this->app->alias(IRIS::class, 'iris');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('iris.php'),
        ], 'iris-config');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [IRIS::class, 'iris'];
    }
}
