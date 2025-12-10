<?php

declare(strict_types=1);

namespace Laravilt\AI;

use InvalidArgumentException;
use Laravilt\AI\Contracts\AIProvider;
use Laravilt\AI\Providers\AnthropicProvider;
use Laravilt\AI\Providers\DeepSeekProvider;
use Laravilt\AI\Providers\GeminiProvider;
use Laravilt\AI\Providers\OpenAIProvider;

class AIManager
{
    /** @var array<string, AIProvider> */
    protected array $providers = [];

    protected ?string $defaultProvider = null;

    /** @var array<string, class-string<AIProvider>> */
    protected array $availableProviders = [
        'openai' => OpenAIProvider::class,
        'anthropic' => AnthropicProvider::class,
        'gemini' => GeminiProvider::class,
        'deepseek' => DeepSeekProvider::class,
    ];

    public function __construct()
    {
        $this->loadFromConfig();
    }

    protected function loadFromConfig(): void
    {
        $config = config('laravilt-ai', []);

        // Load default provider
        $this->defaultProvider = $config['default'] ?? null;

        // Load provider configurations
        foreach ($config['providers'] ?? [] as $name => $providerConfig) {
            if (empty($providerConfig['api_key'])) {
                continue;
            }

            $provider = $this->createProvider($name, $providerConfig);
            if ($provider) {
                $this->providers[$name] = $provider;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createProvider(string $name, array $config): ?AIProvider
    {
        $providerClass = $this->availableProviders[$name] ?? null;

        if (! $providerClass) {
            return null;
        }

        $provider = new $providerClass;

        if (isset($config['api_key'])) {
            $provider->apiKey($config['api_key']);
        }

        if (isset($config['model'])) {
            $provider->model($config['model']);
        }

        if (isset($config['base_url'])) {
            $provider->baseUrl($config['base_url']);
        }

        if (isset($config['temperature'])) {
            $provider->temperature((float) $config['temperature']);
        }

        if (isset($config['max_tokens'])) {
            $provider->maxTokens((int) $config['max_tokens']);
        }

        return $provider;
    }

    public function provider(?string $name = null): AIProvider
    {
        $name = $name ?? $this->defaultProvider ?? array_key_first($this->providers);

        if (! $name || ! isset($this->providers[$name])) {
            throw new InvalidArgumentException("AI provider [{$name}] is not configured.");
        }

        return $this->providers[$name];
    }

    public function hasProvider(?string $name = null): bool
    {
        if ($name === null) {
            return count($this->providers) > 0;
        }

        return isset($this->providers[$name]);
    }

    /**
     * @return array<string, AIProvider>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * @return array<string, class-string<AIProvider>>
     */
    public function getAvailableProviders(): array
    {
        return $this->availableProviders;
    }

    /**
     * Add a provider instance.
     */
    public function addProvider(AIProvider|string $provider, ?AIProvider $instance = null): static
    {
        if (is_string($provider) && $instance !== null) {
            // Legacy: addProvider('name', $provider)
            $this->providers[$provider] = $instance;
        } elseif ($provider instanceof AIProvider) {
            // New: addProvider($provider) - uses provider's getName()
            $this->providers[$provider->getName()] = $provider;
        }

        return $this;
    }

    /**
     * @param  class-string<AIProvider>  $class
     */
    public function registerProvider(string $name, string $class): static
    {
        $this->availableProviders[$name] = $class;

        return $this;
    }

    public function setDefault(string $name): static
    {
        $this->defaultProvider = $name;

        return $this;
    }

    /**
     * Set the default provider (alias for setDefault).
     */
    public function setDefaultProvider(string $name): static
    {
        return $this->setDefault($name);
    }

    public function isConfigured(): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->isConfigured()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get configuration array for frontend
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $providers = [];
        foreach ($this->providers as $name => $provider) {
            $providers[$name] = $provider->toArray();
        }

        return [
            'configured' => $this->isConfigured(),
            'default' => $this->defaultProvider,
            'providers' => $providers,
        ];
    }
}
