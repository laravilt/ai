<?php

declare(strict_types=1);

namespace Laravilt\AI;

use Illuminate\Support\ServiceProvider;
use Laravilt\AI\Commands\InstallAiCommand;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravilt-ai.php', 'laravilt-ai');

        $this->app->singleton(AIManager::class, function () {
            return new AIManager;
        });

        $this->app->singleton(GlobalSearch::class, function () {
            return new GlobalSearch;
        });

        $this->app->alias(AIManager::class, 'laravilt-ai');
        $this->app->alias(GlobalSearch::class, 'laravilt-global-search');
    }

    public function boot(): void
    {
        // AI routes are registered by the Panel via HasAI trait
        // This ensures proper middleware (localization, panel settings, etc.)
        // $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravilt-ai');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravilt-ai.php' => config_path('laravilt-ai.php'),
            ], 'laravilt-ai-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravilt-ai-migrations');

            $this->publishes([
                __DIR__.'/../resources/lang' => lang_path('vendor/laravilt-ai'),
            ], 'laravilt-ai-lang');

            $this->commands([
                InstallAiCommand::class,
            ]);
        }
    }
}
