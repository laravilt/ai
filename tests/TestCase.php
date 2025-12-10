<?php

namespace Laravilt\AI\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');        // Additional setup if needed

    }

    protected function getPackageProviders($app): array
    {
        return [
            \Laravilt\AI\AIServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // Set up AI config for testing
        config()->set('laravilt-ai.providers.openai', [
            'api_key' => 'test-key',
            'model' => 'gpt-4o-mini',
        ]);
    }
}
