<?php

declare(strict_types=1);

namespace Laravilt\AI\Enums;

enum AnthropicModel: string
{
    case CLAUDE_SONNET_4 = 'claude-sonnet-4-20250514';
    case CLAUDE_OPUS_4 = 'claude-opus-4-20250514';
    case CLAUDE_35_SONNET = 'claude-3-5-sonnet-20241022';
    case CLAUDE_35_HAIKU = 'claude-3-5-haiku-20241022';
    case CLAUDE_3_OPUS = 'claude-3-opus-20240229';
    case CLAUDE_3_SONNET = 'claude-3-sonnet-20240229';
    case CLAUDE_3_HAIKU = 'claude-3-haiku-20240307';

    public function label(): string
    {
        return match ($this) {
            self::CLAUDE_SONNET_4 => 'Claude Sonnet 4',
            self::CLAUDE_OPUS_4 => 'Claude Opus 4',
            self::CLAUDE_35_SONNET => 'Claude 3.5 Sonnet',
            self::CLAUDE_35_HAIKU => 'Claude 3.5 Haiku',
            self::CLAUDE_3_OPUS => 'Claude 3 Opus',
            self::CLAUDE_3_SONNET => 'Claude 3 Sonnet',
            self::CLAUDE_3_HAIKU => 'Claude 3 Haiku',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function toArray(): array
    {
        $models = [];
        foreach (self::cases() as $case) {
            $models[$case->value] = $case->label();
        }

        return $models;
    }
}
