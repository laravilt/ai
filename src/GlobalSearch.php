<?php

declare(strict_types=1);

namespace Laravilt\AI;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Laravel\Scout\Searchable;

class GlobalSearch
{
    /** @var array<int, array{resource: string, model: class-string<Model>, searchable: array<int, string>, label: string, icon: ?string, url: string}> */
    protected array $resources = [];

    protected ?AIManager $aiManager = null;

    protected int $limit = 5;

    protected bool $useAI = true;

    public function __construct()
    {
        $this->aiManager = App::make(AIManager::class);
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $searchable
     */
    public function registerResource(
        string $resource,
        string $model,
        array $searchable,
        string $label,
        ?string $icon = null,
        string $url = ''
    ): static {
        $this->resources[] = [
            'resource' => $resource,
            'model' => $model,
            'searchable' => $searchable,
            'label' => $label,
            'icon' => $icon,
            'url' => $url,
        ];

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    public function useAI(bool $useAI = true): static
    {
        $this->useAI = $useAI;

        return $this;
    }

    /**
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    public function search(string $query): Collection
    {
        if (empty(trim($query))) {
            return collect();
        }

        // Check if AI is configured and should be used
        if ($this->useAI && $this->aiManager?->isConfigured()) {
            return $this->searchWithAI($query);
        }

        return $this->searchDirect($query);
    }

    /**
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchDirect(string $query): Collection
    {
        $results = collect();

        foreach ($this->resources as $resource) {
            /** @var Model $modelInstance */
            $modelInstance = new $resource['model'];

            // Check if model uses Scout
            if ($this->modelUsesScout($modelInstance)) {
                $items = $this->searchWithScout($modelInstance, $query, $resource['searchable']);
            } else {
                $items = $this->searchWithDatabase($modelInstance, $query, $resource['searchable']);
            }

            if ($items->isNotEmpty()) {
                $results->push([
                    'resource' => $resource['resource'],
                    'label' => $resource['label'],
                    'icon' => $resource['icon'],
                    'url' => $resource['url'],
                    'results' => $items->map(function ($item) use ($resource) {
                        return $this->formatResult($item, $resource);
                    }),
                ]);
            }
        }

        return $results;
    }

    /**
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchWithAI(string $query): Collection
    {
        // For now, we'll use AI to understand the query and then search
        // In a more advanced implementation, AI could directly query data

        try {
            $provider = $this->aiManager->provider();

            // Build context about available resources
            $resourceContext = collect($this->resources)->map(function ($r) {
                return "- {$r['label']}: searchable fields are ".implode(', ', $r['searchable']);
            })->implode("\n");

            $response = $provider->chat([
                [
                    'role' => 'system',
                    'content' => "You are a search assistant. Given a user query, extract the search terms and identify which resource they might be looking for. Available resources:\n{$resourceContext}\n\nRespond with JSON: {\"terms\": [\"search terms\"], \"resource\": \"resource_name or null for all\"}",
                ],
                [
                    'role' => 'user',
                    'content' => $query,
                ],
            ], ['max_tokens' => 100]);

            $parsed = json_decode($response['content'], true);
            $searchTerms = $parsed['terms'] ?? [$query];
            $targetResource = $parsed['resource'] ?? null;

            // Search with extracted terms
            return $this->searchDirectWithTerms(implode(' ', $searchTerms), $targetResource);
        } catch (\Exception $e) {
            // Fallback to direct search on error
            return $this->searchDirect($query);
        }
    }

    /**
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchDirectWithTerms(string $query, ?string $targetResource = null): Collection
    {
        $results = collect();

        foreach ($this->resources as $resource) {
            // Skip if targeting specific resource and this isn't it
            if ($targetResource && $resource['resource'] !== $targetResource) {
                continue;
            }

            /** @var Model $modelInstance */
            $modelInstance = new $resource['model'];

            if ($this->modelUsesScout($modelInstance)) {
                $items = $this->searchWithScout($modelInstance, $query, $resource['searchable']);
            } else {
                $items = $this->searchWithDatabase($modelInstance, $query, $resource['searchable']);
            }

            if ($items->isNotEmpty()) {
                $results->push([
                    'resource' => $resource['resource'],
                    'label' => $resource['label'],
                    'icon' => $resource['icon'],
                    'url' => $resource['url'],
                    'results' => $items->map(function ($item) use ($resource) {
                        return $this->formatResult($item, $resource);
                    }),
                ]);
            }
        }

        return $results;
    }

    protected function modelUsesScout(Model $model): bool
    {
        return in_array(Searchable::class, class_uses_recursive($model));
    }

    /**
     * @param  array<int, string>  $searchable
     */
    protected function searchWithScout(Model $model, string $query, array $searchable): Collection
    {
        /** @phpstan-ignore-next-line */
        return $model::search($query)->take($this->limit)->get();
    }

    /**
     * @param  array<int, string>  $searchable
     */
    protected function searchWithDatabase(Model $model, string $query, array $searchable): Collection
    {
        return $model->newQuery()
            ->where(function (Builder $builder) use ($query, $searchable) {
                foreach ($searchable as $column) {
                    $builder->orWhere($column, 'LIKE', "%{$query}%");
                }
            })
            ->limit($this->limit)
            ->get();
    }

    /**
     * @param  array{resource: string, model: class-string<Model>, searchable: array<int, string>, label: string, icon: ?string, url: string}  $resource
     * @return array{id: mixed, title: string, subtitle: ?string, url: string}
     */
    protected function formatResult(Model $item, array $resource): array
    {
        $titleColumn = $resource['searchable'][0] ?? 'id';
        $subtitleColumn = $resource['searchable'][1] ?? null;

        return [
            'id' => $item->getKey(),
            'title' => (string) ($item->{$titleColumn} ?? $item->getKey()),
            'subtitle' => $subtitleColumn ? (string) ($item->{$subtitleColumn} ?? null) : null,
            'url' => str_replace('{id}', (string) $item->getKey(), $resource['url']),
        ];
    }

    /**
     * @return array<int, array{resource: string, model: class-string<Model>, searchable: array<int, string>, label: string, icon: ?string, url: string}>
     */
    public function getResources(): array
    {
        return $this->resources;
    }
}
