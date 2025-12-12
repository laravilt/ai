<?php

declare(strict_types=1);

namespace Laravilt\AI;

/**
 * AIColumn configuration for resource AI searchable columns.
 * Defines which columns are searchable by AI and their configuration.
 */
class AIColumn
{
    protected string $name;

    protected ?string $label = null;

    protected ?string $description = null;

    protected bool $searchable = true;

    protected bool $filterable = false;

    protected bool $sortable = false;

    protected ?string $type = null;

    /** @var array<string, mixed> */
    protected array $options = [];

    protected ?string $relationship = null;

    protected ?string $relationshipTitleColumn = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function searchable(bool $condition = true): static
    {
        $this->searchable = $condition;

        return $this;
    }

    public function filterable(bool $condition = true): static
    {
        $this->filterable = $condition;

        return $this;
    }

    public function sortable(bool $condition = true): static
    {
        $this->sortable = $condition;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function relationship(string $relationship, string $titleColumn = 'name'): static
    {
        $this->relationship = $relationship;
        $this->relationshipTitleColumn = $titleColumn;

        return $this;
    }

    // Getters

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label ?? str($this->name)->title()->replace('_', ' ')->toString();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function getRelationshipTitleColumn(): ?string
    {
        return $this->relationshipTitleColumn;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->getLabel(),
            'description' => $this->description,
            'searchable' => $this->searchable,
            'filterable' => $this->filterable,
            'sortable' => $this->sortable,
            'type' => $this->type,
            'options' => $this->options,
            'relationship' => $this->relationship,
            'relationshipTitleColumn' => $this->relationshipTitleColumn,
        ];
    }
}
