<?php
/**
 * NewsAPI Dashboard – Enhanced
 *
 * @package     NewsAggregator
 * @version     3.0.0
 */

declare(strict_types=1);

// ─── Core Dependencies ───────────────────────────────────────────────────────
require_once '../auth.php';
require_once '../db.php';
require_once __DIR__ . '/newsapi/classes/CacheManager.php';
require_once __DIR__ . '/newsapi/classes/NewsAPIClient.php';
require_once __DIR__ . '/newsapi/classes/Helper.php';
require_once __DIR__ . '/newsapi/classes/Paginator.php';

// ─── Configuration ───────────────────────────────────────────────────────────
$config = require __DIR__ . '/newsapi/config/newsapi_config.php';

if (empty($config['api']['key'])) {
    die(renderFatalError('Missing API Key', 'NEWSAPI_KEY is not configured.'));
}

spl_autoload_register(function (string $class) {
    $file = __DIR__ . "/newsapi/classes/{$class}.php";
    if (file_exists($file)) require_once $file;
});

// ─── Initialize Components ───────────────────────────────────────────────────
try {
    $cacheManager = new CacheManager($config['cache']['dir'], $config['cache']['lifetime']);
    $apiClient    = new NewsAPIClient($config['api'], $cacheManager);
} catch (Exception $e) {
    die(renderFatalError('Initialization Failed', Helper::e($e->getMessage())));
}

// ─── Sanitize & Validate Input ───────────────────────────────────────────────
$validCategories  = array_keys($config['categories']);
$validCountries   = array_keys($config['countries']);
$validSortOptions = array_keys($config['sort_options'] ?? ['publishedAt' => '', 'popularity' => '', 'relevancy' => '']);

$category = Helper::getParam('category', $config['defaults']['category']);
$category = in_array($category, $validCategories, true) ? $category : $config['defaults']['category'];

$country  = Helper::getParam('country', $config['defaults']['country']);
$country  = in_array($country, $validCountries, true) ? $country : $config['defaults']['country'];

$sortBy   = Helper::getParam('sortBy', $config['defaults']['sort_by'] ?? 'publishedAt');
$sortBy   = in_array($sortBy, $validSortOptions, true) ? $sortBy : 'publishedAt';

$language = Helper::getParam('language', $config['defaults']['language'] ?? 'en');
$language = isset($config['languages'][$language]) ? $language : 'en';

$itemsPerPage = Helper::sanitizeInt(
    Helper::getParam('itemsPerPage', $config['pagination']['default_items_per_page']),
    $config['pagination']['default_items_per_page'], 1, 100
);
if (!in_array($itemsPerPage, $config['pagination']['allowed_items_per_page'], true)) {
    $itemsPerPage = $config['pagination']['default_items_per_page'];
}

$currentPage  = Helper::sanitizeInt(Helper::getParam('page', 1), 1, 1);
$searchQuery  = Helper::sanitizeString(Helper::getParam('search', ''), '', 200);
$forceRefresh = isset($_GET['refresh']);
$viewMode     = in_array(Helper::getParam('view', 'grid'), ['grid', 'list']) ? Helper::getParam('view', 'grid') : 'grid';

// ─── Fetch News ──────────────────────────────────────────────────────────────
$newsData          = [];
$allArticles       = [];
$paginatedArticles = [];
$fromCache         = false;
$errorMessage      = null;
$fetchDuration     = null;

try {
    $fetchStart = microtime(true);

    $params = [
        'category' => $category,
        'country'  => $country,
        'sortBy'   => $sortBy,
        'language' => $language,
        'pageSize' => $config['cache']['max_articles_per_fetch'],
    ];
    if (!empty($searchQuery)) $params['q'] = $searchQuery;

    $newsData      = $apiClient->fetchNews($params, $forceRefresh);
    $allArticles   = $newsData['articles'] ?? [];
    $fromCache     = $newsData['from_cache'] ?? false;
    $fetchDuration = (int)round((microtime(true) - $fetchStart) * 1000);

    $allArticles = array_values(array_filter($allArticles, function (array $a): bool {
        return !empty($a['title']) && $a['title'] !== '[Removed]' && !empty($a['url']);
    }));

    $paginator         = new Paginator($allArticles, $currentPage, $itemsPerPage);
    $paginatedArticles = $paginator->getItems();

} catch (RuntimeException $e) {
    $errorMessage = 'API Error: ' . Helper::e($e->getMessage());
} catch (Exception $e) {
    $errorMessage = 'Unexpected Error: ' . Helper::e($e->getMessage());
}

// ─── View Helpers ────────────────────────────────────────────────────────────
function buildUrlParams(array $params): string {
    return http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}
function buildPageUrl(array $overrides, array $base): string {
    return '?' . buildUrlParams(array_merge($base, $overrides));
}
function getCategoryMeta(string $category, array $categories): array {
    return $categories[$category] ?? ['label' => ucfirst($category), 'icon' => 'article', 'color' => '#64748B'];
}
function getCountryMeta(string $country, array $countries): array {
    return $countries[$country] ?? ['name' => strtoupper($country), 'flag' => '🌐'];
}
function renderFatalError(string $title, string $message): string {
    return "<!DOCTYPE html><html><head><meta charset='utf-8'/><title>Error</title></head>
    <body style='font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#FEF2F2'>
    <div style='background:white;padding:40px;border-radius:16px;max-width:400px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.1)'>
    <div style='font-size:48px;margin-bottom:16px'>⚠️</div>
    <h1 style='color:#B91C1C;margin-bottom:8px'>{$title}</h1>
    <p style='color:#6B7280;font-size:14px'>{$message}</p>
    </div></body></html>";
}

function articleReadingTime(array $article): string {
    $text  = ($article['description'] ?? '') . ' ' . ($article['content'] ?? '');
    $words = str_word_count(strip_tags($text));
    return max(1, (int)ceil($words / 200)) . ' min';
}

function articleDomain(string $url): string {
    $host = parse_url($url, PHP_URL_HOST) ?? $url;
    return preg_replace('/^www\./', '', $host);
}

function sourceBadgeColor(string $source): string {
    $colors = ['#7C3AED','#6D28D9','#5B21B6','#4C1D95','#8B5CF6','#A78BFA','#9333EA','#7E22CE'];
    return $colors[crc32($source) % count($colors)];
}

// ─── Derived State ───────────────────────────────────────────────────────────
$activeCategoryMeta = getCategoryMeta($category, $config['categories']);
$activeCountryMeta  = getCountryMeta($country, $config['countries']);
$totalArticles      = isset($paginator) ? $paginator->getTotalItems() : 0;
$totalPages         = isset($paginator) ? $paginator->getTotalPages() : 0;
$currentUser        = Helper::e($_SESSION['username'] ?? 'User');
$currentUserInitial = strtoupper(substr($currentUser, 0, 1));
$currentUserRole    = ucfirst($_SESSION['role'] ?? 'Editor');
$pageTitle          = $searchQuery
    ? "Search: \"{$searchQuery}\" – NewsAPI"
    : ($activeCategoryMeta['label'] . " – NewsAPI Dashboard");

// Source distribution (top 6)
$sourceMap = [];
foreach ($allArticles as $a) {
    $src = $a['source']['name'] ?? 'Unknown';
    $sourceMap[$src] = ($sourceMap[$src] ?? 0) + 1;
}
arsort($sourceMap);
$topSources = array_slice($sourceMap, 0, 6, true);

