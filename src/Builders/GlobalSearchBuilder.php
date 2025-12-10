<?php

declare(strict_types=1);

namespace Laravilt\AI\Builders;

use Closure;

class GlobalSearchBuilder
{
    /**
     * Is global search enabled.
     */
    protected bool $enabled = true;

    /**
     * Use AI for search query understanding.
     */
    protected bool $useAI = false;

    /**
     * Maximum results per resource.
     */
    protected int $limit = 5;

    /**
     * Maximum total results.
     */
    protected int $maxResults = 25;

    /**
     * Debounce delay in milliseconds.
     */
    protected int $debounce = 300;

    /**
     * Keyboard shortcut to open search.
     */
    protected string $shortcut = 'cmd+k';

    /**
     * Search endpoint URL.
     */
    protected ?string $endpoint = null;

    /**
     * Custom search handler.
     */
    protected ?Closure $searchHandler = null;

    /**
     * Resources to exclude from search.
     */
    protected array $excludedResources = [];

    /**
     * Enable global search.
     */
    public function enabled(bool $enabled = true): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * Disable global search.
     */
    public function disabled(): static
    {
        $this->enabled = false;

        return $this;
    }

    /**
     * Enable AI-powered search understanding.
     */
    public function withAI(bool $useAI = true): static
    {
        $this->useAI = $useAI;

        return $this;
    }

    /**
     * Disable AI-powered search.
     */
    public function withoutAI(): static
    {
        $this->useAI = false;

        return $this;
    }

    /**
     * Set maximum results per resource.
     */
    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * Set maximum total results.
     */
    public function maxResults(int $maxResults): static
    {
        $this->maxResults = $maxResults;

        return $this;
    }

    /**
     * Set debounce delay.
     */
    public function debounce(int $milliseconds): static
    {
        $this->debounce = $milliseconds;

        return $this;
    }

    /**
     * Set keyboard shortcut.
     */
    public function shortcut(string $shortcut): static
    {
        $this->shortcut = $shortcut;

        return $this;
    }

    /**
     * Set custom endpoint URL.
     */
    public function endpoint(string $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * Set custom search handler.
     */
    public function using(Closure $handler): static
    {
        $this->searchHandler = $handler;

        return $this;
    }

    /**
     * Exclude resources from search.
     *
     * @param  array<class-string>  $resources
     */
    public function exclude(array $resources): static
    {
        $this->excludedResources = $resources;

        return $this;
    }

    /**
     * Check if global search is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Check if AI is enabled for search.
     */
    public function usesAI(): bool
    {
        return $this->useAI;
    }

    /**
     * Get limit per resource.
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Get maximum total results.
     */
    public function getMaxResults(): int
    {
        return $this->maxResults;
    }

    /**
     * Get debounce delay.
     */
    public function getDebounce(): int
    {
        return $this->debounce;
    }

    /**
     * Get keyboard shortcut.
     */
    public function getShortcut(): string
    {
        return $this->shortcut;
    }

    /**
     * Get custom endpoint.
     */
    public function getEndpoint(): ?string
    {
        return $this->endpoint;
    }

    /**
     * Get custom search handler.
     */
    public function getSearchHandler(): ?Closure
    {
        return $this->searchHandler;
    }

    /**
     * Get excluded resources.
     *
     * @return array<class-string>
     */
    public function getExcludedResources(): array
    {
        return $this->excludedResources;
    }

    /**
     * Convert to array for frontend.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'useAI' => $this->useAI,
            'limit' => $this->limit,
            'maxResults' => $this->maxResults,
            'debounce' => $this->debounce,
            'shortcut' => $this->shortcut,
            'endpoint' => $this->endpoint,
        ];
    }
}
