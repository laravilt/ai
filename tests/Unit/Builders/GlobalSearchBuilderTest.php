<?php

use Laravilt\AI\Builders\GlobalSearchBuilder;

describe('GlobalSearchBuilder', function () {
    beforeEach(function () {
        $this->builder = new GlobalSearchBuilder;
    });

    it('is enabled by default', function () {
        expect($this->builder->isEnabled())->toBeTrue();
    });

    it('can be disabled', function () {
        $this->builder->disabled();

        expect($this->builder->isEnabled())->toBeFalse();
    });

    it('can be enabled with condition', function () {
        $this->builder->enabled(false);

        expect($this->builder->isEnabled())->toBeFalse();
    });

    it('can set limit', function () {
        $this->builder->limit(10);

        expect($this->builder->getLimit())->toBe(10);
    });

    it('has default limit of 5', function () {
        expect($this->builder->getLimit())->toBe(5);
    });

    it('can set debounce', function () {
        $this->builder->debounce(500);

        expect($this->builder->getDebounce())->toBe(500);
    });

    it('has default debounce of 300', function () {
        expect($this->builder->getDebounce())->toBe(300);
    });

    it('can enable AI', function () {
        $this->builder->withAI();

        expect($this->builder->usesAI())->toBeTrue();
    });

    it('AI is disabled by default', function () {
        expect($this->builder->usesAI())->toBeFalse();
    });

    it('can set custom endpoint', function () {
        $this->builder->endpoint('/custom-search');

        expect($this->builder->getEndpoint())->toBe('/custom-search');
    });

    it('can exclude resources', function () {
        $this->builder->exclude(['UserResource', 'PostResource']);

        expect($this->builder->getExcludedResources())->toContain('UserResource', 'PostResource');
    });

    it('can set shortcut', function () {
        $this->builder->shortcut('ctrl+k');

        expect($this->builder->getShortcut())->toBe('ctrl+k');
    });

    it('has default shortcut', function () {
        expect($this->builder->getShortcut())->toBe('cmd+k');
    });

    it('can convert to array', function () {
        $this->builder
            ->enabled()
            ->limit(10)
            ->debounce(500)
            ->withAI();

        $array = $this->builder->toArray();

        expect($array)->toHaveKeys(['enabled', 'limit', 'debounce', 'useAI', 'endpoint']);
        expect($array['enabled'])->toBeTrue();
        expect($array['limit'])->toBe(10);
        expect($array['debounce'])->toBe(500);
        expect($array['useAI'])->toBeTrue();
    });
});