$baseParams = array_filter([
    'category'     => $category,
    'country'      => $country,
    'sortBy'       => $sortBy,
    'language'     => $language,
    'itemsPerPage' => $itemsPerPage,
    'view'         => $viewMode,
    'search'       => $searchQuery,
]);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="robots" content="noindex, nofollow"/>
<title><?= $pageTitle ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<style>
/* ═══ Design Tokens — Purple Edition ═════════════════════════════════ */
:root {
    --ink:          #13111A;
    --ink-muted:    #4A4560;
    --ink-faint:    #8E89A8;

    --canvas:       #F4F2FA;
    --surface:      #FFFFFF;
    --surface-2:    #EEEBf8;

    /* ── Purple Accent Scale ── */
    --purple:       #7C3AED;
    --purple-md:    #6D28D9;
    --purple-dark:  #4C1D95;
    --purple-light: #EDE9FE;
    --purple-pale:  #F5F3FF;
    --purple-glow:  rgba(124,58,237,0.18);

    /* ── Sidebar (deep violet) ── */
    --sidebar-bg:   #130F23;
    --sidebar-item: rgba(255,255,255,0.04);
    --sidebar-act:  rgba(124,58,237,0.18);
    --sidebar-text: #D4CFE8;
    --sidebar-muted:#6B6485;

    --border:       #E2DDEF;
    --border-md:    #C9C2E0;

    --success-bg:   #ECFDF5;
    --success-text: #065F46;
    --success-bd:   #A7F3D0;
    --error-bg:     #FFF1F2;
    --error-text:   #9F1239;
    --error-bd:     #FECDD3;
    --info-bg:      #F5F3FF;
    --info-text:    #5B21B6;
    --info-bd:      #C4B5FD;
    --warn-bg:      #FFFBEB;
    --warn-text:    #92400E;
    --warn-bd:      #FDE68A;

    --r:            10px;
    --r-sm:         6px;
    --sh:           0 1px 3px rgba(60,20,120,.07), 0 1px 2px rgba(60,20,120,.05);
    --sh-md:        0 4px 14px rgba(60,20,120,.10), 0 2px 4px rgba(60,20,120,.06);
    --sh-lg:        0 12px 36px rgba(60,20,120,.15), 0 4px 8px rgba(60,20,120,.08);
}

[data-theme="dark"] {
    --ink:          #EAE6F8;
    --ink-muted:    #9E98B8;
    --ink-faint:    #635D7A;
    --canvas:       #0E0C18;
    --surface:      #17142A;
    --surface-2:    #1E1A30;
    --border:       #2A2540;
    --border-md:    #362F50;
    --purple-light: #1E1440;
    --purple-pale:  #150F2E;
    --sidebar-bg:   #0A0815;
    --sh:           0 1px 3px rgba(0,0,0,.4);
    --sh-md:        0 4px 14px rgba(0,0,0,.45);
    --sh-lg:        0 12px 36px rgba(0,0,0,.55);
}

/* ═══ Reset / Base ════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { height: 100%; }
body {
    font-family: 'Sora', sans-serif;
    background: var(--canvas);
    color: var(--ink);
    height: 100%;
    overflow: hidden;
    transition: background .2s, color .2s;
}

/* ═══ App Shell ═══════════════════════════════════════════════════════ */
.app { display: flex; height: 100vh; overflow: hidden; }

/* ═══ Sidebar ═════════════════════════════════════════════════════════ */
.sidebar {
    width: 256px;
    flex-shrink: 0;
    background: var(--sidebar-bg);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width .25s cubic-bezier(.4,0,.2,1);
    position: relative;
    z-index: 20;
    border-right: 1px solid rgba(255,255,255,.04);
}
.sidebar.collapsed { width: 68px; }
.sidebar-brand {
    padding: 22px 18px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-bottom: 1px solid rgba(255,255,255,.05);
    flex-shrink: 0;
}
.brand-mark {
    width: 34px; height: 34px;
    background: var(--purple);
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.brand-mark .material-icons-round { font-size: 18px !important; color: white; }
.brand-text { overflow: hidden; white-space: nowrap; }
.brand-text h1 {
    font-family: 'Playfair Display', serif;
    font-size: 15px;
    font-weight: 700;
    color: #EAE8E3;
    line-height: 1;
}
.brand-text span {
    font-size: 9px;
    color: var(--sidebar-muted);
    text-transform: uppercase;
    letter-spacing: .14em;
    font-weight: 500;
}
.collapsed .brand-text { opacity: 0; pointer-events: none; }

.sidebar-scroll { flex: 1; overflow-y: auto; overflow-x: hidden; }
.sidebar-scroll::-webkit-scrollbar { width: 4px; }
.sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.08); border-radius: 99px; }

.sidebar-section { padding: 18px 0 6px; }
.sidebar-label {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .14em;
    color: var(--sidebar-muted);
    padding: 0 18px 6px;
    white-space: nowrap;
    overflow: hidden;
    transition: opacity .2s;
}
.collapsed .sidebar-label { opacity: 0; }

.nav-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 18px;
    text-decoration: none;
    color: var(--sidebar-muted);
    font-size: 13px;
    font-weight: 500;
    transition: all .15s;
    border-left: 2px solid transparent;
    white-space: nowrap;
    overflow: hidden;
    position: relative;
}
.nav-item:hover { background: var(--sidebar-item); color: var(--sidebar-text); }
.nav-item.active { background: var(--sidebar-act); color: #C4B5FD; border-left-color: var(--purple); }
.nav-item .ni { font-size: 17px !important; flex-shrink: 0; }
.nav-item .nl { flex: 1; overflow: hidden; text-overflow: ellipsis; }
.nav-item .nb {
    font-size: 10px;
    font-family: 'Fira Code', monospace;
    background: rgba(255,255,255,.06);
    color: var(--sidebar-muted);
    padding: 1px 5px;
    border-radius: 99px;
    flex-shrink: 0;
    transition: opacity .2s;
}
.collapsed .nl, .collapsed .nb, .collapsed .sidebar-label { opacity: 0; pointer-events: none; }

.sidebar-divider {
    height: 1px;
    background: rgba(255,255,255,.05);
    margin: 6px 14px;
}

.sidebar-footer {
    flex-shrink: 0;
    padding: 14px 16px;
    border-top: 1px solid rgba(255,255,255,.05);
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}
.user-avatar {
    width: 32px; height: 32px;
    border-radius: 50%;
    background: var(--purple);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: white;
    flex-shrink: 0;
}
.user-info { overflow: hidden; }
.user-name { font-size: 12px; font-weight: 600; color: var(--sidebar-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.user-role { font-size: 10px; color: var(--sidebar-muted); white-space: nowrap; }
.collapsed .user-info { opacity: 0; }

/* ═══ Main Area ═══════════════════════════════════════════════════════ */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }

/* ═══ Topbar ══════════════════════════════════════════════════════════ */
.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    height: 56px;
    padding: 0 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
    position: relative;
    z-index: 10;
}
.topbar-btn {
    width: 34px; height: 34px;
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    background: transparent;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--ink-faint);
    transition: all .15s;
}
.topbar-btn:hover { background: var(--canvas); color: var(--ink); }
.topbar-btn.on { background: var(--purple-light); color: var(--purple); border-color: #C4B5FD; }
.topbar-btn .material-icons-round { font-size: 17px !important; }

.search-box {
    flex: 1;
    max-width: 480px;
    position: relative;
}
.search-box input {
    width: 100%;
    background: var(--canvas);
    border: 1px solid var(--border);
    border-radius: 99px;
    padding: 7px 14px 7px 38px;
    font-family: 'Sora', sans-serif;
    font-size: 13px;
    color: var(--ink);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.search-box input:focus {
    border-color: var(--purple);
    box-shadow: 0 0 0 3px rgba(124,58,237,.12);
}
.search-box input::placeholder { color: var(--ink-faint); }
.search-icon {
    position: absolute; left: 11px; top: 50%; transform: translateY(-50%);
    color: var(--ink-faint); font-size: 16px !important; pointer-events: none;
}
.search-clear {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    color: var(--ink-faint); cursor: pointer; font-size: 15px !important;
    transition: color .15s; text-decoration: none; display: flex;
}
.search-clear:hover { color: var(--ink); }

.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }

.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px; font-weight: 600;
    border: 1px solid;
    font-family: 'Fira Code', monospace;
}
.status-pill.cached { background: var(--success-bg); color: var(--success-text); border-color: var(--success-bd); }
.status-pill.live   { background: var(--info-bg);    color: var(--info-text);    border-color: var(--info-bd); }
.status-pill .material-icons-round { font-size: 12px !important; }

/* ═══ Content ════════════════════════════════════════════════════════ */
.content { flex: 1; overflow-y: auto; padding: 22px 24px; }
.content::-webkit-scrollbar { width: 5px; }
.content::-webkit-scrollbar-track { background: transparent; }
.content::-webkit-scrollbar-thumb { background: var(--border-md); border-radius: 99px; }

