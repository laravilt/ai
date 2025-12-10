<?php

declare(strict_types=1);

namespace Laravilt\AI\Enums;

enum GeminiModel: string
{
    case GEMINI_2_FLASH = 'gemini-2.0-flash-exp';
    case GEMINI_15_PRO = 'gemini-1.5-pro';
    case GEMINI_15_FLASH = 'gemini-1.5-flash';
    case GEMINI_PRO = 'gemini-pro';

    public function label(): string
    {
        return match ($this) {
            self::GEMINI_2_FLASH => 'Gemini 2.0 Flash',
            self::GEMINI_15_PRO => 'Gemini 1.5 Pro',
            self::GEMINI_15_FLASH => 'Gemini 1.5 Flash',
            self::GEMINI_PRO => 'Gemini Pro',
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
