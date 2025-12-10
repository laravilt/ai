<?php

use Laravilt\AI\GlobalSearch;

describe('GlobalSearch', function () {
    beforeEach(function () {
        $this->search = new GlobalSearch;
    });

    it('can register a resource', function () {
        $result = $this->search->registerResource(
            resource: 'users',
            model: \Illuminate\Foundation\Auth\User::class,
            searchable: ['name', 'email'],
            label: 'Users',
            icon: 'Users',
            url: '/admin/users/{id}'
        );

        expect($result)->toBeInstanceOf(GlobalSearch::class);
    });

    it('can set limit', function () {
        $result = $this->search->limit(10);

        expect($result)->toBeInstanceOf(GlobalSearch::class);
    });

    it('can enable/disable AI', function () {
        $result = $this->search->useAI(true);

        expect($result)->toBeInstanceOf(GlobalSearch::class);
    });

    it('returns empty collection for empty query', function () {
        $results = $this->search->search('');

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toBeEmpty();
    });

    it('returns empty collection for whitespace query', function () {
        $results = $this->search->search('   ');

        expect($results)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($results)->toBeEmpty();
    });
});
