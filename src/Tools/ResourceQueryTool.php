<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Laravilt\Panel\Facades\Panel;

class ResourceQueryTool
{
    /**
     * Get all available resources with their metadata.
     *
     * @return array<string, array{label: string, model: string, count: int, fields: array<string>}>
     */
    public static function getAvailableResources(): array
    {
        $panel = Panel::getCurrent();
        if (! $panel) {
            return [];
        }

        $resources = [];
        foreach ($panel->getResources() as $resourceClass) {
            $slug = $resourceClass::getSlug();
            $model = $resourceClass::getModel();

            // Get model count and fields
            $count = 0;
            $fields = [];

            try {
                if (class_exists($model)) {
                    $instance = new $model;
                    $table = $instance->getTable();

                    if (Schema::hasTable($table)) {
                        $count = $model::count();
                        $fields = Schema::getColumnListing($table);
                    }
                }
            } catch (\Exception $e) {
                // Ignore errors
            }

            $resources[$slug] = [
                'label' => $resourceClass::getPluralLabel(),
                'singular' => $resourceClass::getLabel(),
                'model' => $model,
                'count' => $count,
                'fields' => $fields,
            ];
        }

        return $resources;
    }

    /**
     * Query a specific resource.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function queryResource(array $params): array
    {
        $resourceSlug = $params['resource'] ?? null;
        $action = $params['action'] ?? 'list';
        $filters = $params['filters'] ?? [];
        $limit = min($params['limit'] ?? 10, 50); // Max 50 records
        $fields = $params['fields'] ?? [];
        $id = $params['id'] ?? null;

        if (! $resourceSlug) {
            return ['error' => 'Resource name is required'];
        }

        $panel = Panel::getCurrent();
        if (! $panel) {
            return ['error' => 'No panel context'];
        }

        // Find the resource
        $resourceClass = null;
        foreach ($panel->getResources() as $class) {
            if ($class::getSlug() === $resourceSlug) {
                $resourceClass = $class;
                break;
            }
        }

        if (! $resourceClass) {
            return ['error' => "Resource '{$resourceSlug}' not found"];
        }

        $model = $resourceClass::getModel();

        try {
            $query = $model::query();

            // Apply filters
            foreach ($filters as $field => $value) {
                if (is_array($value)) {
                    $query->whereIn($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }

            switch ($action) {
                case 'count':
                    return [
                        'resource' => $resourceSlug,
                        'action' => 'count',
                        'count' => $query->count(),
                    ];

                case 'get':
                    if (! $id) {
                        return ['error' => 'ID is required for get action'];
                    }
                    $record = $query->find($id);
                    if (! $record) {
                        return ['error' => "Record with ID {$id} not found"];
                    }

                    return [
                        'resource' => $resourceSlug,
                        'action' => 'get',
                        'data' => $fields ? $record->only($fields) : $record->toArray(),
                    ];

                case 'list':
                default:
                    $records = $query->limit($limit)->get();

                    return [
                        'resource' => $resourceSlug,
                        'action' => 'list',
                        'count' => $records->count(),
                        'data' => $records->map(function ($record) use ($fields) {
                            return $fields ? $record->only($fields) : $record->toArray();
                        })->toArray(),
                    ];
            }
        } catch (\Exception $e) {
            return ['error' => 'Query error: '.$e->getMessage()];
        }
    }

    /**
     * Get tool definitions for OpenAI function calling.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getToolDefinitions(): array
    {
        $resources = self::getAvailableResources();
        $resourceNames = array_keys($resources);

        if (empty($resourceNames)) {
            return [];
        }

        return [
            [
                'name' => 'list_resources',
                'description' => 'List all available resources in the admin panel with their record counts',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass,
                    'required' => [],
                ],
            ],
            [
                'name' => 'query_resource',
                'description' => 'Query data from a specific resource. Use this to get records, count records, or find specific items.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'resource' => [
                            'type' => 'string',
                            'description' => 'The resource slug to query',
                            'enum' => $resourceNames,
                        ],
                        'action' => [
                            'type' => 'string',
                            'description' => 'The action to perform',
                            'enum' => ['list', 'count', 'get'],
                            'default' => 'list',
                        ],
                        'id' => [
                            'type' => 'integer',
                            'description' => 'The record ID (required for "get" action)',
                        ],
                        'filters' => [
                            'type' => 'object',
                            'description' => 'Filter conditions as field:value pairs',
                        ],
                        'fields' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Specific fields to return',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of records to return (max 50)',
                            'default' => 10,
                        ],
                    ],
                    'required' => ['resource'],
                ],
            ],
        ];
    }

    /**
     * Execute a tool call.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function executeTool(string $name, array $arguments): array
    {
        return match ($name) {
            'list_resources' => ['resources' => self::getAvailableResources()],
            'query_resource' => self::queryResource($arguments),
            default => ['error' => "Unknown tool: {$name}"],
        };
    }
}