/* ═══ Page Title ═════════════════════════════════════════════════════ */
.page-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 18px;
    gap: 12px;
    flex-wrap: wrap;
}
.page-head-title h2 {
    font-family: 'Playfair Display', serif;
    font-size: 26px;
    line-height: 1;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: 8px;
}
.page-head-title h2 .cat-icon {
    width: 32px; height: 32px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.page-head-title p { font-size: 12px; color: var(--ink-faint); margin-top: 4px; }
.page-head-controls { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ═══ Filters Bar ════════════════════════════════════════════════════ */
.filters-bar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    box-shadow: var(--sh);
}
.filter-group { display: flex; align-items: center; gap: 6px; }
.filter-label { font-size: 11px; color: var(--ink-faint); font-weight: 500; white-space: nowrap; }
.filter-select {
    background: var(--canvas);
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    padding: 5px 26px 5px 10px;
    font-family: 'Sora', sans-serif;
    font-size: 12px;
    color: var(--ink);
    cursor: pointer;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238A909E' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    transition: border-color .15s;
}
.filter-select:focus { border-color: var(--purple); }

.filter-divider { width: 1px; height: 24px; background: var(--border); flex-shrink: 0; }

.view-toggle { display: flex; gap: 2px; background: var(--canvas); padding: 3px; border-radius: var(--r-sm); border: 1px solid var(--border); }
.view-btn {
    width: 28px; height: 28px;
    border: none; border-radius: 5px; cursor: pointer; background: transparent;
    color: var(--ink-faint);
    display: flex; align-items: center; justify-content: center;
    transition: all .15s;
    text-decoration: none;
}
.view-btn:hover { color: var(--ink); }
.view-btn.active { background: var(--surface); color: var(--gold); box-shadow: var(--sh); }
.view-btn .material-icons-round { font-size: 15px !important; }

.per-page-select { font-size: 12px; }

/* ═══ Stats Bar ══════════════════════════════════════════════════════ */
.stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
.stat-tile {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 14px 16px;
    display: flex; align-items: center; gap: 12px;
    box-shadow: var(--sh);
    transition: box-shadow .15s;
}
.stat-tile:hover { box-shadow: var(--sh-md); }
.stat-icon {
    width: 36px; height: 36px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.stat-icon .material-icons-round { font-size: 18px !important; }
.stat-val  { font-size: 21px; font-weight: 600; line-height: 1; color: var(--ink); font-family: 'Fira Code', monospace; }
.stat-desc { font-size: 11px; color: var(--ink-faint); margin-top: 2px; }

/* ═══ Toolbar ════════════════════════════════════════════════════════ */
.toolbar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 9px 14px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px;
    gap: 10px; flex-wrap: wrap;
    box-shadow: var(--sh);
}
.toolbar-left  { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toolbar-right { font-size: 12px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }
.bulk-label { font-size: 12px; color: var(--ink-faint); display: none; align-items: center; gap: 5px; }
.bulk-label.show { display: flex; }
.bulk-label strong { color: var(--gold); }

/* ═══ Buttons ════════════════════════════════════════════════════════ */
.btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 13px;
    border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif;
    font-size: 12px; font-weight: 500;
    cursor: pointer; border: none;
    transition: all .15s; white-space: nowrap; text-decoration: none;
}
.btn .material-icons-round { font-size: 15px !important; }
.btn-gold    { background: var(--purple); color: white; }
.btn-gold:hover { background: var(--purple-md); box-shadow: 0 3px 10px rgba(124,58,237,.4); }
.btn-outline { background: var(--surface); border: 1px solid var(--border); color: var(--ink); }
.btn-outline:hover { background: var(--canvas); border-color: var(--border-md); }
.btn-ghost   { background: transparent; color: var(--ink-faint); }
.btn-ghost:hover { background: var(--canvas); color: var(--ink); }

/* ═══ Articles Grid ══════════════════════════════════════════════════ */
.articles-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
.articles-list { display: flex; flex-direction: column; gap: 10px; }

/* ═══ Article Card – Grid ════════════════════════════════════════════ */
.a-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    overflow: hidden;
    box-shadow: var(--sh);
    transition: transform .2s, box-shadow .2s, border-color .2s;
    display: flex; flex-direction: column;
    position: relative;
}
.a-card:hover { transform: translateY(-3px); box-shadow: var(--sh-lg); }
.a-card.sel  { border-color: var(--purple); }
.a-card.sel::after {
    content: '';
    position: absolute; inset: 0;
    background: rgba(124,58,237,.04);
    pointer-events: none;
    border-radius: var(--r);
}

.card-chk {
    position: absolute; top: 10px; left: 10px; z-index: 5;
    width: 22px; height: 22px;
    border-radius: 5px;
    border: 2px solid rgba(255,255,255,.8);
    background: rgba(0,0,0,.2);
    backdrop-filter: blur(4px);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    opacity: 0;
    transition: opacity .15s, background .15s;
}
.card-chk .material-icons-round { font-size: 13px !important; color: white; display: none; }
.a-card:hover .card-chk,
.a-card.sel .card-chk,
.sel-mode .card-chk { opacity: 1; }
.card-chk.on { background: var(--purple); border-color: var(--purple); }
.card-chk.on .material-icons-round { display: block; }

.card-img {
    height: 172px;
    background: linear-gradient(135deg, var(--surface-2) 0%, var(--border) 100%);
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
}
.card-img img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
.a-card:hover .card-img img { transform: scale(1.04); }
.card-img-ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
.card-img-ph .material-icons-round { font-size: 42px !important; color: var(--border-md); }
.card-img-veil {
    position: absolute; bottom: 0; left: 0; right: 0; height: 56px;
    background: linear-gradient(to top, rgba(0,0,0,.38), transparent);
}

.card-body { padding: 14px; display: flex; flex-direction: column; flex: 1; }
.card-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.cat-chip {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 8px;
    border-radius: 99px;
    font-size: 10px; font-weight: 600;
    text-transform: uppercase; letter-spacing: .05em;
}
.cat-chip .material-icons-round { font-size: 10px !important; }
.ts {
    font-size: 10px;
    font-family: 'Fira Code', monospace;
    color: var(--ink-faint);
    display: flex; align-items: center; gap: 2px;
}
.ts .material-icons-round { font-size: 11px !important; }

.card-title {
    font-family: 'Playfair Display', serif;
    font-size: 15px;
    line-height: 1.45;
    color: var(--ink);
    margin-bottom: 7px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    cursor: pointer;
    transition: color .15s;
}
.card-title:hover { color: var(--gold); }

.card-desc {
    font-size: 12px;
    color: var(--ink-muted);
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
    margin-bottom: 12px;
}

.card-foot {
    display: flex; align-items: center; justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid var(--border);
    margin-top: auto;
}
.card-src { display: flex; align-items: center; gap: 6px; min-width: 0; }
.src-dot  { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
.src-name { font-size: 11px; color: var(--ink-faint); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 120px; }
.rt-tag   { font-size: 10px; color: var(--ink-faint); font-family: 'Fira Code', monospace; display: flex; align-items: center; gap: 2px; }
.rt-tag .material-icons-round { font-size: 11px !important; }

.card-acts { display: flex; gap: 3px; }
.act-btn {
    width: 28px; height: 28px;
    border: 1px solid var(--border);
    border-radius: 5px;
    background: transparent; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--ink-faint);
    transition: all .15s;
}
.act-btn:hover { background: var(--canvas); color: var(--ink); border-color: var(--border-md); }
.act-btn.import:hover { background: var(--success-bg); color: var(--success-text); border-color: var(--success-bd); }
.act-btn.import.done { background: var(--success-bg); color: var(--success-text); border-color: var(--success-bd); cursor: default; }
.act-btn .material-icons-round { font-size: 14px !important; }

/* ═══ Article Card – List ════════════════════════════════════════════ */
.a-list-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 12px 14px;
    display: flex; gap: 12px; align-items: flex-start;
    box-shadow: var(--sh);
    transition: box-shadow .15s, border-color .15s;
    position: relative;
}
.a-list-card:hover { box-shadow: var(--sh-md); }
.a-list-card.sel { border-color: var(--purple); background: var(--purple-light); }

