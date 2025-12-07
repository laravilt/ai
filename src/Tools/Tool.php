<?php

declare(strict_types=1);

namespace Laravilt\AI\Tools;

use Closure;

abstract class Tool
{
    protected string $name;

    protected ?string $description = null;

    /** @var array<string, array<string, mixed>> */
    protected array $parameters = [];

    /** @var array<string, mixed> */
    protected array $schema = [];

    protected ?Closure $handler = null;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(string $name): static
    {
        return new static($name); // @phpstan-ignore new.static
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @param  array<string, array<string, mixed>>  $parameters
     */
    public function parameters(array $parameters): static
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function addParameter(string $name, string $type, string $description, bool $required = false): static
    {
        $this->parameters[$name] = [
            'type' => $type,
            'description' => $description,
            'required' => $required,
        ];

        return $this;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function schema(array $schema): static
    {
        $this->schema = $schema;

        return $this;
    }

    public function handler(Closure $callback): static
    {
        $this->handler = $callback;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments): mixed
    {
        if ($this->handler) {
            return ($this->handler)($arguments);
        }

        return $this->handle($arguments);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    abstract protected function handle(array $arguments): mixed;

    /**
     * Export tool configuration for AI providers
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $config) {
            $properties[$name] = [
                'type' => $config['type'],
                'description' => $config['description'],
            ];

            if ($config['required'] ?? false) {
                $required[] = $name;
            }
        }

        return [
            'name' => $this->name,
            'description' => $this->description ?? '',
            'parameters' => [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
            ],
        ];
    }
}
