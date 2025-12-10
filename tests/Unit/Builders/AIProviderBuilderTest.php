<?php

use Laravilt\AI\Builders\AIProviderBuilder;
use Laravilt\AI\Providers\OpenAIProvider;
use Laravilt\AI\Providers\AnthropicProvider;
use Laravilt\AI\Enums\OpenAIModel;

describe('AIProviderBuilder', function () {
    beforeEach(function () {
        $this->builder = new AIProviderBuilder;
    });

    it('can add a provider', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        });

        expect($this->builder->getProviders())->toHaveCount(1);
    });

    it('can add multiple providers', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        });

        $this->builder->provider(AnthropicProvider::class, function (AnthropicProvider $provider) {
            $provider->apiKey('another-key');
        });

        expect($this->builder->getProviders())->toHaveCount(2);
    });

    it('can set default provider', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        })->default('openai');

        expect($this->builder->getDefaultProviderName())->toBe('openai');
    });

    it('can check if has configured providers', function () {
        expect($this->builder->hasConfiguredProviders())->toBeFalse();

        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        });

        expect($this->builder->hasConfiguredProviders())->toBeTrue();
    });

    it('can convert to array', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        })->default('openai');

        $array = $this->builder->toArray();

        expect($array)->toHaveKeys(['providers', 'defaultProvider']);
        expect($array['defaultProvider'])->toBe('openai');
    });

    it('can configure provider with enum model', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider
                ->apiKey('test-key')
                ->model(OpenAIModel::GPT_4O);
        });

        expect($this->builder->getProviders())->toHaveCount(1);
    });

    it('can get provider by name', function () {
        $this->builder->provider(OpenAIProvider::class, function (OpenAIProvider $provider) {
            $provider->apiKey('test-key');
        });

        $provider = $this->builder->getProvider('openai');

        expect($provider)->toBeInstanceOf(OpenAIProvider::class);
    });

    it('has shorthand methods for common providers', function () {
        $this->builder
            ->openai(fn ($p) => $p->apiKey('test'))
            ->anthropic(fn ($p) => $p->apiKey('test'))
            ->gemini(fn ($p) => $p->apiKey('test'))
            ->deepseek(fn ($p) => $p->apiKey('test'))
            ->perplexity(fn ($p) => $p->apiKey('test'));

        expect($this->builder->getProviders())->toHaveCount(5);
    });
});
