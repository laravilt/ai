<?php

namespace Laravilt\Ai;

use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/laravilt-ai.php',
            'laravilt-ai'
        );

        // Register any services, bindings, or singletons here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {

        // Load translations
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'ai');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load web routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        // Load API routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');


        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/laravilt-ai.php' => config_path('laravilt-ai.php'),
            ], 'laravilt-ai-config');

            // Publish assets
            $this->publishes([
                __DIR__ . '/../dist' => public_path('vendor/laravilt/ai'),
            ], 'laravilt-ai-assets');

            // Publish migrations
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'ai-migrations');


            // Register commands
            $this->commands([
                Commands\InstallAiCommand::class,
            ]);
        }
    }
}
