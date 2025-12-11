<?php

return [
    // Global Search
    'search' => [
        'placeholder' => 'Search...',
        'ai_powered' => 'AI-powered search',
        'no_results' => 'No results found',
        'no_results_for' => 'No results found for ":query"',
        'try_different' => 'Try searching with different keywords',
        'start_typing' => 'Start typing to search',
        'results_count' => ':count results',
        'type_to_search' => 'Type to search',
        'press_to_close' => 'Press :key to close',
        'to_navigate' => 'to navigate',
        'to_select' => 'to select',
        'to_close' => 'to close',
        'toggle_ai' => 'Toggle AI search',
        'ai_enabled' => 'AI search enabled',
        'ai_disabled' => 'AI search disabled',
    ],

    // Chat
    'chat' => [
        'title' => 'AI Chat',
        'new_chat' => 'New Chat',
        'send_message' => 'Send message',
        'type_message' => 'Type your message...',
        'thinking' => 'Thinking...',
        'error' => 'An error occurred. Please try again.',
        'sessions' => 'Chat Sessions',
        'no_sessions' => 'No chat sessions',
        'delete_session' => 'Delete session',
        'delete_confirm' => 'Are you sure you want to delete this session?',
        'clear_chat' => 'Clear chat',
        'clear_confirm' => 'Are you sure you want to clear this chat?',
        'copy_message' => 'Copy message',
        'copied' => 'Copied to clipboard',
        'regenerate' => 'Regenerate response',
        'mention_resource' => 'Mention a resource',
        'press_enter' => 'Press',
        'to_send' => 'to send',
        'for_new_line' => 'for new line',
        'to_mention' => 'to mention resource',
        'understand_codebase' => 'Understand codebase',
        'generate_report' => 'Generate report',
        'debug_issue' => 'Debug issue',
    ],

    // Providers
    'providers' => [
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'gemini' => 'Google Gemini',
        'deepseek' => 'DeepSeek',
        'select_provider' => 'Select AI Provider',
        'select_model' => 'Select Model',
    ],

    // Settings
    'settings' => [
        'title' => 'AI Settings',
        'provider' => 'AI Provider',
        'model' => 'Model',
        'temperature' => 'Temperature',
        'max_tokens' => 'Max Tokens',
    ],

    // Actions
    'actions' => [
        'ask_ai' => 'Ask AI',
        'generate' => 'Generate',
        'generating' => 'Generating...',
        'improve' => 'Improve with AI',
        'translate' => 'Translate',
        'summarize' => 'Summarize',
        'explain' => 'Explain',
    ],

    // Errors
    'errors' => [
        'not_configured' => 'AI is not configured. Please add your API key.',
        'provider_error' => 'Error communicating with AI provider.',
        'rate_limit' => 'Rate limit exceeded. Please try again later.',
        'invalid_response' => 'Invalid response from AI provider.',
    ],
];
