<?php

use Laravilt\AI\Enums\OpenAIModel;

describe('OpenAIModel Enum', function () {
    it('has correct values', function () {
        expect(OpenAIModel::GPT_4O->value)->toBe('gpt-4o');
        expect(OpenAIModel::GPT_4O_MINI->value)->toBe('gpt-4o-mini');
        expect(OpenAIModel::GPT_4_TURBO->value)->toBe('gpt-4-turbo');
        expect(OpenAIModel::GPT_4->value)->toBe('gpt-4');
        expect(OpenAIModel::GPT_35_TURBO->value)->toBe('gpt-3.5-turbo');
    });

    it('has correct labels', function () {
        expect(OpenAIModel::GPT_4O->label())->toBe('GPT-4o');
        expect(OpenAIModel::GPT_4O_MINI->label())->toBe('GPT-4o Mini');
        expect(OpenAIModel::GPT_35_TURBO->label())->toBe('GPT-3.5 Turbo');
    });

    it('can convert to array', function () {
        $array = OpenAIModel::toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKey('gpt-4o');
        expect($array)->toHaveKey('gpt-4o-mini');
        expect($array['gpt-4o'])->toBe('GPT-4o');
    });

    it('can get all cases', function () {
        $cases = OpenAIModel::cases();

        expect($cases)->toBeArray();
        expect(count($cases))->toBeGreaterThan(0);
    });

    it('includes O1 models', function () {
        expect(OpenAIModel::O1->value)->toBe('o1');
        expect(OpenAIModel::O1_MINI->value)->toBe('o1-mini');
        expect(OpenAIModel::O1_PREVIEW->value)->toBe('o1-preview');
    });
});
