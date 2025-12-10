<?php

declare(strict_types=1);

namespace Laravilt\AI\Enums;

enum PerplexityModel: string
{
    case SONAR = 'sonar';
    case SONAR_PRO = 'sonar-pro';
    case SONAR_REASONING = 'sonar-reasoning';
    case SONAR_REASONING_PRO = 'sonar-reasoning-pro';

    public function label(): string
    {
        return match ($this) {
            self::SONAR => 'Sonar',
            self::SONAR_PRO => 'Sonar Pro',
            self::SONAR_REASONING => 'Sonar Reasoning',
            self::SONAR_REASONING_PRO => 'Sonar Reasoning Pro',
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