.list-thumb {
    width: 76px; height: 58px;
    border-radius: 6px; object-fit: cover;
    flex-shrink: 0;
}
.list-thumb-ph {
    width: 76px; height: 58px;
    border-radius: 6px;
    background: var(--surface-2);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.list-thumb-ph .material-icons-round { font-size: 20px !important; color: var(--border-md); }
.list-body { flex: 1; min-width: 0; }
.list-title {
    font-family: 'Playfair Display', serif;
    font-size: 14px; line-height: 1.4;
    color: var(--ink); cursor: pointer;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    transition: color .15s;
}
.list-title:hover { color: var(--gold); }
.list-desc { font-size: 11px; color: var(--ink-muted); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list-meta { display: flex; align-items: center; gap: 10px; margin-top: 7px; flex-wrap: wrap; }
.list-acts { display: flex; gap: 4px; flex-shrink: 0; align-self: center; }

/* ═══ Pagination ═════════════════════════════════════════════════════ */
.pagi {
    display: flex; align-items: center; justify-content: space-between;
    margin-top: 20px; gap: 12px; flex-wrap: wrap;
}
.pagi-info { font-size: 12px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }
.pagi-pages { display: flex; gap: 3px; flex-wrap: wrap; }
.pg-btn {
    min-width: 32px; height: 32px;
    padding: 0 8px;
    border: 1px solid var(--border);
    border-radius: var(--r-sm);
    background: var(--surface);
    color: var(--ink-faint);
    font-size: 12px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s; text-decoration: none;
    font-family: 'Sora', sans-serif;
}
.pg-btn:hover { background: var(--canvas); color: var(--ink); }
.pg-btn.active { background: var(--purple); border-color: var(--purple); color: white; font-weight: 600; }
.pg-btn.dis { opacity: .3; pointer-events: none; }
.pg-btn .material-icons-round { font-size: 15px !important; }

/* ═══ Empty / Error States ══════════════════════════════════════════ */
.empty-state {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--r);
    padding: 56px 24px;
    text-align: center;
    box-shadow: var(--sh);
}
.empty-icon-wrap {
    width: 64px; height: 64px;
    border-radius: 50%;
    background: var(--purple-light);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
}
.empty-icon-wrap .material-icons-round { font-size: 30px !important; color: var(--purple); }
.empty-state h3 { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 8px; }
.empty-state p  { font-size: 13px; color: var(--ink-muted); max-width: 300px; margin: 0 auto 18px; }

.error-banner {
    background: var(--error-bg);
    border: 1px solid var(--error-bd);
    border-radius: var(--r);
    padding: 14px 16px;
    display: flex; align-items: flex-start; gap: 10px;
    margin-bottom: 16px;
    box-shadow: var(--sh);
}
.error-banner .material-icons-round { color: #E11D48; margin-top: 1px; flex-shrink: 0; }
.error-banner-body p:first-child { font-size: 13px; font-weight: 600; color: var(--error-text); }
.error-banner-body p:last-child  { font-size: 11px; color: #9F1239; margin-top: 2px; }

/* ═══ Preview Modal ══════════════════════════════════════════════════ */
.modal-bg {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.65);
    backdrop-filter: blur(5px);
    z-index: 100;
    display: none; align-items: center; justify-content: center;
    padding: 24px;
}
.modal-bg.open { display: flex; animation: fadeUp .2s ease; }
@keyframes fadeUp {
    from { opacity: 0; }
    to   { opacity: 1; }
}
.modal {
    background: var(--surface);
    border-radius: 14px;
    width: 100%; max-width: 660px;
    max-height: 88vh;
    overflow: hidden;
    display: flex; flex-direction: column;
    box-shadow: 0 24px 80px rgba(0,0,0,.3);
    animation: modalIn .25s cubic-bezier(.4,0,.2,1);
}
@keyframes modalIn {
    from { transform: translateY(18px) scale(.98); opacity: 0; }
    to   { transform: translateY(0) scale(1);      opacity: 1; }
}
.modal-img-area {
    height: 220px; position: relative; flex-shrink: 0;
    background: var(--surface-2);
    overflow: hidden;
}
.modal-img-area img { width: 100%; height: 100%; object-fit: cover; }
.modal-img-ph { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
.modal-img-ph .material-icons-round { font-size: 52px !important; color: var(--border-md); }
.modal-img-veil {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, transparent 40%, rgba(0,0,0,.5));
}
.modal-close {
    position: absolute; top: 10px; right: 10px;
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(0,0,0,.45); border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: white; transition: background .15s;
}
.modal-close:hover { background: rgba(0,0,0,.7); }
.modal-close .material-icons-round { font-size: 17px !important; }
.modal-body-scroll { padding: 20px 22px; overflow-y: auto; flex: 1; }
.modal-title { font-family: 'Playfair Display', serif; font-size: 20px; line-height: 1.4; margin-bottom: 10px; color: var(--ink); }
.modal-desc  { font-size: 14px; color: var(--ink-muted); line-height: 1.7; margin-bottom: 14px; }
.modal-meta  { display: flex; flex-wrap: wrap; gap: 12px; padding: 12px 0; border-top: 1px solid var(--border); margin-bottom: 14px; }
.modal-meta-item { display: flex; align-items: center; gap: 4px; font-size: 11px; color: var(--ink-faint); }
.modal-meta-item .material-icons-round { font-size: 12px !important; }
.modal-acts { display: flex; gap: 8px; flex-wrap: wrap; padding-top: 12px; border-top: 1px solid var(--border); }

/* ═══ Toasts ═════════════════════════════════════════════════════════ */
.toast-stack {
    position: fixed; top: 64px; right: 14px;
    z-index: 200;
    display: flex; flex-direction: column; gap: 6px;
    pointer-events: none;
}
.toast {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 14px;
    border-radius: var(--r-sm);
    font-size: 12px; font-weight: 500;
    min-width: 240px; max-width: 340px;
    box-shadow: var(--sh-lg);
    pointer-events: all;
    animation: toastSlide .22s ease;
    border: 1px solid;
}
@keyframes toastSlide { from { transform: translateX(16px); opacity: 0; } to { transform: none; opacity: 1; } }
.toast.success { background: var(--success-bg); color: var(--success-text); border-color: var(--success-bd); }
.toast.error   { background: var(--error-bg);   color: var(--error-text);   border-color: var(--error-bd);   }
.toast.info    { background: var(--info-bg);     color: var(--info-text);    border-color: var(--info-bd);    }
.toast.warn    { background: var(--warn-bg);     color: var(--warn-text);    border-color: var(--warn-bd);    }
.toast .material-icons-round { font-size: 15px !important; flex-shrink: 0; }
.toast-msg { flex: 1; }
.toast-x { cursor: pointer; opacity: .6; line-height: 1; flex-shrink: 0; }
.toast-x:hover { opacity: 1; }

/* ═══ Country flag pill ══════════════════════════════════════════════ */
.country-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px;
    background: var(--canvas);
    border: 1px solid var(--border);
    border-radius: 99px;
    font-size: 11px; color: var(--ink-muted);
}

/* ═══ Skeleton ═══════════════════════════════════════════════════════ */
@keyframes shimmer {
    0%   { background-position: -400px 0; }
    100% { background-position:  400px 0; }
}
.skel {
    background: linear-gradient(90deg, var(--border) 25%, var(--canvas) 50%, var(--border) 75%);
    background-size: 400px 100%;
    animation: shimmer 1.3s ease infinite;
    border-radius: 4px;
}

/* ═══ Responsive ═════════════════════════════════════════════════════ */
@media (max-width: 900px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
    .articles-grid { grid-template-columns: 1fr; }
}
@media (max-width: 700px) {
    .sidebar { position: fixed; top: 0; bottom: 0; left: 0; transform: translateX(-100%); z-index: 50; transition: transform .25s; width: 256px !important; }
    .sidebar.mob-open { transform: none; }
    .content { padding: 14px; }
    .stats-row { grid-template-columns: 1fr 1fr; }
    .page-head { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 480px) {
    .stats-row { grid-template-columns: 1fr 1fr; }
}

/* ═══ Spin ════════════════════════════════════════════════════════════ */
@keyframes spin { to { transform: rotate(360deg); } }
.spin-it { animation: spin .8s linear infinite; }
</style>
</head>
<body>
<div class="app">

<!-- ═══════════════════════════════════════════════════════ SIDEBAR ══ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">
            <span class="material-icons-round">newspaper</span>
        </div>
        <div class="brand-text">
            <h1>NewsAPI</h1>
            <span>Live Intelligence</span>
        </div>
    </div>

    <div class="sidebar-scroll">
        <!-- Categories -->
        <div class="sidebar-section">
            <div class="sidebar-label">Categories</div>
            <?php foreach ($config['categories'] as $key => $meta):
                $cnt = 0;
                foreach ($allArticles as $a) {
                    if (($a['category'] ?? $category) === $key) $cnt++;
                }
            ?>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['category' => $key, 'page' => 1])) ?>"
               class="nav-item <?= $category === $key ? 'active' : '' ?>" title="<?= $meta['label'] ?>">
                <span class="material-icons-round ni"><?= $meta['icon'] ?></span>
                <span class="nl"><?= htmlspecialchars($meta['label']) ?></span>
                <?php if ($category === $key && $totalArticles > 0): ?>
                <span class="nb"><?= $totalArticles ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="sidebar-divider"></div>

        <!-- Countries -->
        <div class="sidebar-section">
            <div class="sidebar-label">Country</div>
            <?php foreach (array_slice($config['countries'], 0, 8, true) as $key => $meta): ?>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['country' => $key, 'page' => 1])) ?>"
               class="nav-item <?= $country === $key ? 'active' : '' ?>" title="<?= $meta['name'] ?>">
                <span class="nl" style="font-size:16px"><?= $meta['flag'] ?></span>
                <span class="nl"><?= htmlspecialchars($meta['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($topSources)): ?>
        <div class="sidebar-divider"></div>

                <div class="sidebar-section">
            <div class="sidebar-label">Tools</div>
            <a href="user_dashboard.php" class="nav-item" title="Dashboard">
                <span class="material-icons-round ni">dashboard</span>
                <span class="nl">Dashboard</span>
            </a>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['refresh' => 1, 'page' => 1])) ?>" class="nav-item" title="Refresh Feed">
                <span class="material-icons-round ni">sync</span>
                <span class="nl">Refresh Feed</span>
            </a>
        </div>

        <!-- Tools -->
        <div class="sidebar-divider"></div>
                <!-- Top Sources -->
        <div class="sidebar-section">
            <div class="sidebar-label">Top Sources</div>
            <?php foreach ($topSources as $src => $cnt): ?>
            <div class="nav-item" style="cursor:default" title="<?= htmlspecialchars($src) ?>">
                <span class="material-icons-round ni" style="font-size:13px!important;color:var(--sidebar-muted)">radio_button_checked</span>
                <span class="nl" style="font-size:12px"><?= htmlspecialchars($src) ?></span>
                <span class="nb"><?= $cnt ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    
    </div>

    <div class="sidebar-footer">
        <div class="user-avatar"><?= $currentUserInitial ?></div>
        <div class="user-info">
            <div class="user-name"><?= $currentUser ?></div>
            <div class="user-role"><?= $currentUserRole ?></div>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════════════ MAIN AREA ══ -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <button class="topbar-btn" onclick="toggleSidebar()" title="Toggle sidebar">
            <span class="material-icons-round">menu</span>
        </button>

        <form method="GET" class="search-box" id="searchForm">
            <input type="hidden" name="category"    value="<?= htmlspecialchars($category) ?>">
            <input type="hidden" name="country"     value="<?= htmlspecialchars($country) ?>">
            <input type="hidden" name="sortBy"      value="<?= htmlspecialchars($sortBy) ?>">
            <input type="hidden" name="language"    value="<?= htmlspecialchars($language) ?>">
            <input type="hidden" name="itemsPerPage" value="<?= $itemsPerPage ?>">
            <input type="hidden" name="view"        value="<?= htmlspecialchars($viewMode) ?>">
            <span class="material-icons-round search-icon">search</span>
            <input type="text" name="search" id="searchInput"
                   value="<?= htmlspecialchars($searchQuery) ?>"
                   placeholder="Search headlines, sources, topics…"
                   autocomplete="off"/>
            <?php if ($searchQuery): ?>
            <a href="?<?= buildUrlParams(array_diff_key($baseParams, ['search' => ''])) ?>" class="search-clear">
                <span class="material-icons-round">close</span>
            </a>
            <?php endif; ?>
        </form>

        <!-- Cache pill -->
        <?php if (!empty($newsData)): ?>
        <div class="status-pill <?= $fromCache ? 'cached' : 'live' ?>">
            <span class="material-icons-round"><?= $fromCache ? 'inventory_2' : 'bolt' ?></span>
            <?= $fromCache ? 'Cached' : 'Live' ?>
            <?php if ($fetchDuration !== null): ?>
            <span style="opacity:.6"> · <?= $fetchDuration ?>ms</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="topbar-right">
            <!-- View toggle -->
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['view' => 'grid', 'page' => 1])) ?>"
               class="topbar-btn <?= $viewMode === 'grid' ? 'on' : '' ?>" title="Grid view">
                <span class="material-icons-round">grid_view</span>
            </a>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['view' => 'list', 'page' => 1])) ?>"
               class="topbar-btn <?= $viewMode === 'list' ? 'on' : '' ?>" title="List view">
                <span class="material-icons-round">view_list</span>
            </a>

            <!-- Select mode -->
            <button class="topbar-btn" id="selModeBtn" onclick="toggleSelectMode()" title="Bulk select">
                <span class="material-icons-round">checklist</span>
            </button>

            <!-- Dark mode -->
            <button class="topbar-btn" id="darkBtn" onclick="toggleDark()" title="Toggle dark mode">
                <span class="material-icons-round">dark_mode</span>
            </button>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content" id="contentArea">

        <!-- Page head -->
        <div class="page-head">
            <div class="page-head-title">
                <h2>
                    <div class="cat-icon" style="background:<?= $activeCategoryMeta['color'] ?>1A">
                        <span class="material-icons-round" style="font-size:18px!important;color:<?= $activeCategoryMeta['color'] ?>"><?= $activeCategoryMeta['icon'] ?></span>
                    </div>
                    <?= htmlspecialchars($activeCategoryMeta['label']) ?>
                    <?php if ($searchQuery): ?>
                    <span style="font-family:'Sora',sans-serif;font-size:15px;font-weight:400;color:var(--ink-faint)">· "<?= htmlspecialchars($searchQuery) ?>"</span>
                    <?php endif; ?>
                </h2>
                <p>
                    <?= $activeCountryMeta['flag'] ?> <?= $activeCountryMeta['name'] ?>
                    &nbsp;·&nbsp; <?= $totalArticles ?> articles
                    &nbsp;·&nbsp; <?= date('F j, Y') ?>
                    <?php if ($fromCache && !empty($newsData['cached_at'])): ?>
                    &nbsp;·&nbsp; Cached <?= date('g:i A', strtotime($newsData['cached_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="page-head-controls">
                <select class="filter-select" onchange="changeSortBy(this.value)" title="Sort by">
                    <?php foreach ($config['sort_options'] ?? ['publishedAt' => 'Latest', 'popularity' => 'Popularity', 'relevancy' => 'Relevancy'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $sortBy === $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" onchange="changePerPage(this.value)">
                    <?php foreach ($config['pagination']['allowed_items_per_page'] as $n): ?>
                    <option value="<?= $n ?>" <?= $itemsPerPage == $n ? 'selected' : '' ?>><?= $n ?>/page</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <div class="filter-group">
                <span class="filter-label">Country</span>
                <select class="filter-select" onchange="changeFilter('country', this.value)">
                    <?php foreach ($config['countries'] as $key => $meta): ?>
                    <option value="<?= $key ?>" <?= $country === $key ? 'selected' : '' ?>>
                        <?= $meta['flag'] ?> <?= htmlspecialchars($meta['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-divider"></div>
            <div class="filter-group">
                <span class="filter-label">Language</span>
                <select class="filter-select" onchange="changeFilter('language', this.value)">
                    <?php foreach ($config['languages'] as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $language === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-divider" style="margin-left:auto"></div>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['refresh' => 1, 'page' => 1])) ?>"
               class="btn btn-outline" style="padding:5px 10px;font-size:11px">
                <span class="material-icons-round">sync</span> Refresh
            </a>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-tile">
                <div class="stat-icon" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="color:var(--purple)">article</span>
                </div>
                <div>
                    <div class="stat-val"><?= $totalArticles ?></div>
                    <div class="stat-desc">Total Articles</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon" style="background:var(--info-bg)">
                    <span class="material-icons-round" style="color:var(--info-text)">source</span>
                </div>
                <div>
                    <div class="stat-val"><?= count($sourceMap) ?></div>
                    <div class="stat-desc">Sources</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon" style="background:var(--success-bg)">
                    <span class="material-icons-round" style="color:var(--success-text)">auto_stories</span>
                </div>
                <div>
                    <div class="stat-val"><?= $totalPages ?></div>
                    <div class="stat-desc">Pages</div>
                </div>
            </div>
            <div class="stat-tile">
                <div class="stat-icon" style="background:var(--purple-pale)">
                    <span class="material-icons-round" style="color:var(--purple-md)"><?= $fromCache ? 'inventory_2' : 'bolt' ?></span>
                </div>
                <div>
                    <div class="stat-val" style="font-size:15px"><?= $fromCache ? 'Cache' : 'Live' ?></div>
                    <div class="stat-desc"><?= $fetchDuration !== null ? $fetchDuration.'ms' : 'Source' ?></div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="bulk-label" id="bulkLabel">
                    <span class="material-icons-round" style="font-size:15px!important">check_box</span>
                    <strong id="selCount">0</strong> selected
                </div>
                <button class="btn btn-gold" id="importSelBtn" style="display:none" onclick="importSelected()">
                    <span class="material-icons-round">cloud_download</span>
                    <span id="importSelLabel">Import Selected</span>
                </button>
                <button class="btn btn-outline" onclick="importAll()">
                    <span class="material-icons-round">cloud_download</span>
                    Import All (<?= count($paginatedArticles) ?>)
                </button>
            </div>
            <div class="toolbar-right">
                <?= ($offset ?? 0) + 1 ?>–<?= min(($offset ?? 0) + $itemsPerPage, $totalArticles) ?> of <?= number_format($totalArticles) ?>
            </div>
        </div>

        <!-- Search context -->
        <?php if (!empty($searchQuery)): ?>
        <div style="background:var(--purple-light);border:1px solid var(--info-bd);border-radius:var(--r);padding:11px 14px;display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;box-shadow:var(--sh)">
            <div style="display:flex;align-items:center;gap:7px">
                <span class="material-icons-round" style="color:var(--purple);font-size:17px!important">search</span>
                <span style="font-size:13px;color:var(--info-text)">
                    Results for <strong>"<?= htmlspecialchars($searchQuery) ?>"</strong>
                    <?php if ($totalArticles > 0): ?><span style="opacity:.7"> · <?= $totalArticles ?> found</span><?php endif; ?>
                </span>
            </div>
            <a href="?<?= buildUrlParams(array_diff_key($baseParams, ['search' => ''])) ?>"
               style="font-size:12px;color:var(--info-text);display:flex;align-items:center;gap:3px;text-decoration:none;font-weight:500">
                <span class="material-icons-round" style="font-size:15px!important">close</span> Clear
            </a>
        </div>
        <?php endif; ?>

        <!-- Error -->
        <?php if ($errorMessage): ?>
        <div class="error-banner">
            <span class="material-icons-round">error_outline</span>
            <div class="error-banner-body">
                <p>Something went wrong</p>
                <p><?= $errorMessage ?></p>
            </div>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['refresh' => 1])) ?>"
               style="margin-left:auto;font-size:11px;color:#9F1239;text-decoration:underline;white-space:nowrap">Retry</a>
        </div>
        <?php endif; ?>

        <!-- Empty State -->
        <?php if (empty($paginatedArticles) && !$errorMessage): ?>
        <div class="empty-state">
            <div class="empty-icon-wrap">
                <span class="material-icons-round"><?= $searchQuery ? 'search_off' : 'newspaper' ?></span>
            </div>
            <h3><?= $searchQuery ? 'No Results Found' : 'No Articles Available' ?></h3>
            <p><?= $searchQuery ? "Nothing matched \"" . htmlspecialchars($searchQuery) . "\". Try different keywords." : "No articles are available for the current filters." ?></p>
            <?php if ($searchQuery): ?>
            <a href="?<?= buildUrlParams(array_diff_key($baseParams, ['search' => ''])) ?>" class="btn btn-gold">Clear Search</a>
            <?php else: ?>
            <a href="?<?= buildUrlParams(array_merge($baseParams, ['refresh' => 1])) ?>" class="btn btn-outline">
                <span class="material-icons-round">refresh</span> Refresh
            </a>
            <?php endif; ?>
        </div>

        <?php elseif ($viewMode === 'list'): ?>
        <!-- ── LIST VIEW ── -->
        <div class="articles-list" id="articlesContainer">
            <?php foreach ($paginatedArticles as $i => $article):
                $src   = $article['source']['name'] ?? 'Unknown';
                $color = sourceBadgeColor($src);
                $catM  = getCategoryMeta($category, $config['categories']);
            ?>
            <div class="a-list-card" id="lc-<?= $i ?>" data-index="<?= $i ?>">
                <div class="card-chk" onclick="toggleSel(<?= $i ?>)" id="chk-<?= $i ?>">
                    <span class="material-icons-round">check</span>
                </div>

                <?php if (!empty($article['urlToImage'])): ?>
                <img src="<?= htmlspecialchars($article['urlToImage']) ?>" class="list-thumb"
                     onerror="this.outerHTML='<div class=\'list-thumb-ph\'><span class=\'material-icons-round\'><?= $catM['icon'] ?></span></div>'" alt="">
                <?php else: ?>
                <div class="list-thumb-ph">
                    <span class="material-icons-round"><?= $catM['icon'] ?></span>
                </div>
                <?php endif; ?>

                <div class="list-body">
                    <div class="list-title" onclick='openPreview(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>)'>
                        <?= htmlspecialchars($article['title']) ?>
                    </div>
                    <div class="list-desc"><?= htmlspecialchars($article['description'] ?? 'No description.') ?></div>
                    <div class="list-meta">
                        <span class="cat-chip" style="background:<?= $catM['color'] ?>1A;color:<?= $catM['color'] ?>">
                            <?= htmlspecialchars($catM['label']) ?>
                        </span>
                        <span class="ts">
                            <span class="material-icons-round">schedule</span>
                            <?php
                            $t = strtotime($article['publishedAt'] ?? 'now');
                            $d = time() - $t;
                            echo $d < 3600 ? floor($d/60).'m ago' : ($d < 86400 ? floor($d/3600).'h ago' : date('M j', $t));
                            ?>
                        </span>
                        <span style="font-size:11px;color:var(--ink-faint)"><?= htmlspecialchars($src) ?></span>
                        <span class="rt-tag">
                            <span class="material-icons-round">menu_book</span>
                            <?= articleReadingTime($article) ?>
                        </span>
                    </div>
                </div>

                <div class="list-acts">
                    <button class="act-btn" onclick="window.open('<?= htmlspecialchars($article['url'] ?? '#') ?>','_blank')" title="Open">
                        <span class="material-icons-round">open_in_new</span>
                    </button>
                    <button class="act-btn" onclick='openPreview(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>)' title="Preview">
                        <span class="material-icons-round">visibility</span>
                    </button>
                    <button class="act-btn import" id="ib-<?= $i ?>"
                            onclick='doImport(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>, this)' title="Import">
                        <span class="material-icons-round">cloud_download</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- ── GRID VIEW ── -->
        <div class="articles-grid" id="articlesContainer">
            <?php foreach ($paginatedArticles as $i => $article):
                $src   = $article['source']['name'] ?? 'Unknown';
                $color = sourceBadgeColor($src);
                $catM  = getCategoryMeta($category, $config['categories']);
                $t     = strtotime($article['publishedAt'] ?? 'now');
                $diff  = time() - $t;
                $ago   = $diff < 3600 ? floor($diff/60).'m ago' : ($diff < 86400 ? floor($diff/3600).'h ago' : date('M j', $t));
            ?>
            <div class="a-card" id="card-<?= $i ?>" data-index="<?= $i ?>">
                <div class="card-chk" onclick="toggleSel(<?= $i ?>)" id="chk-<?= $i ?>">
                    <span class="material-icons-round">check</span>
                </div>

                <div class="card-img">
                    <?php if (!empty($article['urlToImage'])): ?>
                    <img src="<?= htmlspecialchars($article['urlToImage']) ?>" alt=""
                         onerror="this.parentElement.innerHTML='<div class=\'card-img-ph\'><span class=\'material-icons-round\'><?= $catM['icon'] ?></span></div><div class=\'card-img-veil\'></div>'">
                    <?php else: ?>
                    <div class="card-img-ph">
                        <span class="material-icons-round"><?= $catM['icon'] ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="card-img-veil"></div>
                </div>

                <div class="card-body">
                    <div class="card-row">
                        <span class="cat-chip" style="background:<?= $catM['color'] ?>1A;color:<?= $catM['color'] ?>">
                            <span class="material-icons-round"><?= $catM['icon'] ?></span>
                            <?= htmlspecialchars($catM['label']) ?>
                        </span>
                        <span class="ts">
                            <span class="material-icons-round">schedule</span>
                            <?= $ago ?>
                        </span>
                    </div>

                    <div class="card-title" onclick='openPreview(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>)'
                         title="<?= htmlspecialchars($article['title']) ?>">
                        <?= htmlspecialchars($article['title']) ?>
                    </div>

                    <div class="card-desc"><?= htmlspecialchars($article['description'] ?? 'No description available.') ?></div>

                    <div class="card-foot">
                        <div class="card-src">
                            <div class="src-dot" style="background:<?= $color ?>"></div>
                            <span class="src-name" title="<?= htmlspecialchars($src) ?>"><?= htmlspecialchars($src) ?></span>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px">
                            <span class="rt-tag"><?= articleReadingTime($article) ?></span>
                            <div class="card-acts">
                                <button class="act-btn" onclick='openPreview(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>)' title="Preview">
                                    <span class="material-icons-round">visibility</span>
                                </button>
                                <button class="act-btn" onclick="window.open('<?= htmlspecialchars($article['url'] ?? '#') ?>','_blank')" title="Open original">
                                    <span class="material-icons-round">open_in_new</span>
                                </button>
                                <button class="act-btn import" id="ib-<?= $i ?>"
                                        onclick='doImport(<?= htmlspecialchars(json_encode($article), ENT_QUOTES) ?>, this)' title="Import">
                                    <span class="material-icons-round">cloud_download</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if (isset($paginator) && $totalPages > 1):
            $offset = ($currentPage - 1) * $itemsPerPage;
        ?>
        <div class="pagi">
            <div class="pagi-info">
                Page <?= $currentPage ?> / <?= $totalPages ?>
                &nbsp;·&nbsp; <?= number_format($totalArticles) ?> articles
            </div>
            <div class="pagi-pages">
                <a href="?<?= buildUrlParams(array_merge($baseParams, ['page' => 1])) ?>"
                   class="pg-btn <?= $currentPage <= 1 ? 'dis' : '' ?>">
                    <span class="material-icons-round">first_page</span>
                </a>
                <a href="?<?= buildUrlParams(array_merge($baseParams, ['page' => max(1,$currentPage-1)])) ?>"
                   class="pg-btn <?= $currentPage <= 1 ? 'dis' : '' ?>">
                    <span class="material-icons-round">chevron_left</span>
                </a>
                <?php
                $sp = max(1, $currentPage - 2);
                $ep = min($totalPages, $currentPage + 2);
                if ($sp > 1) echo '<span style="padding:0 2px;color:var(--ink-faint);line-height:32px;font-size:12px">…</span>';
                for ($pg = $sp; $pg <= $ep; $pg++): ?>
                <a href="?<?= buildUrlParams(array_merge($baseParams, ['page' => $pg])) ?>"
                   class="pg-btn <?= $pg === $currentPage ? 'active' : '' ?>"><?= $pg ?></a>
                <?php endfor;
                if ($ep < $totalPages) echo '<span style="padding:0 2px;color:var(--ink-faint);line-height:32px;font-size:12px">…</span>';
                ?>
                <a href="?<?= buildUrlParams(array_merge($baseParams, ['page' => min($totalPages,$currentPage+1)])) ?>"
                   class="pg-btn <?= $currentPage >= $totalPages ? 'dis' : '' ?>">
                    <span class="material-icons-round">chevron_right</span>
                </a>
                <a href="?<?= buildUrlParams(array_merge($baseParams, ['page' => $totalPages])) ?>"
                   class="pg-btn <?= $currentPage >= $totalPages ? 'dis' : '' ?>">
                    <span class="material-icons-round">last_page</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main -->
</div><!-- /app -->

<!-- ════════════════════════════════════════════ PREVIEW MODAL ══ -->
<div class="modal-bg" id="previewModal" onclick="closeModal(event)">
    <div class="modal">
        <div class="modal-img-area">
            <img src="" id="mImg" alt="" style="display:none">
            <div class="modal-img-ph" id="mPh">
                <span class="material-icons-round">newspaper</span>
            </div>
            <div class="modal-img-veil"></div>
            <button class="modal-close" onclick="closePreview()">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="modal-body-scroll">
            <div id="mCat" style="margin-bottom:8px"></div>
            <h2 class="modal-title" id="mTitle"></h2>
            <p class="modal-desc" id="mDesc"></p>
            <div class="modal-meta" id="mMeta"></div>
            <div class="modal-acts" id="mActs"></div>
        </div>
    </div>
</div>

<!-- Toasts -->
<div class="toast-stack" id="toastStack"></div>

<script>
const articlesData = <?= json_encode(array_values($paginatedArticles), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const catMeta = <?= json_encode($config['categories']) ?>;
const currCat = <?= json_encode($category) ?>;

let selMode = false;
let selected = new Set();

// ── Sidebar ────────────────────────────────────────────
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 700) {
        sb.classList.toggle('mob-open');
    } else {
        sb.classList.toggle('collapsed');
        localStorage.setItem('sbCollapsed', sb.classList.contains('collapsed'));
    }
}
(function() {
    if (localStorage.getItem('sbCollapsed') === 'true' && window.innerWidth > 700) {
        document.getElementById('sidebar').classList.add('collapsed');
    }
})();

// Mobile overlay close
document.addEventListener('click', e => {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 700 && sb.classList.contains('mob-open') &&
        !sb.contains(e.target) && !e.target.closest('button[onclick*=toggleSidebar]')) {
        sb.classList.remove('mob-open');
    }
});

// ── Dark Mode ──────────────────────────────────────────
function toggleDark() {
    const html = document.documentElement;
    const dark = html.dataset.theme === 'dark';
    html.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('theme', dark ? 'light' : 'dark');
    document.getElementById('darkBtn').querySelector('.material-icons-round').textContent =
        dark ? 'dark_mode' : 'light_mode';
}
(function() {
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.dataset.theme = t;
    const btn = document.getElementById('darkBtn');
    if (btn) btn.querySelector('.material-icons-round').textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
})();

// ── Filter helpers ─────────────────────────────────────
function changeFilter(key, val) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}
function changeSortBy(val) { changeFilter('sortBy', val); }
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('itemsPerPage', val);
    url.searchParams.set('page', 1);
    window.location.href = url.toString();
}

// ── Select Mode ────────────────────────────────────────
function toggleSelectMode() {
    selMode = !selMode;
    const btn = document.getElementById('selModeBtn');
    const cont = document.getElementById('articlesContainer');
    btn.classList.toggle('on', selMode);
    if (cont) cont.classList.toggle('sel-mode', selMode);
    if (!selMode) {
        selected.clear();
        document.querySelectorAll('.a-card.sel,.a-list-card.sel').forEach(c => c.classList.remove('sel'));
        document.querySelectorAll('.card-chk.on').forEach(c => c.classList.remove('on'));
        updateBulkUI();
    }
}

function toggleSel(idx) {
    if (!selMode) { selMode = true; toggleSelectMode(); selMode = true; }
    const card = document.getElementById('card-' + idx) || document.getElementById('lc-' + idx);
    const chk  = document.getElementById('chk-' + idx);
    if (selected.has(idx)) {
        selected.delete(idx);
        card?.classList.remove('sel');
        chk?.classList.remove('on');
    } else {
        selected.add(idx);
        card?.classList.add('sel');
        chk?.classList.add('on');
    }
    updateBulkUI();
}

function updateBulkUI() {
    const n = selected.size;
    const lbl  = document.getElementById('bulkLabel');
    const ibtn = document.getElementById('importSelBtn');
    const ilbl = document.getElementById('importSelLabel');
    document.getElementById('selCount').textContent = n;
    lbl.classList.toggle('show', n > 0);
    ibtn.style.display = n > 0 ? 'inline-flex' : 'none';
    if (ilbl) ilbl.textContent = `Import ${n} Selected`;
}

// ── Preview Modal ──────────────────────────────────────
function openPreview(article) {
    const modal = document.getElementById('previewModal');
    const img   = document.getElementById('mImg');
    const ph    = document.getElementById('mPh');

    if (article.urlToImage) {
        img.src = article.urlToImage;
        img.style.display = 'block';
        ph.style.display  = 'none';
        img.onerror = () => { img.style.display='none'; ph.style.display='flex'; };
    } else {
        img.style.display = 'none';
        ph.style.display  = 'flex';
    }

    const cm = catMeta[currCat] || { icon:'article', color:'#64748B', label: currCat };
    document.getElementById('mCat').innerHTML =
        `<span class="cat-chip" style="background:${cm.color}1A;color:${cm.color}">
            <span class="material-icons-round">${cm.icon}</span>${cm.label}
         </span>`;

    document.getElementById('mTitle').textContent = article.title || 'No title';
    document.getElementById('mDesc').textContent  = article.description || 'No description available.';

    const src  = article.source?.name || 'Unknown';
    const ts   = article.publishedAt ? new Date(article.publishedAt).toLocaleDateString('en-US', {year:'numeric',month:'long',day:'numeric'}) : 'Unknown date';
    const auth = article.author;
    const rt   = readingTime(article.description || '');

    let meta = `<span class="modal-meta-item"><span class="material-icons-round">source</span>${esc(src)}</span>`;
    if (auth) meta += `<span class="modal-meta-item"><span class="material-icons-round">person</span>${esc(auth)}</span>`;
    meta += `<span class="modal-meta-item"><span class="material-icons-round">calendar_today</span>${ts}</span>`;
    meta += `<span class="modal-meta-item"><span class="material-icons-round">menu_book</span>${rt}</span>`;
    if (article.url) meta += `<span class="modal-meta-item"><span class="material-icons-round">language</span>${esc(articleDomain(article.url))}</span>`;
    document.getElementById('mMeta').innerHTML = meta;

    document.getElementById('mActs').innerHTML = `
        <button class="btn btn-gold" onclick="window.open('${esc(article.url)}','_blank')">
            <span class="material-icons-round">open_in_new</span> Read Full Article
        </button>
        <button class="btn btn-outline" id="modalImportBtn" onclick='handleModalImport(${JSON.stringify(article).replace(/'/g,"\\'")})'> 
            <span class="material-icons-round">cloud_download</span> Import
        </button>
        <button class="btn btn-ghost" onclick="copyUrl('${esc(article.url)}')">
            <span class="material-icons-round">link</span> Copy URL
        </button>
    `;

    modal.classList.add('open');
}

function closePreview() {
    document.getElementById('previewModal').classList.remove('open');
}
function closeModal(e) {
    if (e.target === document.getElementById('previewModal')) closePreview();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closePreview(); });

function handleModalImport(article) {
    const btn = document.getElementById('modalImportBtn');
    doImport(article, null, () => {
        if (btn) {
            btn.innerHTML = '<span class="material-icons-round">check_circle</span> Imported';
            btn.style.background = 'var(--purple)';
            btn.style.color = 'white';
            btn.style.border = 'none';
            btn.disabled = true;
        }
    });
}

// ── Import ─────────────────────────────────────────────
function doImport(article, btnEl, onOk) {
    if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<span class="material-icons-round spin-it">refresh</span>'; }

    fetch('news_import/save_article.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: article.title,
            content: article.description || article.content || article.title,
            category: currCat,
            source: article.source?.name || '',
            author: article.author || '',
            published_at: article.publishedAt || '',
            url: article.url || ''
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            toast(data.message, 'success');
            if (btnEl) { btnEl.classList.add('done'); btnEl.innerHTML = '<span class="material-icons-round">check_circle</span>'; }
            if (onOk) onOk();
        } else {
            const isEx = (data.message||'').includes('already exists');
            toast(data.message, isEx ? 'warn' : 'error');
            if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<span class="material-icons-round">cloud_download</span>'; }
        }
    })
    .catch(() => {
        toast('Import failed. Try again.', 'error');
        if (btnEl) { btnEl.disabled = false; btnEl.innerHTML = '<span class="material-icons-round">cloud_download</span>'; }
    });
}

