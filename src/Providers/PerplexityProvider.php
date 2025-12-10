<?php

declare(strict_types=1);

namespace Laravilt\AI\Providers;

use Generator;
use Laravilt\AI\Enums\PerplexityModel;

class PerplexityProvider extends BaseProvider
{
    public function getName(): string
    {
        return 'perplexity';
    }

    public function getLabel(): string
    {
        return 'Perplexity';
    }

    public function getModels(): array
    {
        return PerplexityModel::toArray();
    }

    public function getDefaultModel(): string
    {
        return PerplexityModel::SONAR->value;
    }

    protected function getBaseUrl(): string
    {
        return 'https://api.perplexity.ai';
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
            'citations' => $data['citations'] ?? [],
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
                'total_tokens' => $data['usage']['total_tokens'] ?? 0,
            ],
        ];
    }

    public function chatWithTools(array $messages, array $tools, array $options = []): array
    {
        // Perplexity doesn't support tools, fall back to regular chat
        $result = $this->chat($messages, $options);

        return [
            'content' => $result['content'],
            'tool_calls' => null,
            'citations' => $result['citations'] ?? [],
            'usage' => $result['usage'],
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
