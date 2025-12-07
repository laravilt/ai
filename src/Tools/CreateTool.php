<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Illuminate\Database\Eloquent\Model;

class CreateTool extends Tool
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    /** @var array<int, string> */
    protected array $fillable = [];

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        // Get fillable fields from model
        $instance = new $model;
        $this->fillable = $instance->getFillable();

        // Add parameters for each fillable field
        foreach ($this->fillable as $field) {
            $this->addParameter($field, 'string', "The {$field} value", false);
        }

        return $this;
    }

    /**
     * @param  array<int, string>  $fields
     */
    public function fillable(array $fields): static
    {
        $this->fillable = $fields;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function handle(array $arguments): array
    {
        if (! $this->model) {
            return ['error' => 'No model specified'];
        }

        // Filter to only fillable fields
        $data = array_intersect_key($arguments, array_flip($this->fillable));

        $model = $this->model::create($data);

        return $model->toArray();
    }
}
