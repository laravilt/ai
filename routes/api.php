<?php

use Illuminate\Support\Facades\Route;
use Laravilt\AI\Http\Controllers\AIController;
use Laravilt\AI\Http\Controllers\GlobalSearchController;

Route::middleware(['web', 'auth'])->prefix('laravilt-ai')->group(function () {
    // AI Configuration
    Route::get('/config', [AIController::class, 'config'])->name('laravilt-ai.config');

    // Chat endpoints
    Route::post('/chat', [AIController::class, 'chat'])->name('laravilt-ai.chat');
    Route::post('/stream', [AIController::class, 'stream'])->name('laravilt-ai.stream');

    // Session management
    Route::get('/sessions', [AIController::class, 'sessions'])->name('laravilt-ai.sessions');
    Route::post('/sessions', [AIController::class, 'createSession'])->name('laravilt-ai.sessions.create');
    Route::get('/sessions/{id}', [AIController::class, 'session'])->name('laravilt-ai.sessions.show');
    Route::patch('/sessions/{id}', [AIController::class, 'updateSession'])->name('laravilt-ai.sessions.update');
    Route::delete('/sessions/{id}', [AIController::class, 'deleteSession'])->name('laravilt-ai.sessions.delete');

    // Global search
    Route::get('/search', [GlobalSearchController::class, 'search'])->name('laravilt-ai.search');
    Route::get('/search/resources', [GlobalSearchController::class, 'resources'])->name('laravilt-ai.search.resources');
});
