<?php

declare(strict_types=1);

namespace Laravilt\AI;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Laravilt\AI\Tools\Tool;

/**
 * AIAgent configuration class for resources.
 * Similar to Form/Table/ApiResource configuration pattern.
 */
class AIAgent
{
    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $systemPrompt = null;

    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var array<int, string> */
    protected array $searchable = [];

    /** @var array<int, Tool> */
    protected array $tools = [];

    /** @var array<string, mixed> */
    protected array $metadata = [];

    protected bool $canCreate = true;

    protected bool $canUpdate = true;

    protected bool $canDelete = true;

    protected bool $canQuery = true;

    protected ?Closure $customHandler = null;

    public static function make(): static
    {
        return new static;
    }

    public function name(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function systemPrompt(string $prompt): static
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param  array<int, string>  $columns
     */
    public function searchable(array $columns): static
    {
        $this->searchable = $columns;

        return $this;
    }

    /**
     * @param  array<int, Tool>  $tools
     */
    public function tools(array $tools): static
    {
        $this->tools = $tools;

        return $this;
    }

    public function addTool(Tool $tool): static
    {
        $this->tools[] = $tool;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function canCreate(bool $condition = true): static
    {
        $this->canCreate = $condition;

        return $this;
    }

    public function canUpdate(bool $condition = true): static
    {
        $this->canUpdate = $condition;

        return $this;
    }

    public function canDelete(bool $condition = true): static
    {
        $this->canDelete = $condition;

        return $this;
    }

    public function canQuery(bool $condition = true): static
    {
        $this->canQuery = $condition;

        return $this;
    }

    public function handler(Closure $handler): static
    {
        $this->customHandler = $handler;

        return $this;
    }

    // Getters

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    /**
     * @return class-string<Model>|null
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * @return array<int, string>
     */
    public function getSearchable(): array
    {
        return $this->searchable;
    }

    /**
     * @return array<int, Tool>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCanCreate(): bool
    {
        return $this->canCreate;
    }

    public function getCanUpdate(): bool
    {
        return $this->canUpdate;
    }

    public function getCanDelete(): bool
    {
        return $this->canDelete;
    }

    public function getCanQuery(): bool
    {
        return $this->canQuery;
    }

    public function getHandler(): ?Closure
    {
        return $this->customHandler;
    }

    /**
     * Convert to ResourceAgent instance for execution
     */
    public function toResourceAgent(): ResourceAgent
    {
        $agent = ResourceAgent::make($this->name ?? 'resource_agent');

        if ($this->description) {
            $agent->description($this->description);
        }

        if ($this->systemPrompt) {
            $agent->instructions($this->systemPrompt);
        }

        if ($this->model) {
            $agent->model($this->model);
        }

        if (! empty($this->tools)) {
            $agent->tools($this->tools);
        }

        if (! empty($this->metadata)) {
            $agent->metadata($this->metadata);
        }

        return $agent;
    }

    /**
     * Export configuration for frontend
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'model' => $this->model,
            'searchable' => $this->searchable,
            'capabilities' => [
                'create' => $this->canCreate,
                'update' => $this->canUpdate,
                'delete' => $this->canDelete,
                'query' => $this->canQuery,
            ],
            'tools' => array_map(fn (Tool $tool) => $tool->toArray(), $this->tools),
            'metadata' => $this->metadata,
        ];
    }
}
