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
     * Check if query is a natural language question.
     */
    protected function isQuestion(string $query): bool
    {
        $questionPatterns = [
            '/^(what|which|who|whom|whose|where|when|why|how|can|could|would|should|is|are|was|were|do|does|did|have|has|had|find|show|get|list|give)/i',
            '/\?$/',
            '/(highest|lowest|most|least|top|bottom|best|worst|maximum|minimum|max|min|biggest|smallest|largest|first|last|recent|latest|oldest|newest)/i',
        ];

        foreach ($questionPatterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchWithAI(string $query): Collection
    {
        try {
            $provider = $this->aiManager->provider();

            // Check if this is a natural language question that needs tool-based query
            if ($this->isQuestion($query)) {
                return $this->searchWithAITools($query, $provider);
            }

            // Otherwise, use AI to extract search terms
            return $this->searchWithAITermExtraction($query, $provider);
        } catch (\Exception $e) {
            // Fallback to direct search on error
            return $this->searchDirect($query);
        }
    }

    /**
     * Search using AI with tools for natural language questions.
     *
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchWithAITools(string $query, $provider): Collection
    {
        // Build tools for each resource
        $tools = [];
        $resourceMap = [];

        foreach ($this->resources as $resource) {
            $toolName = 'query_'.str_replace('-', '_', $resource['resource']);
            $resourceMap[$toolName] = $resource;

            // Get model columns for better context
            $modelInstance = new $resource['model'];
            $table = $modelInstance->getTable();

            $tools[] = [
                'name' => $toolName,
                'description' => "Query {$resource['label']} from the database. Table: {$table}. Searchable columns: ".implode(', ', $resource['searchable']),
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'search' => [
                            'type' => 'string',
                            'description' => 'Search term to filter results (searches in: '.implode(', ', $resource['searchable']).')',
                        ],
                        'orderBy' => [
                            'type' => 'string',
                            'description' => 'Column name to sort by (e.g., price, name, created_at)',
                        ],
                        'orderDirection' => [
                            'type' => 'string',
                            'enum' => ['asc', 'desc'],
                            'description' => 'Sort direction: asc for ascending, desc for descending',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return (default: '.$this->limit.')',
                        ],
                    ],
                    'required' => [],
                ],
            ];
        }

        $systemPrompt = "You are a database query assistant. Given a user's question, determine which tool to use and with what parameters to answer their question. Always use tools to fetch data - never make up answers.

For questions about 'highest', 'most expensive', 'maximum', etc: use orderBy with desc direction and limit 1.
For questions about 'lowest', 'cheapest', 'minimum', etc: use orderBy with asc direction and limit 1.
For questions about 'top N' or 'best N': use appropriate ordering with limit N.
For search questions: use the search parameter with relevant terms.";

        $response = $provider->chatWithTools([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $query],
        ], $tools, ['max_tokens' => 500]);

        $results = collect();

        if (! empty($response['tool_calls'])) {
            foreach ($response['tool_calls'] as $toolCall) {
                $toolName = $toolCall['name'];
                $arguments = $toolCall['arguments'];

                if (isset($resourceMap[$toolName])) {
                    $resource = $resourceMap[$toolName];
                    $items = $this->executeQueryTool($resource, $arguments);

                    if ($items->isNotEmpty()) {
                        $results->push([
                            'resource' => $resource['resource'],
                            'label' => $resource['label'],
                            'icon' => $resource['icon'],
                            'url' => $resource['url'],
                            'aiAnswer' => $response['content'] ?? null,
                            'results' => $items->map(function ($item) use ($resource) {
                                return $this->formatResult($item, $resource);
                            }),
                        ]);
                    }
                }
            }
        }

        // If no tool calls, fall back to term extraction
        if ($results->isEmpty()) {
            return $this->searchWithAITermExtraction($query, $provider);
        }

        return $results;
    }

    /**
     * Execute a query tool with the given arguments.
     *
     * @param  array{resource: string, model: class-string<Model>, searchable: array<int, string>, label: string, icon: ?string, url: string}  $resource
     * @param  array<string, mixed>  $arguments
     */
    protected function executeQueryTool(array $resource, array $arguments): Collection
    {
        $query = $resource['model']::query();

        // Apply search
        if (! empty($arguments['search']) && ! empty($resource['searchable'])) {
            $search = $arguments['search'];
            $query->where(function ($q) use ($search, $resource) {
                foreach ($resource['searchable'] as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Apply ordering
        if (! empty($arguments['orderBy'])) {
            $direction = $arguments['orderDirection'] ?? 'asc';
            if (! in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }
            $query->orderBy($arguments['orderBy'], $direction);
        }

        // Apply limit
        $limit = $arguments['limit'] ?? $this->limit;
        $query->limit($limit);

        return $query->get();
    }

    /**
     * Search using AI to extract search terms.
     *
     * @return Collection<int, array{resource: string, label: string, icon: ?string, results: Collection}>
     */
    protected function searchWithAITermExtraction(string $query, $provider): Collection
    {
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
