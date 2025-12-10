<?php

declare(strict_types=1);

namespace Laravilt\AI\Builders;

use Laravilt\AI\Contracts\AIProvider;

class AIProviderBuilder
{
    /**
     * @var array<AIProvider>
     */
    protected array $providers = [];

    /**
     * Default provider name.
     */
    protected ?string $defaultProvider = null;

    /**
     * Add an AI provider.
     *
     * @param  class-string<AIProvider>|AIProvider  $provider
     */
    public function provider(string|AIProvider $provider, ?callable $configure = null): static
    {
        if (is_string($provider)) {
            $instance = new $provider;
        } else {
            $instance = $provider;
        }

        if ($configure) {
            $configure($instance);
        }

        $this->providers[] = $instance;

        return $this;
    }

    /**
     * Add OpenAI provider.
     */
    public function openai(?callable $configure = null): static
    {
        return $this->provider(\Laravilt\AI\Providers\OpenAIProvider::class, $configure);
    }

    /**
     * Add Anthropic (Claude) provider.
     */
    public function anthropic(?callable $configure = null): static
    {
        return $this->provider(\Laravilt\AI\Providers\AnthropicProvider::class, $configure);
    }

    /**
     * Add Google Gemini provider.
     */
    public function gemini(?callable $configure = null): static
    {
        return $this->provider(\Laravilt\AI\Providers\GeminiProvider::class, $configure);
    }

    /**
     * Add DeepSeek provider.
     */
    public function deepseek(?callable $configure = null): static
    {
        return $this->provider(\Laravilt\AI\Providers\DeepSeekProvider::class, $configure);
    }

    /**
     * Set the default provider.
     */
    public function default(string $providerName): static
    {
        $this->defaultProvider = $providerName;

        return $this;
    }

    /**
     * Get all registered providers.
     *
     * @return array<AIProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get enabled/configured providers only.
     *
     * @return array<AIProvider>
     */
    public function getConfiguredProviders(): array
    {
        return array_values(array_filter(
            $this->providers,
            fn (AIProvider $provider) => $provider->isConfigured()
        ));
    }

    /**
     * Get provider by name.
     */
    public function getProvider(string $name): ?AIProvider
    {
        foreach ($this->providers as $provider) {
            if ($provider->getName() === $name) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Get the default provider.
     */
    public function getDefaultProvider(): ?AIProvider
    {
        if ($this->defaultProvider) {
            $provider = $this->getProvider($this->defaultProvider);
            if ($provider && $provider->isConfigured()) {
                return $provider;
            }
        }

        // Return first configured provider
        $configured = $this->getConfiguredProviders();

        return $configured[0] ?? null;
    }

    /**
     * Get default provider name.
     */
    public function getDefaultProviderName(): ?string
    {
        return $this->defaultProvider ?? $this->getDefaultProvider()?->getName();
    }

    /**
     * Get provider names.
     *
     * @return array<string>
     */
    public function getProviderNames(): array
    {
        return array_map(
            fn (AIProvider $provider) => $provider->getName(),
            $this->getConfiguredProviders()
        );
    }

    /**
     * Check if any provider is configured.
     */
    public function hasConfiguredProviders(): bool
    {
        return count($this->getConfiguredProviders()) > 0;
    }

    /**
     * Convert to array for frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'providers' => array_map(
                fn (AIProvider $provider) => $provider->toArray(),
                $this->getConfiguredProviders()
            ),
            'defaultProvider' => $this->getDefaultProviderName(),
        ];
    }
}
