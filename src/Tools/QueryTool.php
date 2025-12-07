<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Illuminate\Database\Eloquent\Model;

class QueryTool extends Tool
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    protected int $limit = 10;

    /** @var array<int, string> */
    protected array $searchableColumns = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        $this->addParameter('search', 'string', 'Search query', false);
        $this->addParameter('limit', 'integer', 'Maximum number of results', false);
        $this->addParameter('orderBy', 'string', 'Column to sort by', false);
        $this->addParameter('orderDirection', 'string', 'Sort direction (asc or desc)', false);

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchableColumns(array $columns): static
    {
        $this->searchableColumns = $columns;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<int, array<string, mixed>>
     */
    protected function handle(array $arguments): array
    {
        if (! $this->model) {
            return [];
        }

        $query = $this->model::query();

        // Apply search
        if (! empty($arguments['search']) && ! empty($this->searchableColumns)) {
            $search = $arguments['search'];
            $query->where(function ($q) use ($search) {
                foreach ($this->searchableColumns as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Apply ordering
        if (! empty($arguments['orderBy'])) {
            $direction = $arguments['orderDirection'] ?? 'asc';
            $query->orderBy($arguments['orderBy'], $direction);
        }

        // Apply limit
        $limit = $arguments['limit'] ?? $this->limit;
        $query->limit($limit);

        return $query->get()->toArray();
    }
}
