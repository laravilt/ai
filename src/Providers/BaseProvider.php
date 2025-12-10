<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

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

    public function apiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;

        return $this;
    }

    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
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
        return ! empty($this->apiKey);
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
}
