<?php

use Laravilt\AI\AIManager;
use Laravilt\AI\Providers\OpenAIProvider;

describe('AIManager', function () {
    beforeEach(function () {
        $this->manager = new AIManager;
    });

    it('can add a provider', function () {
        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $this->manager->addProvider($provider);

        expect($this->manager->hasProvider('openai'))->toBeTrue();
    });

    it('can set default provider', function () {
        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $this->manager->addProvider($provider);
        $this->manager->setDefaultProvider('openai');

        expect($this->manager->provider())->toBe($provider);
    });

    it('can check if provider exists', function () {
        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $this->manager->addProvider($provider);

        expect($this->manager->hasProvider('openai'))->toBeTrue();
        expect($this->manager->hasProvider('nonexistent'))->toBeFalse();
    });

    it('can get all providers', function () {
        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $this->manager->addProvider($provider);

        expect($this->manager->getProviders())->toHaveCount(1);
    });

    it('can convert to array for frontend', function () {
        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $this->manager->addProvider($provider);
        $this->manager->setDefaultProvider('openai');

        $array = $this->manager->toArray();

        expect($array)->toHaveKeys(['configured', 'default', 'providers']);
        expect($array['default'])->toBe('openai');
        expect($array['providers'])->toHaveKey('openai');
    });

    it('reports configured status', function () {
        // Clear existing config to test unconfigured state
        config()->set('laravilt-ai.providers.openai.api_key', null);
        $freshManager = new AIManager;

        expect($freshManager->isConfigured())->toBeFalse();

        $provider = new OpenAIProvider;
        $provider->apiKey('test-key');
        $freshManager->addProvider($provider);

        expect($freshManager->isConfigured())->toBeTrue();
    });
});
