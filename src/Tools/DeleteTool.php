<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Illuminate\Database\Eloquent\Model;

class DeleteTool extends Tool
{
    /** @var class-string<Model>|null */
    protected ?string $model = null;

    protected bool $softDelete = true;

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        $this->addParameter('id', 'integer', 'The ID of the record to delete', true);

        return $this;
    }

    public function softDelete(bool $condition = true): static
    {
        $this->softDelete = $condition;

        return $this;
    }

    public function forceDelete(): static
    {
        $this->softDelete = false;

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

        if (! $this->softDelete) {
            $model->forceDelete();
        } else {
            $model->delete();
        }

        return ['success' => true, 'message' => 'Record deleted successfully'];
    }
}