function importAll() {
    if (!articlesData.length) { toast('No articles to import', 'warn'); return; }
    if (!confirm(`Import all ${articlesData.length} visible articles?`)) return;
    batchImport(articlesData.map((_, i) => i));
}

function importSelected() {
    if (!selected.size) { toast('No articles selected', 'warn'); return; }
    const idxs = [...selected];
    if (!confirm(`Import ${idxs.length} selected article${idxs.length > 1 ? 's' : ''}?`)) return;
    batchImport(idxs);
}

function batchImport(indices) {
    let done = 0, ok = 0, ex = 0, fail = 0;
    toast(`Importing ${indices.length} article${indices.length > 1 ? 's' : ''}…`, 'info');

    indices.forEach((idx, pos) => {
        const art = articlesData[idx];
        const btn = document.getElementById('ib-' + idx);
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="material-icons-round spin-it">refresh</span>'; }

        setTimeout(() => {
            fetch('news_import/save_article.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: art.title,
                    content: art.description || art.content || art.title,
                    category: currCat,
                    source: art.source?.name || '',
                    author: art.author || '',
                    published_at: art.publishedAt || '',
                    url: art.url || ''
                })
            })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    ok++;
                    if (btn) { btn.classList.add('done'); btn.innerHTML = '<span class="material-icons-round">check_circle</span>'; }
                } else if ((d.message||'').includes('already exists')) {
                    ex++;
                    if (btn) { btn.innerHTML = '<span class="material-icons-round" style="color:var(--purple)">info</span>'; }
                } else {
                    fail++;
                    if (btn) { btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">cloud_download</span>'; }
                }
                done++;
                if (done === indices.length) {
                    let msg = `✓ ${ok} imported`;
                    if (ex)   msg += `, ${ex} already existed`;
                    if (fail) msg += `, ${fail} failed`;
                    toast(msg, fail ? 'warn' : 'success');
                }
            })
            .catch(() => { fail++; done++; if (btn) { btn.disabled=false; btn.innerHTML='<span class="material-icons-round">cloud_download</span>'; } });
        }, pos * 140);
    });
}

