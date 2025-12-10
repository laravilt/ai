<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

use Generator;

class GeminiProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'gemini';
    }

    public function getLabel(): string
    {
        return 'Google Gemini';
    }

    public function getModels(): array
    {
        return [
            'gemini-2.0-flash-exp' => 'Gemini 2.0 Flash',
            'gemini-1.5-pro' => 'Gemini 1.5 Pro',
            'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B',
            'gemini-pro' => 'Gemini Pro',
        ];
    }

    public function getDefaultModel(): string
    {
        return 'gemini-2.0-flash-exp';
    }

    protected function getBaseUrl(): string
    {
        return 'https://generativelanguage.googleapis.com/v1beta';
    }

    protected function getHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
        ];
    }

    public function chat(array $messages, array $options = []): array
    {
        $model = $options['model'] ?? $this->getModel();

        // Convert messages to Gemini format
        $contents = $this->convertMessages($messages);

        $response = $this->http()->post("/models/{$model}:generateContent", [
            'key' => $this->getApiKey(),
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? $this->getTemperature(),
                'maxOutputTokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
            ],
        ]);

        $data = $response->json();

        $content = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $data['candidates'][0]['content']['parts'][0]['text'];
        }

        return [
            'content' => $content,
            'usage' => [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): array
    {
        $model = $options['model'] ?? $this->getModel();

        // Convert messages to Gemini format
        $contents = $this->convertMessages($messages);

        // Convert tools to Gemini format
        $functionDeclarations = array_map(function ($tool) {
            return [
                'name' => $tool['name'],
                'description' => $tool['description'] ?? '',
                'parameters' => $tool['parameters'] ?? ['type' => 'object', 'properties' => []],
            ];
        }, $tools);

        $response = $this->http()->post("/models/{$model}:generateContent", [
            'key' => $this->getApiKey(),
            'contents' => $contents,
            'tools' => [
                ['functionDeclarations' => $functionDeclarations],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? $this->getTemperature(),
                'maxOutputTokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
            ],
        ]);

        $data = $response->json();

        $content = null;
        $toolCalls = null;

        $parts = $data['candidates'][0]['content']['parts'] ?? [];
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $content = $part['text'];
            }
            if (isset($part['functionCall'])) {
                $toolCalls = $toolCalls ?? [];
                $toolCalls[] = [
                    'id' => uniqid('call_'),
                    'name' => $part['functionCall']['name'],
                    'arguments' => $part['functionCall']['args'] ?? [],
                ];
            }
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'usage' => [
                'prompt_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0,
                'completion_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0,
                'total_tokens' => $data['usageMetadata']['totalTokenCount'] ?? 0,
            ],
        ];
    }

    public function streamChat(array $messages, array $options = []): Generator
    {
        $model = $options['model'] ?? $this->getModel();

        // Convert messages to Gemini format
        $contents = $this->convertMessages($messages);

        $response = $this->http()->withOptions(['stream' => true])
            ->post("/models/{$model}:streamGenerateContent", [
                'key' => $this->getApiKey(),
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? $this->getTemperature(),
                    'maxOutputTokens' => $options['max_tokens'] ?? $this->getMaxTokens(),
                ],
            ]);

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            // Parse JSON objects from the buffer
            while (($pos = strpos($buffer, "\n")) !== false) {
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);

                $line = trim($line);
                if (empty($line) || $line === '[' || $line === ']' || $line === ',') {
                    continue;
                }

                // Remove leading comma if present
                $line = ltrim($line, ',');

                $json = json_decode($line, true);
                if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
                    yield $json['candidates'][0]['content']['parts'][0]['text'];
                }
            }
        }
    }

    /**
     * Convert OpenAI-style messages to Gemini format
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, parts: array<int, array{text: string}>}>
     */
    private function convertMessages(array $messages): array
    {
        $contents = [];
        $systemInstruction = null;

        foreach ($messages as $message) {
            if ($message['role'] === 'system') {
                $systemInstruction = $message['content'];

                continue;
            }

            $role = match ($message['role']) {
                'assistant' => 'model',
                default => 'user',
            };

            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $message['content']]],
            ];
        }

        // Prepend system instruction to first user message if exists
        if ($systemInstruction && count($contents) > 0) {
            $contents[0]['parts'][0]['text'] = $systemInstruction."\n\n".$contents[0]['parts'][0]['text'];
        }

        return $contents;
    }
}
