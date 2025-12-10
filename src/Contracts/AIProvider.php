<?php

declare(strict_types=1);

namespace Laravilt\AI\Contracts;

interface AIProvider
{
    /**
     * Get the provider name identifier
     */
    public function getName(): string;

    /**
     * Get the display label for the provider
     */
    public function getLabel(): string;

    /**
     * Get available models for this provider
     *
     * @return array<string, string>
     */
    public function getModels(): array;

    /**
     * Get the default model for this provider
     */
    public function getDefaultModel(): string;

    /**
     * Check if the provider is configured and ready
     */
    public function isConfigured(): bool;

    /**
     * Send a chat completion request
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}}
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * Send a chat completion with tool calling support
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: ?string, tool_calls: ?array<int, array{id: string, name: string, arguments: array<string, mixed>}>, usage: array<string, int>}
     */
    public function chatWithTools(array $messages, array $tools, array $options = []): array;

    /**
     * Stream a chat completion
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return \Generator<string>
     */
    public function streamChat(array $messages, array $options = []): \Generator;

    /**
     * Get configuration array for frontend
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
