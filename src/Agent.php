<?php

declare(strict_types=1);

namespace Laravilt\AI;

use Laravilt\AI\Tools\Tool;

abstract class Agent
{
    protected string $name;

    protected ?string $description = null;

    protected ?string $instructions = null;

    /** @var array<int, Tool> */
    protected array $tools = [];

    /** @var array<string, mixed> */
    protected array $metadata = [];

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

    public function instructions(string $instructions): static
    {
        $this->instructions = $instructions;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
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

    /**
     * Export agent configuration for AI providers (OpenAI, Anthropic, etc.)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'tools' => array_map(
                fn (Tool $tool) => $tool->toArray(),
                $this->tools
            ),
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Export agent configuration as JSON string
     */
    public function toJson(): string
    {
        $json = json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return $json === false ? '{}' : $json;
    }
}
