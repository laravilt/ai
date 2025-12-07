<?php

declare(strict_types=1);

namespace Laravilt\AI;

use Illuminate\Database\Eloquent\Model;
use Laravilt\AI\Tools\CreateTool;
use Laravilt\AI\Tools\DeleteTool;
use Laravilt\AI\Tools\QueryTool;
use Laravilt\AI\Tools\UpdateTool;

class ResourceAgent extends Agent
{
    /** @var class-string<Model> */
    protected string $model;

    protected bool $autoGenerateTools = true;

    /**
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        if ($this->autoGenerateTools) {
            $this->generateTools();
        }

        return $this;
    }

    public function autoGenerateTools(bool $condition = true): static
    {
        $this->autoGenerateTools = $condition;

        return $this;
    }

    protected function generateTools(): void
    {
        $modelName = class_basename($this->model);
        $resourceName = str($modelName)->plural()->lower()->toString();

        // Query tool
        $this->addTool(
            QueryTool::make("query_{$resourceName}")
                ->description("Query and search {$resourceName}")
                ->model($this->model)
        );

        // Create tool
        $this->addTool(
            CreateTool::make("create_{$modelName}")
                ->description("Create a new {$modelName}")
                ->model($this->model)
        );

        // Update tool
        $this->addTool(
            UpdateTool::make("update_{$modelName}")
                ->description("Update an existing {$modelName}")
                ->model($this->model)
        );

        // Delete tool
        $this->addTool(
            DeleteTool::make("delete_{$modelName}")
                ->description("Delete a {$modelName}")
                ->model($this->model)
        );
    }

    /**
     * @return class-string<Model>
     */
    public function getModel(): string
    {
        return $this->model;
    }
}
