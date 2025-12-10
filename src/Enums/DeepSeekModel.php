<?php

declare(strict_types=1);

namespace Laravilt\AI\Enums;

enum DeepSeekModel: string
{
    case DEEPSEEK_CHAT = 'deepseek-chat';
    case DEEPSEEK_CODER = 'deepseek-coder';
    case DEEPSEEK_REASONER = 'deepseek-reasoner';

    public function label(): string
    {
        return match ($this) {
            self::DEEPSEEK_CHAT => 'DeepSeek Chat',
            self::DEEPSEEK_CODER => 'DeepSeek Coder',
            self::DEEPSEEK_REASONER => 'DeepSeek Reasoner (R1)',
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
