<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use when none is specified.
    |
    */
    'default' => env('LARAVILT_AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Providers Configuration
    |--------------------------------------------------------------------------
    |
    | Configure your AI providers here. Each provider requires an API key
    | and can have optional settings like model, temperature, etc.
    |
    */
    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 2048),
        ],

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
            'base_url' => env('ANTHROPIC_BASE_URL'),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2048),
        ],

        'gemini' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'model' => env('GOOGLE_AI_MODEL', 'gemini-2.0-flash-exp'),
            'base_url' => env('GOOGLE_AI_BASE_URL'),
            'temperature' => env('GOOGLE_AI_TEMPERATURE', 0.7),
            'max_tokens' => env('GOOGLE_AI_MAX_TOKENS', 2048),
        ],

        'deepseek' => [
            'api_key' => env('DEEPSEEK_API_KEY'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'base_url' => env('DEEPSEEK_BASE_URL'),
            'temperature' => env('DEEPSEEK_TEMPERATURE', 0.7),
            'max_tokens' => env('DEEPSEEK_MAX_TOKENS', 2048),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure global search behavior including result limits and AI usage.
    |
    */
    'global_search' => [
        'enabled' => true,
        'limit' => 5,
        'use_ai' => true, // Use AI to understand search queries when available
    ],

    /*
    |--------------------------------------------------------------------------
    | Chat UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the chat UI appearance and behavior.
    |
    */
    'chat' => [
        'streaming' => true,
        'max_history' => 100,
        'show_token_usage' => false,
    ],
];
