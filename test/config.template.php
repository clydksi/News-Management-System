<?php
/**
 * API Configuration Template
 * 
 * Copy this file to config.php and add your actual API keys
 * DO NOT commit config.php to version control
 */

// Keys loaded from root .env file
$_env = parse_ini_file(dirname(__DIR__) . '/.env') ?: [];

// OpenAI Configuration — https://platform.openai.com/api-keys
define('OPENAI_API_KEY', $_env['OPENAI_API_KEY'] ?? '');
define('OPENAI_MODEL', 'gpt-4o-mini');

// Anthropic Claude Configuration — https://console.anthropic.com/
define('ANTHROPIC_API_KEY', $_env['ANTHROPIC_API_KEY'] ?? '');
define('CLAUDE_MODEL', 'claude-sonnet-4-20250514');

// Google Gemini Configuration — https://aistudio.google.com/app/apikey
define('GOOGLE_API_KEY', $_env['GOOGLE_API_KEY'] ?? '');
define('GEMINI_MODEL', 'gemini-2.5-flash');

// Azure OpenAI (for Copilot integration) - Optional
define('AZURE_OPENAI_KEY', 'your-azure-key');
define('AZURE_OPENAI_ENDPOINT', 'https://your-resource.openai.azure.com/');
define('AZURE_DEPLOYMENT_NAME', 'your-deployment-name');

// Rate Limiting
define('MAX_REQUESTS_PER_MINUTE', 20);
define('MAX_MESSAGE_LENGTH', 4000);

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
?>
