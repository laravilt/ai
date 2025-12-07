<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Illuminate\Database\Eloquent\Model;

class UpdateTool extends Tool
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

        // Add ID parameter
        $this->addParameter('id', 'integer', 'The ID of the record to update', true);

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

        if (empty($arguments['id'])) {
            return ['error' => 'ID is required'];
        }

        $model = $this->model::query()->find($arguments['id']);

        if (! $model instanceof Model) {
            return ['error' => 'Record not found'];
        }

        // Filter to only fillable fields
        $data = array_intersect_key($arguments, array_flip($this->fillable));

        $model->update($data);

        $freshModel = $model->fresh();

        return $freshModel ? $freshModel->toArray() : [];
    }
}
