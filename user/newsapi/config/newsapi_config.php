<?php
/**
 * NewsAPI Configuration
 * Central configuration file for NewsAPI integration
 *
 * @package     NewsAggregator
 * @version     2.0.0
 * @environment Supports: local | staging | production
 */

// ─── Environment Detection ────────────────────────────────────────────────────
$env = getenv('APP_ENV') ?: 'production';
$isDev = in_array($env, ['local', 'development']);

return [

    // ─── API Credentials & Endpoint ──────────────────────────────────────────
    'api' => [
        'key'             => getenv('NEWSAPI_KEY') ?: 'ace34e79173f490eb82918f6a660a99c',
        'base_url'        => 'https://newsapi.org/v2/everything',
        'top_headlines'   => 'https://newsapi.org/v2/top-headlines',
        'sources_url'     => 'https://newsapi.org/v2/top-headlines/sources',
        'version'         => 'v2',
        'timeout'         => 30,
        'connect_timeout' => 10,
        'retry_attempts'  => 3,
        'retry_delay_ms'  => 500,                         // Delay between retries
        'user_agent'      => 'NewsAggregator/2.0 (PHP/' . PHP_VERSION . ')',
        'rate_limit'      => [
            'requests_per_day'  => 100,                   // Free tier cap
            'requests_per_hour' => 20,                    // Soft internal limit
        ],
    ],

    // ─── Cache Configuration ─────────────────────────────────────────────────
    'cache' => [
        'enabled'               => true,
        'driver'                => 'file',                // 'file' | 'redis' | 'memcached'
        'dir'                   => __DIR__ . '/cache/newsapi/',
        'lifetime'              => $isDev ? 60 : 1800,   // 1 min dev / 30 min prod
        'top_headlines_ttl'     => $isDev ? 60 : 300,    // 5 min prod for breaking news
        'max_articles_per_fetch'=> 100,
        'key_prefix'            => 'newsapi_',
        'compression'           => true,                  // GZip cached responses
        'auto_purge'            => true,                  // Remove expired on read
        'purge_probability'     => 5,                     // 5% chance per request
    ],

    // ─── Request Defaults ────────────────────────────────────────────────────
    'defaults' => [
        'language'    => 'en',
        'country'     => 'ph',
        'category'    => 'general',
        'sort_by'     => 'publishedAt',                   // relevancy | popularity | publishedAt
        'page_size'   => 12,
        'safe_search' => true,
    ],

    // ─── Pagination ──────────────────────────────────────────────────────────
    'pagination' => [
        'default_items_per_page' => 12,
        'allowed_items_per_page' => [6, 12, 24, 48, 100],
        'max_total_results'      => 100,                  // NewsAPI hard cap
        'scroll_offset'          => 100,                  // px, for infinite scroll
    ],

    // ─── Categories ──────────────────────────────────────────────────────────
    'categories' => [
        'general'       => ['label' => 'General',       'icon' => 'article',          'color' => '#607D8B'],
        'business'      => ['label' => 'Business',      'icon' => 'business_center',  'color' => '#2196F3'],
        'entertainment' => ['label' => 'Entertainment', 'icon' => 'movie',            'color' => '#9C27B0'],
        'health'        => ['label' => 'Health',        'icon' => 'favorite',         'color' => '#E91E63'],
        'science'       => ['label' => 'Science',       'icon' => 'science',          'color' => '#00BCD4'],
        'sports'        => ['label' => 'Sports',        'icon' => 'sports_soccer',    'color' => '#FF9800'],
        'technology'    => ['label' => 'Technology',    'icon' => 'computer',         'color' => '#4CAF50'],
    ],

    // ─── Supported Countries ─────────────────────────────────────────────────
    'countries' => [
        'us' => ['name' => 'United States',  'flag' => '🇺🇸', 'locale' => 'en_US', 'supported' => true],
        'gb' => ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'locale' => 'en_GB', 'supported' => true],
        'ph' => ['name' => 'Philippines',    'flag' => '🇵🇭', 'locale' => 'fil_PH','supported' => false],
        'sg' => ['name' => 'Singapore',      'flag' => '🇸🇬', 'locale' => 'en_SG', 'supported' => false],
        'jp' => ['name' => 'Japan',          'flag' => '🇯🇵', 'locale' => 'ja_JP', 'supported' => false],
    ],

    // ─── Supported Languages ─────────────────────────────────────────────────
    'languages' => [
        'en' => 'English',
        'fr' => 'French',
        'de' => 'German',
        'es' => 'Spanish',
        'it' => 'Italian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ar' => 'Arabic',
    ],

    // ─── Sort Options ────────────────────────────────────────────────────────
    'sort_options' => [
        'publishedAt' => 'Latest First',
        'popularity'  => 'Most Popular',
        'relevancy'   => 'Most Relevant',
    ],

    // ─── Content Filtering ───────────────────────────────────────────────────
    'filters' => [
        'excluded_domains'  => [],                        // e.g. ['example.com']
        'excluded_sources'  => [],                        // e.g. ['source-id']
        'strip_html'        => true,
        'min_description_len' => 20,                      // Skip articles with short blurbs
        'require_image'     => false,                     // Only return articles with images
        'deduplicate'       => true,                      // Remove near-duplicate headlines
    ],

    // ─── Logging ─────────────────────────────────────────────────────────────
    'logging' => [
        'enabled'       => true,
        'level'         => $isDev ? 'debug' : 'error',   // debug | info | warning | error
        'log_dir'       => __DIR__ . '/logs/newsapi/',
        'log_requests'  => $isDev,
        'log_responses' => $isDev,
        'max_log_size'  => 5 * 1024 * 1024,              // 5 MB before rotation
        'max_log_files' => 7,                             // Days of log retention
    ],

    // ─── Display / UI Hints ──────────────────────────────────────────────────
    'display' => [
        'date_format'          => 'd M Y, g:i A',
        'excerpt_length'       => 160,                    // Characters
        'placeholder_image'    => '/assets/img/news-placeholder.jpg',
        'card_image_height'    => 200,                    // px
        'show_source_logo'     => true,
        'show_reading_time'    => true,
        'words_per_minute'     => 200,                    // For reading time estimate
        'open_links_in'        => '_blank',
    ],

    // ─── Feature Flags ───────────────────────────────────────────────────────
    'features' => [
        'search_enabled'        => true,
        'bookmarks_enabled'     => true,
        'share_buttons'         => true,
        'dark_mode'             => true,
        'translation_enabled'   => true,                  // Ties into your Filipino translator
        'infinite_scroll'       => false,
        'push_notifications'    => false,
    ],

    // ─── Security ────────────────────────────────────────────────────────────
    'security' => [
        'sanitize_output'     => true,
        'allowed_html_tags'   => '<p><a><strong><em><br>',
        'csrf_protection'     => true,
        'api_key_exposure'    => false,                   // Never expose key in frontend
    ],

];