// ── Toasts ─────────────────────────────────────────────
function toast(msg, type = 'info') {
    const icons = { success:'check_circle', error:'error', warn:'warning', info:'info' };
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span class="material-icons-round">${icons[type]||'info'}</span>
        <span class="toast-msg">${msg}</span>
        <span class="toast-x material-icons-round" onclick="this.parentElement.remove()">close</span>`;
    document.getElementById('toastStack').appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = 'opacity .3s'; }, 4500);
    setTimeout(() => el.remove(), 5000);
}

// ── Utilities ──────────────────────────────────────────
function copyUrl(url) {
    navigator.clipboard.writeText(url)
        .then(() => toast('URL copied to clipboard', 'success'))
        .catch(() => toast('Copy failed', 'error'));
}
function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}
function readingTime(text) {
    return Math.max(1, Math.ceil((text||'').trim().split(/\s+/).length / 200)) + ' min read';
}
function articleDomain(url) {
    try { return new URL(url).hostname.replace(/^www\./,''); } catch { return url; }
}

// ── Debounced search ───────────────────────────────────
let searchTimer;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.getElementById('searchForm').submit(), 620);
});

// ── Scroll-to-top ──────────────────────────────────────
const contentEl = document.getElementById('contentArea');
const scrollBtn = document.createElement('button');
scrollBtn.innerHTML = '<span class="material-icons-round">arrow_upward</span>';
scrollBtn.className = 'topbar-btn';
scrollBtn.style.cssText = 'position:fixed;bottom:24px;right:22px;z-index:40;display:none;background:var(--purple);border-color:var(--purple);color:white;border-radius:50%;width:40px;height:40px;box-shadow:var(--sh-lg)';
scrollBtn.title = 'Back to top';
scrollBtn.onclick = () => contentEl.scrollTo({ top: 0, behavior: 'smooth' });
document.body.appendChild(scrollBtn);
contentEl.addEventListener('scroll', () => {
    scrollBtn.style.display = contentEl.scrollTop > 300 ? 'flex' : 'none';
});
</script>
</body>
</html>