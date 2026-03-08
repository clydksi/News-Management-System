<?php
// ============================================
// MULTI-PROVIDER AI CONFIGURATION
// ============================================
$_env = parse_ini_file(dirname(__DIR__, 2) . '/.env') ?: [];

// AI Provider Settings
define('AI_PROVIDERS', [

    'anthropic' => [
        'name'    => 'Claude (Anthropic)',
        'icon'    => '🤖',
        'api_key' => $_env['ANTHROPIC_API_KEY'] ?? '', // Get key: https://console.anthropic.com
        'models'  => [
            'claude-3-5-haiku-20241022' => [
                'name'        => 'Claude 3.5 Haiku',
                'description' => 'Fast and affordable',
                'max_tokens'  => 4096,
                'recommended' => true
            ],
            'claude-3-haiku-20240307' => [
                'name'        => 'Claude 3 Haiku',
                'description' => 'Fastest and cheapest',
                'max_tokens'  => 4096
            ]
        ],
        'default_model' => 'claude-3-5-haiku-20241022'
    ],

    'openai' => [
        'name'    => 'ChatGPT (OpenAI)',
        'icon'    => '🟢',
        'api_key' => $_env['OPENAI_API_KEY'] ?? '', // Get key: https://platform.openai.com/api-keys
        'models'  => [
            'gpt-4o-mini' => [
                'name'        => 'GPT-4o Mini',
                'description' => 'Affordable and fast',
                'max_tokens'  => 4096,
                'recommended' => true
            ],
            'gpt-3.5-turbo' => [
                'name'        => 'GPT-3.5 Turbo',
                'description' => 'Legacy model',
                'max_tokens'  => 4096
            ]
        ],
        'default_model' => 'gpt-4o-mini'
    ],

    'google' => [
        'name'     => 'Gemini (Google)',
        'icon'     => '✨',
        'api_key'  => $_env['GOOGLE_API_KEY'] ?? '', // Get key FREE: https://aistudio.google.com/app/apikey
        'base_url' => 'https://generativelanguage.googleapis.com/v1beta', // Must be v1beta
        'models'   => [
            // Source: https://ai.google.dev/gemini-api/docs/models
            'gemini-2.5-flash' => [
                'name'           => 'Gemini 2.5 Flash',
                'description'    => 'Best price/performance – FREE',
                'max_tokens'     => 8192,
                'context_window' => 1048576,
                'recommended'    => true
            ],
            'gemini-2.5-flash-lite' => [
                'name'           => 'Gemini 2.5 Flash-Lite',
                'description'    => 'Fastest & cheapest – FREE',
                'max_tokens'     => 8192,
                'context_window' => 1048576
            ],
            'gemini-2.5-pro' => [
                'name'           => 'Gemini 2.5 Pro',
                'description'    => 'Most capable, thinking model – FREE (limited)',
                'max_tokens'     => 8192,
                'context_window' => 1048576
            ],
            'gemini-2.0-flash' => [
                'name'           => 'Gemini 2.0 Flash',
                'description'    => '⚠️ Deprecated Mar 31 2026',
                'max_tokens'     => 8192,
                'context_window' => 1048576
            ],
        ],
        'default_model' => 'gemini-2.5-flash'
    ],

]);

// Default provider
define('DEFAULT_AI_PROVIDER', 'google'); // Google has best free tier

// Check if at least one provider is configured
$enabledProviders = array_filter(AI_PROVIDERS, function($config) {
    return !empty($config['api_key']);
});

define('AI_ASSISTANT_ENABLED', !empty($enabledProviders));
define('ENABLED_PROVIDERS', array_keys($enabledProviders));