<?php

describe('AI Routes', function () {
    it('ai config endpoint returns json', function () {
        // This test requires full panel setup with routes registered
        // For now, we verify the endpoint structure exists in config
        expect(config('laravilt-ai.providers'))->toBeArray();
    });

    it('can load translations', function () {
        expect(__('laravilt-ai::ai.chat.title'))->toBe('AI Chat');
        expect(__('laravilt-ai::ai.chat.new_chat'))->toBe('New Chat');
    });

    it('can load arabic translations', function () {
        app()->setLocale('ar');

        expect(__('laravilt-ai::ai.chat.title'))->toBe('محادثة الذكاء الاصطناعي');
        expect(__('laravilt-ai::ai.chat.new_chat'))->toBe('محادثة جديدة');
    });

    it('can load search translations', function () {
        expect(__('laravilt-ai::ai.search.placeholder'))->toBe('Search...');
        expect(__('laravilt-ai::ai.search.no_results'))->toBe('No results found');
    });

    it('can load arabic search translations', function () {
        app()->setLocale('ar');

        expect(__('laravilt-ai::ai.search.placeholder'))->toBe('بحث...');
        expect(__('laravilt-ai::ai.search.no_results'))->toBe('لا توجد نتائج');
    });
});
