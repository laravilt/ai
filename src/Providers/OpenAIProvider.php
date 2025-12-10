<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

use Generator;

class OpenAIProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'openai';
    }

    public function getLabel(): string
    {
        return 'OpenAI';
    }

    public function getModels(): array
    {
        return [
            'gpt-4o' => 'GPT-4o',
            'gpt-4o-mini' => 'GPT-4o Mini',
            'gpt-4-turbo' => 'GPT-4 Turbo',
            'gpt-4' => 'GPT-4',
            'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
            'o1' => 'o1',
            'o1-mini' => 'o1 Mini',
            'o1-preview' => 'o1 Preview',
        ];
    }

    public function getDefaultModel(): string
    {
        return 'gpt-4o-mini';
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    protected function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getApiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(array $messages, array $options = []): array
    {
        $response = $this->http()->post('/chat/completions', [
            'model' => $options['model'] ?? $this->getModel(),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? $this->getTemperature(),
            'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
        ]);

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): array
    {
        $formattedTools = array_map(function ($tool) {
            return [
                'type' => 'function',
                'function' => [
                    'name' => $tool['name'],
                    'description' => $tool['description'] ?? '',
                    'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
                ],
            ];
        }, $tools);

        $response = $this->http()->post('/chat/completions', [
            'model' => $options['model'] ?? $this->getModel(),
            'messages' => $messages,
            'tools' => $formattedTools,
            'tool_choice' => $options['tool_choice'] ?? 'auto',
            'temperature' => $options['temperature'] ?? $this->getTemperature(),
            'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
        ]);

        $data = $response->json();
        $message = $data['choices'][0]['message'] ?? [];

        $toolCalls = null;
        if (isset($message['tool_calls'])) {
            $toolCalls = array_map(function ($call) {
                return [
                    'id' => $call['id'],
                    'name' => $call['function']['name'],
                    'arguments' => json_decode($call['function']['arguments'], true) ?? [],
                ];
            }, $message['tool_calls']);
        }

        return [
            'content' => $message['content'] ?? null,
            'tool_calls' => $toolCalls,
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    public function streamChat(array $messages, array $options = []): Generator
    {
        $response = $this->http()->withOptions(['stream' => true])
            ->post('/chat/completions', [
                'model' => $options['model'] ?? $this->getModel(),
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? $this->getTemperature(),
                'max_tokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
                'stream' => true,
            ]);

        $body = $response->toPsrResponse()->getBody();

        while (! $body->eof()) {
            $line = $this->readLine($body);
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    break;
                }
                $json = json_decode($data, true);
                if (isset($json['choices'][0]['delta']['content'])) {
                    yield $json['choices'][0]['delta']['content'];
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
