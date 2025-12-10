<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

use Generator;

class AnthropicProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'anthropic';
    }

    public function getLabel(): string
    {
        return 'Anthropic';
    }

    public function getModels(): array
    {
        return [
            'claude-sonnet-4-20250514' => 'Claude Sonnet 4',
            'claude-opus-4-20250514' => 'Claude Opus 4',
            'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
            'claude-3-5-haiku-20241022' => 'Claude 3.5 Haiku',
            'claude-3-opus-20240229' => 'Claude 3 Opus',
            'claude-3-sonnet-20240229' => 'Claude 3 Sonnet',
            'claude-3-haiku-20240307' => 'Claude 3 Haiku',
        ];
    }

    public function getDefaultModel(): string
    {
        return 'claude-sonnet-4-20250514';
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    protected function getHeaders(): array
    {
        return [
            'x-api-key' => $this->getApiKey(),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(array $messages, array $options = []): array
    {
        // Extract system message if present
        $system = null;
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->getModel(),
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        // Anthropic doesn't use temperature for some models
        if (! str_starts_with($payload['model'], 'claude-3-opus')) {
            $payload['temperature'] = $options['temperature'] ?? $this->getTemperature();
        }

        $response = $this->http()->post('/messages', $payload);

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): array
    {
        // Extract system message if present
        $system = null;
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $formattedTools = array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'input_schema' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);

        $payload = [
            'model' => $options['model'] ?? $this->getModel(),
            'messages' => $filteredMessages,
            'tools' => $formattedTools,
            'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        $response = $this->http()->post('/messages', $payload);

        $data = $response->json();

        $content = null;
        $toolCalls = null;

        foreach ($data['content'] ?? [] as $block) {
            if ($block['type'] === 'text') {
                $content = $block['text'];
            } elseif ($block['type'] === 'tool_use') {
                $toolCalls = $toolCalls ?? [];
                $toolCalls[] = [
                    'id' => $block['id'],
                    'name' => $block['name'],
                    'arguments' => $block['input'] ?? [],
                ];
            }
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
                'total_tokens' => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
            ],
        ];
    }

    public function streamChat(array $messages, array $options = []): Generator
    {
        // Extract system message if present
        $system = null;
        $filteredMessages = [];

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $system = $message['content'];
            } else {
                $filteredMessages[] = $message;
            }
        }

        $payload = [
            'model' => $options['model'] ?? $this->getModel(),
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
            'stream' => true,
        ];

        if ($system) {
            $payload['system'] = $system;
        }

        $response = $this->http()->withOptions(['stream' => true])
            ->post('/messages', $payload);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                $json = json_decode($data, true);

                if (isset($json['type']) && $json['type'] === 'content_block_delta') {
                    if (isset($json['delta']['text'])) {
                        yield $json['delta']['text'];
                    }
                }
            }
        }
    }

    /**
     * @param  \Psr\Http\Message\StreamInterface  $stream
     */
    private function readLine($stream): string
    {
        $line = '';
        while (! $stream->eof()) {
            $char = $stream->read(1);
            if ($char === "\n") {
                break;
            }
            $line .= $char;
        }

        return trim($line);
    }
}
