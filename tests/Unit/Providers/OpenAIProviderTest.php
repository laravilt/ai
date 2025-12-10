<?php

use Laravilt\AI\Enums\OpenAIModel;
use Laravilt\AI\Providers\OpenAIProvider;

describe('OpenAIProvider', function () {
    beforeEach(function () {
        $this->provider = new OpenAIProvider;
    });

    it('has correct name', function () {
        expect($this->provider->getName())->toBe('openai');
    });

    it('has correct label', function () {
        expect($this->provider->getLabel())->toBe('OpenAI');
    });

    it('can set api key', function () {
        $this->provider->apiKey('test-api-key');

        expect($this->provider->isConfigured())->toBeTrue();
    });

    it('is not configured without api key', function () {
        // Create a fresh provider without config
        config()->set('laravilt-ai.providers.openai.api_key', null);
        $provider = new OpenAIProvider;

        expect($provider->isConfigured())->toBeFalse();
    });

    it('can set model using enum (fluent api)', function () {
        $result = $this->provider->model(OpenAIModel::GPT_4O);

        expect($result)->toBeInstanceOf(OpenAIProvider::class);
    });

    it('can set model using string (fluent api)', function () {
        $result = $this->provider->model('gpt-4-turbo');

        expect($result)->toBeInstanceOf(OpenAIProvider::class);
    });

    it('has default model', function () {
        expect($this->provider->getDefaultModel())->toBe('gpt-4o-mini');
    });

    it('can get available models', function () {
        $models = $this->provider->getModels();

        expect($models)->toBeArray();
        expect($models)->toHaveKey('gpt-4o');
        expect($models)->toHaveKey('gpt-4o-mini');
    });

    it('can set temperature (fluent api)', function () {
        $result = $this->provider->temperature(0.5);

        expect($result)->toBeInstanceOf(OpenAIProvider::class);
    });

    it('can set max tokens (fluent api)', function () {
        $result = $this->provider->maxTokens(2000);

        expect($result)->toBeInstanceOf(OpenAIProvider::class);
    });

    it('can convert to array', function () {
        $this->provider->apiKey('test-key');

        $array = $this->provider->toArray();

        expect($array)->toHaveKeys(['name', 'label', 'models', 'defaultModel', 'configured']);
        expect($array['name'])->toBe('openai');
        expect($array['configured'])->toBeTrue();
    });

    it('loads config from laravilt-ai config file', function () {
        config()->set('laravilt-ai.providers.openai', [
            'api_key' => 'config-api-key',
            'model' => 'gpt-4-turbo',
            'temperature' => 0.7,
        ]);

        $provider = new OpenAIProvider;

        expect($provider->isConfigured())->toBeTrue();
    });

    it('can be enabled and disabled', function () {
        expect($this->provider->isEnabled())->toBeTrue();

        $this->provider->disabled();
        expect($this->provider->isEnabled())->toBeFalse();

        $this->provider->enabled();
        expect($this->provider->isEnabled())->toBeTrue();
    });
});
