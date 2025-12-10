<?php

declare(strict_types=1);

namespace Laravilt\AI\Enums;

enum OpenAIModel: string
{
    case GPT_4O = 'gpt-4o';
    case GPT_4O_MINI = 'gpt-4o-mini';
    case GPT_4_TURBO = 'gpt-4-turbo';
    case GPT_4 = 'gpt-4';
    case GPT_35_TURBO = 'gpt-3.5-turbo';
    case O1 = 'o1';
    case O1_MINI = 'o1-mini';
    case O1_PREVIEW = 'o1-preview';

    public function label(): string
    {
        return match ($this) {
            self::GPT_4O => 'GPT-4o',
            self::GPT_4O_MINI => 'GPT-4o Mini',
            self::GPT_4_TURBO => 'GPT-4 Turbo',
            self::GPT_4 => 'GPT-4',
            self::GPT_35_TURBO => 'GPT-3.5 Turbo',
            self::O1 => 'O1',
            self::O1_MINI => 'O1 Mini',
            self::O1_PREVIEW => 'O1 Preview',
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
