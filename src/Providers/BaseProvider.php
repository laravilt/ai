<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

use BackedEnum;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravilt\AI\Contracts\AIProvider;

abstract class BaseProvider implements AIProvider
{
    protected ?string $apiKey = null;

    protected ?string $model = null;

    protected ?string $baseUrl = null;

    protected float $temperature = 0.7;

    protected int $maxTokens = 2048;

    protected bool $enabled = true;

    public function __construct()
    {
        $this->loadFromConfig();
    }

    /**
     * Load configuration from laravilt-ai config file.
     */
    protected function loadFromConfig(): void
    {
        $providerName = $this->getName();
        $config = config("laravilt-ai.providers.{$providerName}", []);

        if (! empty($config['api_key'])) {
            $this->apiKey = $config['api_key'];
        }

        if (! empty($config['model'])) {
            $this->model = $config['model'];
        }

        if (! empty($config['base_url'])) {
            $this->baseUrl = $config['base_url'];
        }

        if (isset($config['temperature'])) {
            $this->temperature = (float) $config['temperature'];
        }

        if (isset($config['max_tokens'])) {
            $this->maxTokens = (int) $config['max_tokens'];
        }
    }

    public function apiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function model(string|BackedEnum $model): static
    {
        $this->model = $model instanceof BackedEnum ? $model->value : $model;

        return $this;
    }

    public function enabled(bool $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function disabled(): static
    {
        $this->enabled = false;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function baseUrl(string $baseUrl): static
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function temperature(float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function maxTokens(int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function isConfigured(): bool
    {
        return $this->enabled && ! empty($this->apiKey);
    }

    protected function getApiKey(): string
    {
        return $this->apiKey ?? '';
    }

    protected function getModel(): string
    {
        return $this->model ?? $this->getDefaultModel();
    }

    protected function getTemperature(): float
    {
        return $this->temperature;
    }

    protected function getMaxTokens(): int
    {
        return $this->maxTokens;
    }

    abstract protected function getBaseUrl(): string;

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl ?? $this->getBaseUrl())
            ->withHeaders($this->getHeaders())
            ->timeout(120);
    }

    /**
     * @return array<string, string>
     */
    abstract protected function getHeaders(): array;

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'label' => $this->getLabel(),
            'models' => $this->getModels(),
            'defaultModel' => $this->getDefaultModel(),
            'configured' => $this->isConfigured(),
        ];
    }

    /**
     * Default implementation using streamChat generator
     */
    public function streamChatRealtime(array $messages, callable $callback, array $options = []): void
    {
        foreach ($this->streamChat($messages, $options) as $chunk) {
            $callback($chunk);
        }
    }
}
