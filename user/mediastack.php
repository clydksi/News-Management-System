<?php
require '../auth.php';
require '../db.php';

// -------------------------------
// Config / Inputs
// -------------------------------
$apiKey = "8d0080057fae49a0ed9a89ba7c1b5c24";
$section = $_GET['section'] ?? 'general';
$itemsPerPage = (int)($_GET['itemsPerPage'] ?? 12);
$currentPage = (int)($_GET['page'] ?? 1);
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'newest';
$viewMode = $_GET['view'] ?? 'grid';
$forceRefresh = isset($_GET['refresh']);

// TODAY'S DATE - Core of the new system
$today = date('Y-m-d');
$todayFormatted = date('F j, Y');

// Cache settings
$cacheDir = '../cache/mediastack/';
$cacheLifetime = 86400;
$articlesPerFetch = 100;

if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// -------------------------------
// Cache Management Functions
// -------------------------------
function getCachedData($cacheKey, $cacheDir, $cacheLifetime, $today) {
    $cacheFile = $cacheDir . md5($cacheKey) . '.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        $cacheDate = $cached['cache_date'] ?? null;
        $isSameDay = ($cacheDate === $today);
        $notExpired = (time() - filemtime($cacheFile)) < $cacheLifetime;
        if ($isSameDay && $notExpired) {
            $cached['from_cache'] = true;
            $cached['cached_at'] = date('Y-m-d H:i:s', filemtime($cacheFile));
            $cached['expires_at'] = date('Y-m-d H:i:s', strtotime('tomorrow 00:00:00'));
            return $cached;
        } else {
            unlink($cacheFile);
        }
    }
    return null;
}

function setCachedData($cacheKey, $data, $cacheDir, $today) {
    $cacheFile = $cacheDir . md5($cacheKey) . '.json';
    $data['cache_date'] = $today;
    file_put_contents($cacheFile, json_encode($data));
}

function clearCache($cacheDir, $section = null) {
    $pattern = $section ? "{$cacheDir}*{$section}*.json" : "{$cacheDir}*.json";
    foreach (glob($pattern) as $file) {
        if (is_file($file)) unlink($file);
    }
}

if ($forceRefresh) clearCache($cacheDir, $section);

// -------------------------------
// Fetch / Cache Logic
// -------------------------------
$cacheKey = "mediastack_{$today}_{$section}_" . ($searchQuery ? md5($searchQuery) : 'all');
$cachedData = getCachedData($cacheKey, $cacheDir, $cacheLifetime, $today);

if ($cachedData !== null && !$forceRefresh) {
    $data = $cachedData;
    $fromCache = true;
} else {
    $endpoint = "https://api.mediastack.com/v1/news";
    $params = [
        'access_key' => $apiKey,
        'languages' => 'en',
        'categories' => $section,
        'limit' => $articlesPerFetch,
        'offset' => 0,
        'date' => $today,
        'sort' => 'published_desc'
    ];
    if ($searchQuery) $params['keywords'] = $searchQuery;

    $url = $endpoint . '?' . http_build_query($params);
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['User-Agent: NewsAggregator/1.0']
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode == 401) die("❌ Unauthorized: Check your MediaStack API key.");
    if ($httpCode == 429) {
        $oldCacheFiles = glob($cacheDir . "*.json");
        if (!empty($oldCacheFiles)) {
            usort($oldCacheFiles, fn($a, $b) => filemtime($b) - filemtime($a));
            $oldCache = json_decode(file_get_contents($oldCacheFiles[0]), true);
            if ($oldCache) {
                $oldCache['from_cache'] = true;
                $oldCache['cache_warning'] = 'Rate limit reached. Showing cached data.';
                $data = $oldCache;
                $fromCache = true;
                goto skip_api_fetch;
            }
        }
        die("❌ API Rate Limit Exceeded.");
    }
    if ($httpCode !== 200) die("❌ API Error (HTTP $httpCode): " . ($curlError ?: "Unable to fetch news"));

    $data = json_decode($response, true);
    if (!$data || !isset($data['data'])) {
        $errorMsg = isset($data['error']) ? $data['error']['message'] : 'Invalid API response';
        die("❌ API Error: " . $errorMsg);
    }

    if (isset($data['data']) && is_array($data['data'])) {
        $data['data'] = array_values(array_filter($data['data'], function($article) use ($today) {
            if (!isset($article['published_at'])) return false;
            return date('Y-m-d', strtotime($article['published_at'])) === $today;
        }));
        $data['filtered_count'] = count($data['data']);
    }

    $data['from_cache'] = false;
    $data['fetched_at'] = date('Y-m-d H:i:s');
    $data['news_date'] = $today;
    setCachedData($cacheKey, $data, $cacheDir, $today);
    $fromCache = false;
}

skip_api_fetch:

// -------------------------------
// Sort Articles
// -------------------------------
$allArticles = $data['data'] ?? [];

usort($allArticles, function($a, $b) use ($sortBy) {
    switch ($sortBy) {
        case 'oldest':
            return strtotime($a['published_at'] ?? 0) - strtotime($b['published_at'] ?? 0);
        case 'source':
            return strcmp($a['source'] ?? '', $b['source'] ?? '');
        default: // newest
            return strtotime($b['published_at'] ?? 0) - strtotime($a['published_at'] ?? 0);
    }
});

// Build category stats from full article set
$categoryStats = [];
foreach ($allArticles as $art) {
    $cat = $art['category'] ?? 'general';
    $categoryStats[$cat] = ($categoryStats[$cat] ?? 0) + 1;
}

// Source breakdown (top 5)
$sourceCounts = [];
foreach ($allArticles as $art) {
    $src = $art['source'] ?? 'Unknown';
    $sourceCounts[$src] = ($sourceCounts[$src] ?? 0) + 1;
}
arsort($sourceCounts);
$topSources = array_slice($sourceCounts, 0, 5, true);

// Pagination
$totalItems = count($allArticles);
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedNews = array_slice($allArticles, $offset, $itemsPerPage);
$totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;

// -------------------------------
// Helper Functions
// -------------------------------
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function getPaginationUrl($page, $section, $itemsPerPage, $search = '', $sort = 'newest', $view = 'grid') {
    $params = compact('page', 'section', 'itemsPerPage', 'sort', 'view');
    if ($search) $params['search'] = $search;
    return '?' . http_build_query($params);
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M d', $time);
}

function readingTime($text) {
    $words = str_word_count(strip_tags($text ?? ''));
    $minutes = max(1, ceil($words / 200));
    return $minutes . ' min read';
}

function getCategoryIcon($cat) {
    $icons = [
        'business' => 'trending_up',
        'health' => 'favorite',
        'sports' => 'sports_soccer',
        'technology' => 'memory',
        'entertainment' => 'movie',
        'science' => 'biotech',
        'general' => 'public',
    ];
    return $icons[$cat] ?? 'article';
}

function getCategoryColor($cat) {
    $colors = [
        'business'      => ['bg' => '#F5F3FF', 'text' => '#5B21B6', 'dot' => '#7C3AED'],
        'health'        => ['bg' => '#FDF4FF', 'text' => '#7E22CE', 'dot' => '#A855F7'],
        'sports'        => ['bg' => '#EDE9FE', 'text' => '#4C1D95', 'dot' => '#6D28D9'],
        'technology'    => ['bg' => '#F0EFFE', 'text' => '#6D28D9', 'dot' => '#8B5CF6'],
        'entertainment' => ['bg' => '#FAF5FF', 'text' => '#9333EA', 'dot' => '#C084FC'],
        'science'       => ['bg' => '#EEE8FD', 'text' => '#5B21B6', 'dot' => '#A78BFA'],
        'general'       => ['bg' => '#F5F3FF', 'text' => '#6B6485', 'dot' => '#A78BFA'],
    ];
    return $colors[$cat] ?? $colors['general'];
}

$categories = ['general','business','entertainment','health','science','sports','technology'];
$username = $_SESSION['username'] ?? 'Admin';
$userRole = ucfirst($_SESSION['role'] ?? 'User');
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Newsroom Dashboard · <?= ucfirst($section) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;1,400&family=DM+Serif+Display:ital@0;1&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<script src="https://cdn.tailwindcss.com"></script>
<style>
:root {
    --bg-base:      #F3F1FA;
    --bg-surface:   #FFFFFF;
    --bg-sidebar:   #130F23;
    --bg-sidebar-hover: #1E1640;
    --text-primary: #13111A;
    --text-muted:   #6B6485;
    --text-sidebar: #D4CFE8;
    --text-sidebar-muted: #6B6485;
    --accent:       #7C3AED;
    --accent-hover: #6D28D9;
    --accent-light: #EDE9FE;
    --border:       #E2DDEF;
    --border-dark:  #C9C2E0;
    --shadow-card:  0 1px 3px rgba(60,20,120,0.07), 0 1px 2px rgba(60,20,120,0.05);
    --shadow-hover: 0 8px 24px rgba(60,20,120,0.13), 0 2px 6px rgba(60,20,120,0.07);
    --radius:       12px;
    --radius-sm:    8px;
}
[data-theme="dark"] {
    --bg-base:      #0E0C18;
    --bg-surface:   #17142A;
    --bg-sidebar:   #0A0815;
    --bg-sidebar-hover: #1A1535;
    --text-primary: #EAE6F8;
    --text-muted:   #9E98B8;
    --border:       #2A2540;
    --border-dark:  #362F50;
    --accent-light: #1E1440;
    --shadow-card:  0 1px 3px rgba(0,0,0,0.3);
    --shadow-hover: 0 8px 24px rgba(0,0,0,0.45);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html {
    height: 100%;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
    text-rendering: optimizeLegibility;
    font-synthesis: none;
}
body {
    font-family: 'DM Sans', sans-serif;
    font-size: 15px;
    line-height: 1.65;
    background: var(--bg-base);
    color: var(--text-primary);
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: background 0.2s, color 0.2s;
}

/* ─── Layout ─────────────────────────────── */
.app-shell { display: flex; height: 100vh; overflow: hidden; }

/* ─── Sidebar ─────────────────────────────── */
.sidebar {
    width: 260px;
    flex-shrink: 0;
    background: var(--bg-sidebar);
    color: var(--text-sidebar);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width 0.25s cubic-bezier(.4,0,.2,1);
    position: relative;
    z-index: 10;
}
.sidebar.collapsed { width: 72px; }
.sidebar-logo {
    padding: 24px 20px 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}
.sidebar-logo-icon {
    width: 36px; height: 36px; flex-shrink: 0;
    background: var(--accent);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
}
.sidebar-logo-text { overflow: hidden; white-space: nowrap; }
.sidebar-logo-text h1 {
    font-family: 'DM Serif Display', serif;
    font-size: 16px;
    color: #F0F0EE;
    line-height: 1;
}
.sidebar-logo-text span {
    font-size: 10px;
    color: var(--text-sidebar-muted);
    text-transform: uppercase;
    letter-spacing: 0.1em;
}
.sidebar-section { padding: 20px 0 8px; }
.sidebar-section-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--text-sidebar-muted);
    padding: 0 20px 8px;
    overflow: hidden;
    white-space: nowrap;
    transition: opacity 0.2s;
}
.sidebar.collapsed .sidebar-section-label { opacity: 0; }
.sidebar-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    text-decoration: none;
    color: var(--text-sidebar-muted);
    font-size: 14px;
    font-weight: 500;
    transition: all 0.15s;
    border-left: 3px solid transparent;
    position: relative;
    white-space: nowrap;
    overflow: hidden;
}
.sidebar-nav-item:hover {
    background: var(--bg-sidebar-hover);
    color: var(--text-sidebar);
}
.sidebar-nav-item.active {
    background: var(--bg-sidebar-hover);
    color: #FFFFFF;
    border-left-color: var(--accent);
}
.sidebar-nav-item .nav-icon {
    font-size: 18px !important;
    flex-shrink: 0;
    width: 20px;
    text-align: center;
}
.sidebar-nav-item .nav-label { flex: 1; overflow: hidden; }
.sidebar-nav-item .nav-badge {
    font-size: 10px;
    font-family: 'JetBrains Mono', monospace;
    background: rgba(255,255,255,0.08);
    color: var(--text-sidebar-muted);
    padding: 1px 6px;
    border-radius: 99px;
    flex-shrink: 0;
    transition: opacity 0.2s;
}
.sidebar.collapsed .nav-badge,
.sidebar.collapsed .nav-label,
.sidebar.collapsed .sidebar-section-label { opacity: 0; pointer-events: none; }

.sidebar-footer {
    margin-top: auto;
    padding: 16px 16px;
    border-top: 1px solid rgba(255,255,255,0.06);
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}
.sidebar-avatar {
    width: 34px; height: 34px; border-radius: 50%;
    background: var(--accent);
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 600; color: white;
    flex-shrink: 0;
}
.sidebar-user { overflow: hidden; }
.sidebar-user-name { font-size: 13px; font-weight: 600; color: var(--text-sidebar); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar-user-role { font-size: 11px; color: var(--text-sidebar-muted); white-space: nowrap; }

/* ─── Main Area ─────────────────────────────── */
.main-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

/* ─── Top Bar ─────────────────────────────── */
.top-bar {
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border);
    padding: 0 24px;
    height: 60px;
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}
.toggle-sidebar-btn {
    width: 36px; height: 36px;
    border: 1px solid var(--border);
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted);
    transition: all 0.15s;
    flex-shrink: 0;
}
.toggle-sidebar-btn:hover { background: var(--bg-base); color: var(--text-primary); }

.search-wrap {
    flex: 1;
    max-width: 440px;
    position: relative;
}
.search-wrap input {
    width: 100%;
    background: var(--bg-base);
    border: 1px solid var(--border);
    border-radius: 99px;
    padding: 8px 16px 8px 40px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
    color: var(--text-primary);
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}
.search-wrap input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(124,58,237,0.14);
}
.search-wrap input::placeholder { color: var(--text-muted); }
.search-icon {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); font-size: 18px !important;
    pointer-events: none;
}
.search-clear {
    position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
    color: var(--text-muted); cursor: pointer; font-size: 16px !important;
    transition: color 0.15s;
}
.search-clear:hover { color: var(--text-primary); }

.top-bar-actions { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.icon-btn {
    width: 36px; height: 36px;
    border: 1px solid var(--border);
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted);
    transition: all 0.15s;
    position: relative;
    text-decoration: none;
}
.icon-btn:hover { background: var(--bg-base); color: var(--text-primary); }
.icon-btn.active { background: var(--accent-light); color: var(--accent); border-color: var(--accent); }
.icon-btn .material-icons-round { font-size: 18px !important; }

/* ─── Content Area ─────────────────────────── */
.content-area { flex: 1; overflow-y: auto; padding: 24px; }

/* ─── Page Header ─────────────────────────── */
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 16px;
    flex-wrap: wrap;
}
.page-header-left h2 {
    font-family: 'DM Serif Display', serif;
    font-size: 28px;
    line-height: 1;
    color: var(--text-primary);
}
.page-header-left p {
    font-size: 13px;
    color: var(--text-muted);
    margin-top: 4px;
}
.page-header-controls { display: flex; align-items: center; gap: 8px; }

/* ─── Pill Select ─────────────────────────── */
.pill-select {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    padding: 7px 32px 7px 12px;
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    color: var(--text-primary);
    cursor: pointer;
    outline: none;
    -webkit-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24'%3E%3Cpath fill='%236B7280' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
}
.pill-select:focus { border-color: var(--accent); }

/* ─── Action Buttons ─────────────────────── */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 14px;
    border-radius: var(--radius-sm);
    font-family: 'DM Sans', sans-serif;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    white-space: nowrap;
    text-decoration: none;
}
.btn .material-icons-round { font-size: 16px !important; }
.btn-primary { background: var(--accent); color: white; }
.btn-primary:hover { background: var(--accent-hover); box-shadow: 0 4px 12px rgba(124,58,237,0.35); }
.btn-secondary { background: var(--bg-surface); border: 1px solid var(--border); color: var(--text-primary); }
.btn-secondary:hover { background: var(--bg-base); }
.btn-ghost { background: transparent; color: var(--text-muted); }
.btn-ghost:hover { color: var(--text-primary); background: var(--bg-base); }

/* ─── Stats Bar ─────────────────────────── */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
.stat-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: var(--shadow-card);
}
.stat-icon {
    width: 38px; height: 38px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px !important;
    flex-shrink: 0;
}
.stat-info { min-width: 0; }
.stat-value { font-size: 22px; font-weight: 600; line-height: 1; color: var(--text-primary); }
.stat-label { font-size: 12px; color: var(--text-muted); margin-top: 2px; white-space: nowrap; }

/* ─── Toolbar ─────────────────────────── */
.toolbar {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 16px;
    flex-wrap: wrap;
    box-shadow: var(--shadow-card);
}
.toolbar-left { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.toolbar-right { display: flex; align-items: center; gap: 8px; }
.bulk-info {
    font-size: 13px; color: var(--text-muted);
    display: none;
    align-items: center; gap: 6px;
}
.bulk-info.visible { display: flex; }
.bulk-info strong { color: var(--accent); }

/* ─── Articles Grid ─────────────────────── */
.articles-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 16px;
}
.articles-list { display: flex; flex-direction: column; gap: 12px; }

/* ─── Article Card ─────────────────────── */
.article-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    box-shadow: var(--shadow-card);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
    position: relative;
    display: flex;
    flex-direction: column;
}
.article-card:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-hover);
}
.article-card.selected { border-color: var(--accent); }
.article-card.selected::before {
    content: '';
    position: absolute; inset: 0;
    background: rgba(124,58,237,0.04);
    pointer-events: none;
    z-index: 0;
    border-radius: var(--radius);
}

.card-checkbox {
    position: absolute;
    top: 10px; left: 10px;
    z-index: 5;
    width: 22px; height: 22px;
    border: 2px solid rgba(255,255,255,0.8);
    border-radius: 5px;
    background: rgba(0,0,0,0.25);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    opacity: 0;
    transition: opacity 0.15s, background 0.15s, border-color 0.15s;
    backdrop-filter: blur(4px);
}
.article-card:hover .card-checkbox,
.article-card.selected .card-checkbox,
.select-mode .card-checkbox { opacity: 1; }
.card-checkbox.checked {
    background: var(--accent);
    border-color: var(--accent);
}
.card-checkbox .material-icons-round { font-size: 14px !important; color: white; display: none; }
.card-checkbox.checked .material-icons-round { display: block; }

.card-image {
    height: 180px;
    background: linear-gradient(135deg, #EDE9FE 0%, #DDD6FE 100%);
    overflow: hidden;
    position: relative;
    flex-shrink: 0;
}
.card-image img {
    width: 100%; height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}
.article-card:hover .card-image img { transform: scale(1.04); }
.card-image-placeholder {
    width: 100%; height: 100%;
    display: flex; align-items: center; justify-content: center;
}
.card-image-placeholder .material-icons-round {
    font-size: 48px !important;
    color: rgba(0,0,0,0.15);
}
.card-image-overlay {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 60px;
    background: linear-gradient(to top, rgba(0,0,0,0.4), transparent);
}

.card-body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; }

.card-meta { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.category-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 99px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.category-badge .material-icons-round { font-size: 11px !important; }
.card-time {
    font-size: 11px;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', monospace;
    display: flex; align-items: center; gap: 3px;
}
.card-time .material-icons-round { font-size: 12px !important; }

.card-title {
    font-family: 'DM Serif Display', serif;
    font-size: 16px;
    line-height: 1.4;
    color: var(--text-primary);
    margin-bottom: 8px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    cursor: pointer;
    transition: color 0.15s;
}
.card-title:hover { color: var(--accent); }

.card-desc {
    font-size: 13px;
    color: var(--text-muted);
    line-height: 1.6;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 12px;
    flex: 1;
}

.card-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 10px;
    border-top: 1px solid var(--border);
    margin-top: auto;
}
.card-source {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px;
    color: var(--text-muted);
    max-width: 130px;
    overflow: hidden;
}
.card-source-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.card-source-name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.card-actions { display: flex; gap: 4px; }
.card-action-btn {
    width: 30px; height: 30px;
    border: 1px solid var(--border);
    border-radius: 6px;
    background: transparent;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: var(--text-muted);
    transition: all 0.15s;
}
.card-action-btn:hover { background: var(--bg-base); color: var(--text-primary); border-color: var(--border-dark); }
.card-action-btn.import-btn:hover { background: #F0FDF4; color: #15803D; border-color: #86EFAC; }
.card-action-btn.import-btn.done {
    background: #F0FDF4;
    color: #15803D;
    border-color: #86EFAC;
    cursor: default;
}
.card-action-btn .material-icons-round { font-size: 15px !important; }

/* ─── List View Card ─────────────────────── */
.article-list-card {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 14px 16px;
    display: flex;
    gap: 14px;
    align-items: flex-start;
    box-shadow: var(--shadow-card);
    transition: transform 0.15s, box-shadow 0.15s;
    position: relative;
}
.article-list-card:hover { transform: translateX(2px); box-shadow: var(--shadow-hover); }
.article-list-card.selected { border-color: var(--accent); background: var(--accent-light); }
.list-thumb {
    width: 80px; height: 60px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
}
.list-thumb-placeholder {
    width: 80px; height: 60px;
    border-radius: 6px;
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.list-thumb-placeholder .material-icons-round { font-size: 22px !important; color: rgba(0,0,0,0.2); }
.list-content { flex: 1; min-width: 0; }
.list-title {
    font-family: 'DM Serif Display', serif;
    font-size: 15px;
    line-height: 1.4;
    color: var(--text-primary);
    cursor: pointer;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.list-title:hover { color: var(--accent); }
.list-desc { font-size: 12px; color: var(--text-muted); margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.list-meta { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
.list-actions { display: flex; gap: 4px; flex-shrink: 0; }

/* ─── Skeleton Loader ─────────────────────── */
@keyframes shimmer {
    0% { background-position: -400px 0; }
    100% { background-position: 400px 0; }
}
.skeleton {
    background: linear-gradient(90deg, var(--border) 25%, var(--bg-base) 50%, var(--border) 75%);
    background-size: 400px 100%;
    animation: shimmer 1.4s ease infinite;
    border-radius: 4px;
}

/* ─── Pagination ─────────────────────────── */
.pagination {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 24px;
    gap: 12px;
    flex-wrap: wrap;
}
.pagination-info { font-size: 13px; color: var(--text-muted); }
.pagination-pages { display: flex; gap: 4px; flex-wrap: wrap; }
.page-btn {
    min-width: 34px; height: 34px;
    padding: 0 10px;
    border: 1px solid var(--border);
    border-radius: var(--radius-sm);
    background: var(--bg-surface);
    color: var(--text-muted);
    font-size: 13px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: all 0.15s;
    text-decoration: none;
    font-family: 'DM Sans', sans-serif;
}
.page-btn:hover { background: var(--bg-base); color: var(--text-primary); }
.page-btn.active { background: var(--accent); border-color: var(--accent); color: white; font-weight: 600; }
.page-btn.disabled { opacity: 0.35; cursor: not-allowed; pointer-events: none; }
.page-btn .material-icons-round { font-size: 16px !important; }

/* ─── Empty State ─────────────────────────── */
.empty-state {
    background: var(--bg-surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 60px 24px;
    text-align: center;
    box-shadow: var(--shadow-card);
}
.empty-state-icon { font-size: 56px !important; color: var(--border-dark); margin-bottom: 16px; }
.empty-state h3 { font-family: 'DM Serif Display', serif; font-size: 22px; margin-bottom: 8px; }
.empty-state p { font-size: 14px; color: var(--text-muted); max-width: 320px; margin: 0 auto 20px; }

/* ─── Modal ─────────────────────────────── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.6);
    backdrop-filter: blur(4px);
    z-index: 100;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 24px;
    animation: fadeIn 0.2s ease;
}
.modal-overlay.open { display: flex; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.modal {
    background: var(--bg-surface);
    border-radius: 16px;
    width: 100%;
    max-width: 680px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 24px 80px rgba(0,0,0,0.25);
    animation: slideUp 0.25s cubic-bezier(.4,0,.2,1);
}
@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to   { transform: translateY(0);    opacity: 1; }
}
.modal-image {
    height: 240px;
    background: linear-gradient(135deg, #EDE9FE, #DDD6FE);
    overflow: hidden;
    flex-shrink: 0;
    position: relative;
}
.modal-image img { width: 100%; height: 100%; object-fit: cover; }
.modal-image-overlay {
    position: absolute; inset: 0;
    background: linear-gradient(to bottom, transparent 50%, rgba(0,0,0,0.5) 100%);
}
.modal-body { padding: 24px; overflow-y: auto; flex: 1; }
.modal-category { margin-bottom: 10px; }
.modal-title {
    font-family: 'DM Serif Display', serif;
    font-size: 22px;
    line-height: 1.4;
    margin-bottom: 10px;
    color: var(--text-primary);
}
.modal-desc { font-size: 15px; color: var(--text-muted); line-height: 1.7; margin-bottom: 16px; }
.modal-meta {
    display: flex; flex-wrap: wrap; gap: 12px;
    font-size: 12px; color: var(--text-muted);
    padding-top: 14px;
    border-top: 1px solid var(--border);
    margin-bottom: 16px;
}
.modal-meta-item { display: flex; align-items: center; gap: 4px; }
.modal-meta-item .material-icons-round { font-size: 13px !important; }
.modal-actions {
    display: flex; gap: 8px; flex-wrap: wrap;
    padding-top: 14px;
    border-top: 1px solid var(--border);
}
.modal-close-btn {
    position: absolute;
    top: 12px; right: 12px;
    width: 34px; height: 34px;
    border-radius: 50%;
    background: rgba(0,0,0,0.4);
    border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    color: white;
    transition: background 0.15s;
}
.modal-close-btn:hover { background: rgba(0,0,0,0.65); }
.modal-close-btn .material-icons-round { font-size: 18px !important; }

/* ─── Toast ─────────────────────────────── */
.toast-container {
    position: fixed; top: 72px; right: 16px;
    z-index: 200;
    display: flex; flex-direction: column;
    gap: 8px; pointer-events: none;
}
.toast {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    border-radius: var(--radius-sm);
    font-size: 13px; font-weight: 500;
    min-width: 260px; max-width: 360px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    pointer-events: all;
    animation: toastIn 0.25s ease;
}
@keyframes toastIn {
    from { transform: translateX(20px); opacity: 0; }
    to   { transform: translateX(0);    opacity: 1; }
}
.toast.success { background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; }
.toast.error   { background: #FFF1F2; color: #BE123C; border: 1px solid #FECDD3; }
.toast.info    { background: #EFF6FF; color: #1D4ED8; border: 1px solid #BFDBFE; }
.toast.warning { background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; }
.toast .material-icons-round { font-size: 16px !important; }
.toast-close { margin-left: auto; cursor: pointer; opacity: 0.6; line-height: 1; }
.toast-close:hover { opacity: 1; }

/* ─── Cache Pill ─────────────────────────── */
.cache-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px;
    border-radius: 99px;
    font-size: 11px; font-weight: 500;
    border: 1px solid;
}
.cache-pill.cached { background: #F0FDF4; color: #15803D; border-color: #86EFAC; }
.cache-pill.live    { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.cache-pill .material-icons-round { font-size: 12px !important; }

/* ─── Scrollbar ─────────────────────────── */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-dark); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

/* ─── Reading time tag ─────────────────── */
.read-time {
    font-size: 11px;
    font-family: 'JetBrains Mono', monospace;
    color: var(--text-muted);
    display: flex; align-items: center; gap: 3px;
}
.read-time .material-icons-round { font-size: 12px !important; }

/* ─── Responsive ─────────────────────────── */
@media (max-width: 768px) {
    .sidebar { position: fixed; top: 0; bottom: 0; left: 0; transform: translateX(-100%); z-index: 50; transition: transform 0.25s; }
    .sidebar.mobile-open { transform: translateX(0); }
    .stats-bar { grid-template-columns: repeat(2, 1fr); }
    .articles-grid { grid-template-columns: 1fr; }
    .content-area { padding: 16px; }
}
@media (max-width: 480px) {
    .stats-bar { grid-template-columns: 1fr 1fr; }
    .page-header { flex-direction: column; align-items: flex-start; }
}
</style>
</head>
<body>
<div class="app-shell">

<!-- ═══════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="sidebar-logo-icon">
            <span class="material-icons-round" style="color:white;font-size:20px!important">newspaper</span>
        </div>
        <div class="sidebar-logo-text">
            <h1>Newsroom</h1>
            <span>MediaStack Feed</span>
        </div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Categories</div>
        <?php foreach ($categories as $cat):
            $color = getCategoryColor($cat);
            $count = $categoryStats[$cat] ?? 0;
        ?>
        <a href="?section=<?= $cat ?>&itemsPerPage=<?= $itemsPerPage ?>&sort=<?= e($sortBy) ?>&view=<?= e($viewMode) ?><?= $searchQuery ? '&search='.urlencode($searchQuery) : '' ?>"
           class="sidebar-nav-item <?= $section === $cat ? 'active' : '' ?>"
           title="<?= ucfirst($cat) ?>">
            <span class="material-icons-round nav-icon"><?= getCategoryIcon($cat) ?></span>
            <span class="nav-label"><?= ucfirst($cat) ?></span>
            <?php if ($count > 0): ?>
            <span class="nav-badge"><?= $count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Tools</div>
        <a href="user_dashboard.php" class="sidebar-nav-item" title="Dashboard">
            <span class="material-icons-round nav-icon">dashboard</span>
            <span class="nav-label">Dashboard</span>
        </a>
        <a href="?section=<?= e($section) ?>&refresh=1" class="sidebar-nav-item" title="Refresh Feed">
            <span class="material-icons-round nav-icon">sync</span>
            <span class="nav-label">Refresh Feed</span>
        </a>
    </div>

    <!-- Source Breakdown -->
    <?php if (!empty($topSources)): ?>
    <div class="sidebar-section" style="overflow:hidden;">
        <div class="sidebar-section-label">Top Sources</div>
        <?php foreach ($topSources as $src => $cnt): ?>
        <div class="sidebar-nav-item" style="cursor:default;">
            <span class="material-icons-round nav-icon" style="font-size:14px!important;color:#6B7280">radio_button_checked</span>
            <span class="nav-label" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;"><?= e($src) ?></span>
            <span class="nav-badge"><?= $cnt ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div class="sidebar-avatar"><?= strtoupper(substr($username, 0, 1)) ?></div>
        <div class="sidebar-user">
            <div class="sidebar-user-name"><?= e($username) ?></div>
            <div class="sidebar-user-role"><?= $userRole ?></div>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════
     MAIN AREA
════════════════════════════════════════════ -->
<div class="main-area">

    <!-- TOP BAR -->
    <div class="top-bar">
        <button class="toggle-sidebar-btn" onclick="toggleSidebar()" title="Toggle sidebar">
            <span class="material-icons-round" style="font-size:18px!important">menu</span>
        </button>

        <form method="GET" class="search-wrap" id="searchForm">
            <input type="hidden" name="section" value="<?= e($section) ?>">
            <input type="hidden" name="itemsPerPage" value="<?= $itemsPerPage ?>">
            <input type="hidden" name="sort" value="<?= e($sortBy) ?>">
            <input type="hidden" name="view" value="<?= e($viewMode) ?>">
            <span class="material-icons-round search-icon">search</span>
            <input type="text" name="search" value="<?= e($searchQuery) ?>"
                   placeholder="Search today's news…" id="searchInput" autocomplete="off"/>
            <?php if ($searchQuery): ?>
            <a href="?section=<?= e($section) ?>&itemsPerPage=<?= $itemsPerPage ?>&sort=<?= e($sortBy) ?>&view=<?= e($viewMode) ?>"
               class="material-icons-round search-clear">close</a>
            <?php endif; ?>
        </form>

        <!-- Cache Status -->
        <div class="cache-pill <?= $fromCache ? 'cached' : 'live' ?>">
            <span class="material-icons-round"><?= $fromCache ? 'cached' : 'bolt' ?></span>
            <span><?= $fromCache ? 'Cached' : 'Live' ?></span>
        </div>

        <div class="top-bar-actions">
            <!-- View toggle -->
            <button class="icon-btn <?= $viewMode === 'grid' ? 'active' : '' ?>"
                    onclick="setView('grid')" title="Grid view">
                <span class="material-icons-round">grid_view</span>
            </button>
            <button class="icon-btn <?= $viewMode === 'list' ? 'active' : '' ?>"
                    onclick="setView('list')" title="List view">
                <span class="material-icons-round">view_list</span>
            </button>

            <!-- Select mode -->
            <button class="icon-btn" onclick="toggleSelectMode()" id="selectModeBtn" title="Select articles">
                <span class="material-icons-round">checklist</span>
            </button>

            <!-- Dark mode -->
            <button class="icon-btn" onclick="toggleDarkMode()" id="darkModeBtn" title="Toggle dark mode">
                <span class="material-icons-round">dark_mode</span>
            </button>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content-area" id="contentArea">

        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-left">
                <h2>
                    <span class="material-icons-round" style="vertical-align:middle;font-size:28px!important;margin-right:8px;color:var(--accent)"><?= getCategoryIcon($section) ?></span><?= ucfirst($section) ?>
                    <?php if ($searchQuery): ?>
                    <span style="font-family:'DM Sans',sans-serif;font-size:16px;font-weight:400;color:var(--text-muted)">· "<?= e($searchQuery) ?>"</span>
                    <?php endif; ?>
                </h2>
                <p>
                    <?= $todayFormatted ?> &nbsp;·&nbsp;
                    <?= $totalItems ?> <?= $totalItems === 1 ? 'article' : 'articles' ?> found
                    <?php if ($fromCache && isset($data['cached_at'])): ?>
                    &nbsp;·&nbsp; Cached at <?= date('g:i A', strtotime($data['cached_at'])) ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="page-header-controls">
                <select class="pill-select" onchange="setSort(this.value)">
                    <option value="newest" <?= $sortBy === 'newest' ? 'selected' : '' ?>>Newest first</option>
                    <option value="oldest" <?= $sortBy === 'oldest' ? 'selected' : '' ?>>Oldest first</option>
                    <option value="source" <?= $sortBy === 'source'  ? 'selected' : '' ?>>By source</option>
                </select>
                <select class="pill-select" onchange="changeItemsPerPage(this.value)">
                    <?php foreach ([6,12,24,48,100] as $n): ?>
                    <option value="<?= $n ?>" <?= $itemsPerPage == $n ? 'selected' : '' ?>><?= $n ?> per page</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-card">
                <div class="stat-icon" style="background:#EDE9FE;color:var(--accent)">
                    <span class="material-icons-round" style="font-size:20px!important">article</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalItems ?></div>
                    <div class="stat-label">Total Articles</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#EFF6FF;color:#3B82F6">
                    <span class="material-icons-round" style="font-size:20px!important">source</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= count($sourceCounts) ?></div>
                    <div class="stat-label">Sources</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#F0FDF4;color:#15803D">
                    <span class="material-icons-round" style="font-size:20px!important">layers</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= $totalPages ?></div>
                    <div class="stat-label">Pages</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" style="background:#FDF4FF;color:#D946EF">
                    <span class="material-icons-round" style="font-size:20px!important">today</span>
                </div>
                <div class="stat-info">
                    <div class="stat-value"><?= date('D') ?></div>
                    <div class="stat-label"><?= date('M j, Y') ?></div>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar" id="toolbar">
            <div class="toolbar-left">
                <div class="bulk-info" id="bulkInfo">
                    <span class="material-icons-round" style="font-size:16px!important">check_box</span>
                    <span><strong id="selectedCount">0</strong> selected</span>
                </div>
                <button class="btn btn-primary" onclick="importSelected()" id="importSelectedBtn" style="display:none">
                    <span class="material-icons-round">cloud_download</span>
                    Import Selected
                </button>
                <button class="btn btn-secondary" onclick="importAllVisible()">
                    <span class="material-icons-round">cloud_download</span>
                    Import All (<?= count($paginatedNews) ?>)
                </button>
            </div>
            <div class="toolbar-right">
                <span style="font-size:12px;color:var(--text-muted);">
                    <?= $offset + 1 ?>–<?= min($offset + $itemsPerPage, $totalItems) ?> of <?= number_format($totalItems) ?>
                </span>
            </div>
        </div>

        <!-- Articles -->
        <?php if (empty($paginatedNews)): ?>
        <div class="empty-state">
            <div><span class="material-icons-round empty-state-icon">search_off</span></div>
            <h3><?= $searchQuery ? 'No Results Found' : 'No Articles Today' ?></h3>
            <p><?= $searchQuery ? "Nothing matched \"".e($searchQuery)."\" in ".ucfirst($section)."." : "No articles available in this section yet. Try refreshing or check back later." ?></p>
            <?php if ($searchQuery): ?>
            <a href="?section=<?= $section ?>&itemsPerPage=<?= $itemsPerPage ?>" class="btn btn-primary">Clear Search</a>
            <?php else: ?>
            <a href="?section=<?= $section ?>&itemsPerPage=<?= $itemsPerPage ?>&refresh=1" class="btn btn-secondary">
                <span class="material-icons-round">refresh</span> Refresh
            </a>
            <?php endif; ?>
        </div>

        <?php elseif ($viewMode === 'list'): ?>
        <!-- LIST VIEW -->
        <div class="articles-list" id="articlesContainer">
            <?php foreach ($paginatedNews as $index => $article):
                $catColor = getCategoryColor($article['category'] ?? 'general');
            ?>
            <div class="article-list-card" data-index="<?= $index ?>" id="listcard-<?= $index ?>">
                <!-- Checkbox -->
                <div class="card-checkbox" onclick="toggleSelect(<?= $index ?>)" id="chk-<?= $index ?>">
                    <span class="material-icons-round">check</span>
                </div>

                <?php if (!empty($article['image'])): ?>
                <img src="<?= e($article['image']) ?>" class="list-thumb"
                     onerror="this.outerHTML='<div class=\'list-thumb-placeholder\'><span class=\'material-icons-round\'>image_not_supported</span></div>'"
                     alt="">
                <?php else: ?>
                <div class="list-thumb-placeholder">
                    <span class="material-icons-round"><?= getCategoryIcon($article['category'] ?? 'general') ?></span>
                </div>
                <?php endif; ?>

                <div class="list-content">
                    <div class="list-title" onclick='openPreview(<?= json_encode($article, JSON_HEX_APOS) ?>)'><?= e($article['title']) ?></div>
                    <div class="list-desc"><?= e($article['description'] ?: 'No description available.') ?></div>
                    <div class="list-meta">
                        <span class="category-badge" style="background:<?= $catColor['bg'] ?>;color:<?= $catColor['text'] ?>;font-size:10px;">
                            <?= ucfirst($article['category'] ?? 'general') ?>
                        </span>
                        <span class="card-time"><span class="material-icons-round">schedule</span><?= timeAgo($article['published_at']) ?></span>
                        <span style="font-size:12px;color:var(--text-muted);"><?= e($article['source'] ?? '') ?></span>
                        <span class="read-time"><span class="material-icons-round">menu_book</span><?= readingTime($article['description'] ?? '') ?></span>
                    </div>
                </div>

                <div class="list-actions">
                    <button class="card-action-btn" onclick="window.open('<?= e($article['url']) ?>','_blank')" title="Read original">
                        <span class="material-icons-round">open_in_new</span>
                    </button>
                    <button class="card-action-btn" onclick='openPreview(<?= json_encode($article, JSON_HEX_APOS) ?>)' title="Preview">
                        <span class="material-icons-round">visibility</span>
                    </button>
                    <button class="card-action-btn import-btn" id="importbtn-<?= $index ?>"
                            onclick='importArticle(<?= json_encode($article, JSON_HEX_APOS) ?>, this)' title="Import">
                        <span class="material-icons-round">cloud_download</span>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- GRID VIEW -->
        <div class="articles-grid" id="articlesContainer">
            <?php foreach ($paginatedNews as $index => $article):
                $catColor = getCategoryColor($article['category'] ?? 'general');
            ?>
            <div class="article-card" data-index="<?= $index ?>" id="card-<?= $index ?>">
                <!-- Checkbox -->
                <div class="card-checkbox" onclick="toggleSelect(<?= $index ?>)" id="chk-<?= $index ?>">
                    <span class="material-icons-round">check</span>
                </div>

                <!-- Image -->
                <div class="card-image">
                    <?php if (!empty($article['image'])): ?>
                    <img src="<?= e($article['image']) ?>"
                         alt="<?= e($article['title']) ?>"
                         onerror="this.parentElement.innerHTML='<div class=\'card-image-placeholder\'><span class=\'material-icons-round\'><?= getCategoryIcon($article['category'] ?? 'general') ?></span></div>'">
                    <?php else: ?>
                    <div class="card-image-placeholder">
                        <span class="material-icons-round"><?= getCategoryIcon($article['category'] ?? 'general') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="card-image-overlay"></div>
                </div>

                <div class="card-body">
                    <div class="card-meta">
                        <span class="category-badge" style="background:<?= $catColor['bg'] ?>;color:<?= $catColor['text'] ?>">
                            <span class="material-icons-round" style="font-size:11px!important"><?= getCategoryIcon($article['category'] ?? 'general') ?></span>
                            <?= ucfirst($article['category'] ?? 'general') ?>
                        </span>
                        <span class="card-time">
                            <span class="material-icons-round">schedule</span>
                            <?= timeAgo($article['published_at']) ?>
                        </span>
                    </div>

                    <div class="card-title" onclick='openPreview(<?= json_encode($article, JSON_HEX_APOS) ?>)'
                         title="<?= e($article['title']) ?>"><?= e($article['title']) ?></div>

                    <div class="card-desc"><?= e($article['description'] ?: 'No description available.') ?></div>

                    <div class="card-footer">
                        <div class="card-source">
                            <div class="card-source-dot" style="background:<?= $catColor['dot'] ?>"></div>
                            <span class="card-source-name" title="<?= e($article['source'] ?? '') ?>"><?= e($article['source'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="card-actions">
                            <span class="read-time" style="margin-right:4px"><?= readingTime($article['description'] ?? '') ?></span>
                            <button class="card-action-btn" onclick='openPreview(<?= json_encode($article, JSON_HEX_APOS) ?>)' title="Preview article">
                                <span class="material-icons-round">visibility</span>
                            </button>
                            <button class="card-action-btn" onclick="window.open('<?= e($article['url']) ?>','_blank')" title="Open original">
                                <span class="material-icons-round">open_in_new</span>
                            </button>
                            <button class="card-action-btn import-btn" id="importbtn-<?= $index ?>"
                                    onclick='importArticle(<?= json_encode($article, JSON_HEX_APOS) ?>, this)' title="Import to database">
                                <span class="material-icons-round">cloud_download</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <div class="pagination-info">
                Page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong>
                &nbsp;·&nbsp; <?= number_format($totalItems) ?> total articles
            </div>
            <div class="pagination-pages">
                <a href="<?= getPaginationUrl(1, $section, $itemsPerPage, $searchQuery, $sortBy, $viewMode) ?>"
                   class="page-btn <?= $currentPage == 1 ? 'disabled' : '' ?>" title="First">
                    <span class="material-icons-round">first_page</span>
                </a>
                <a href="<?= getPaginationUrl(max(1,$currentPage-1), $section, $itemsPerPage, $searchQuery, $sortBy, $viewMode) ?>"
                   class="page-btn <?= $currentPage == 1 ? 'disabled' : '' ?>">
                    <span class="material-icons-round">chevron_left</span>
                </a>

                <?php
                $startPage = max(1, $currentPage - 2);
                $endPage   = min($totalPages, $currentPage + 2);
                if ($startPage > 1) echo '<span style="padding:0 4px;color:var(--text-muted);line-height:34px">…</span>';
                for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= getPaginationUrl($i, $section, $itemsPerPage, $searchQuery, $sortBy, $viewMode) ?>"
                   class="page-btn <?= $i == $currentPage ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor;
                if ($endPage < $totalPages) echo '<span style="padding:0 4px;color:var(--text-muted);line-height:34px">…</span>';
                ?>

                <a href="<?= getPaginationUrl(min($totalPages,$currentPage+1), $section, $itemsPerPage, $searchQuery, $sortBy, $viewMode) ?>"
                   class="page-btn <?= $currentPage == $totalPages ? 'disabled' : '' ?>">
                    <span class="material-icons-round">chevron_right</span>
                </a>
                <a href="<?= getPaginationUrl($totalPages, $section, $itemsPerPage, $searchQuery, $sortBy, $viewMode) ?>"
                   class="page-btn <?= $currentPage == $totalPages ? 'disabled' : '' ?>" title="Last">
                    <span class="material-icons-round">last_page</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /content-area -->
</div><!-- /main-area -->
</div><!-- /app-shell -->

<!-- ═══════════════════════════════════════════
     ARTICLE PREVIEW MODAL
════════════════════════════════════════════ -->
<div class="modal-overlay" id="previewModal" onclick="closeModal(event)">
    <div class="modal" id="modalContent">
        <div class="modal-image" id="modalImage" style="position:relative">
            <img src="" id="modalImg" alt="" style="display:none">
            <div id="modalImgPlaceholder" class="card-image-placeholder" style="height:100%">
                <span class="material-icons-round" style="font-size:56px!important;color:rgba(0,0,0,0.2)">article</span>
            </div>
            <div class="modal-image-overlay"></div>
            <button class="modal-close-btn" onclick="document.getElementById('previewModal').classList.remove('open')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-category" id="modalCategory"></div>
            <h2 class="modal-title" id="modalTitle">Article Title</h2>
            <p class="modal-desc" id="modalDesc">Description goes here.</p>
            <div class="modal-meta" id="modalMeta"></div>
            <div class="modal-actions" id="modalActions"></div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ── Data ─────────────────────────────────────────────
const articlesData = <?= json_encode($paginatedNews, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
let selectedArticles = new Set();
let selectMode = false;

// ── Sidebar ───────────────────────────────────────────
function toggleSidebar() {
    const sb = document.getElementById('sidebar');
    const isMobile = window.innerWidth <= 768;
    if (isMobile) {
        sb.classList.toggle('mobile-open');
    } else {
        sb.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', sb.classList.contains('collapsed'));
    }
}
(function initSidebar() {
    if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
        document.getElementById('sidebar').classList.add('collapsed');
    }
})();

// ── Dark Mode ─────────────────────────────────────────
function toggleDarkMode() {
    const html = document.documentElement;
    const isDark = html.getAttribute('data-theme') === 'dark';
    html.setAttribute('data-theme', isDark ? 'light' : 'dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    const btn = document.getElementById('darkModeBtn');
    btn.querySelector('.material-icons-round').textContent = isDark ? 'dark_mode' : 'light_mode';
}
(function initTheme() {
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const btn = document.getElementById('darkModeBtn');
    if (btn) btn.querySelector('.material-icons-round').textContent = saved === 'dark' ? 'light_mode' : 'dark_mode';
})();

// ── View Mode ─────────────────────────────────────────
function setView(mode) {
    const url = new URL(window.location.href);
    url.searchParams.set('view', mode);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// ── Sort ──────────────────────────────────────────────
function setSort(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// ── Items per page ─────────────────────────────────────
function changeItemsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('itemsPerPage', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// ── Select Mode ───────────────────────────────────────
function toggleSelectMode() {
    selectMode = !selectMode;
    const btn = document.getElementById('selectModeBtn');
    const container = document.getElementById('articlesContainer');
    btn.classList.toggle('active', selectMode);
    if (container) container.classList.toggle('select-mode', selectMode);
    if (!selectMode) {
        selectedArticles.clear();
        updateBulkUI();
        document.querySelectorAll('.article-card.selected, .article-list-card.selected').forEach(c => c.classList.remove('selected'));
        document.querySelectorAll('.card-checkbox.checked').forEach(c => c.classList.remove('checked'));
    }
}

function toggleSelect(index) {
    if (!selectMode) { selectMode = true; toggleSelectMode(); selectMode = true; }
    const card = document.getElementById('card-' + index) || document.getElementById('listcard-' + index);
    const chk  = document.getElementById('chk-' + index);
    if (selectedArticles.has(index)) {
        selectedArticles.delete(index);
        card?.classList.remove('selected');
        chk?.classList.remove('checked');
    } else {
        selectedArticles.add(index);
        card?.classList.add('selected');
        chk?.classList.add('checked');
    }
    updateBulkUI();
}

function updateBulkUI() {
    const count = selectedArticles.size;
    document.getElementById('selectedCount').textContent = count;
    const bulkInfo = document.getElementById('bulkInfo');
    const importBtn = document.getElementById('importSelectedBtn');
    if (count > 0) {
        bulkInfo.classList.add('visible');
        importBtn.style.display = 'inline-flex';
        importBtn.textContent = '';
        importBtn.innerHTML = `<span class="material-icons-round">cloud_download</span> Import ${count} selected`;
    } else {
        bulkInfo.classList.remove('visible');
        importBtn.style.display = 'none';
    }
}

// ── Preview Modal ─────────────────────────────────────
function openPreview(article) {
    const modal = document.getElementById('previewModal');
    const catColor = getCatColor(article.category);

    // Image
    const img = document.getElementById('modalImg');
    const placeholder = document.getElementById('modalImgPlaceholder');
    if (article.image) {
        img.src = article.image;
        img.style.display = 'block';
        placeholder.style.display = 'none';
        img.onerror = () => { img.style.display='none'; placeholder.style.display='flex'; };
    } else {
        img.style.display = 'none';
        placeholder.style.display = 'flex';
    }

    // Category
    document.getElementById('modalCategory').innerHTML =
        `<span class="category-badge" style="background:${catColor.bg};color:${catColor.text}">
            ${capitalize(article.category || 'general')}
        </span>`;

    // Title & desc
    document.getElementById('modalTitle').textContent = article.title || 'No title';
    document.getElementById('modalDesc').textContent  = article.description || 'No description available for this article.';

    // Meta
    const metaItems = [
        article.source ? `<span class="modal-meta-item"><span class="material-icons-round">source</span>${esc(article.source)}</span>` : '',
        article.author ? `<span class="modal-meta-item"><span class="material-icons-round">person</span>${esc(article.author)}</span>` : '',
        article.published_at ? `<span class="modal-meta-item"><span class="material-icons-round">schedule</span>${timeAgoJS(article.published_at)}</span>` : '',
        article.description ? `<span class="modal-meta-item read-time"><span class="material-icons-round">menu_book</span>${readingTimeJS(article.description)}</span>` : '',
    ];
    document.getElementById('modalMeta').innerHTML = metaItems.filter(Boolean).join('');

    // Actions
    document.getElementById('modalActions').innerHTML = `
        <button class="btn btn-primary" onclick="window.open('${esc(article.url)}','_blank')">
            <span class="material-icons-round">open_in_new</span> Read Full Article
        </button>
        <button class="btn btn-secondary" id="modalImportBtn" onclick='handleModalImport(${JSON.stringify(article).replace(/'/g,"\\'")})'> 
            <span class="material-icons-round">cloud_download</span> Import
        </button>
        <button class="btn btn-ghost" onclick="copyToClipboard('${esc(article.url)}')">
            <span class="material-icons-round">link</span> Copy URL
        </button>
    `;

    modal.classList.add('open');
}

function closeModal(e) {
    if (e.target === document.getElementById('previewModal')) {
        document.getElementById('previewModal').classList.remove('open');
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') document.getElementById('previewModal').classList.remove('open');
});

function handleModalImport(article) {
    const btn = document.getElementById('modalImportBtn');
    if (!btn) return;
    importArticle(article, null, () => {
        btn.innerHTML = '<span class="material-icons-round">check_circle</span> Imported';
        btn.disabled = true;
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-primary');
        btn.style.background = '#6D28D9';
    });
}

// ── Import Single Article ─────────────────────────────
function importArticle(article, btnEl, onSuccess) {
    if (btnEl) {
        btnEl.disabled = true;
        btnEl.innerHTML = '<span class="material-icons-round" style="animation:spin 1s linear infinite">refresh</span>';
    }

    fetch('news_import/save_article.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: article.title,
            content: article.description || article.title,
            category: article.category,
            source: article.source,
            author: article.author,
            published_at: article.published_at,
            url: article.url
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            if (btnEl) {
                btnEl.classList.add('done');
                btnEl.innerHTML = '<span class="material-icons-round">check_circle</span>';
            }
            if (onSuccess) onSuccess();
        } else {
            const isExists = (data.message || '').includes('already exists');
            showToast(data.message, isExists ? 'warning' : 'error');
            if (btnEl) {
                btnEl.disabled = false;
                btnEl.innerHTML = '<span class="material-icons-round">cloud_download</span>';
            }
        }
    })
    .catch(() => {
        showToast('Import failed. Please try again.', 'error');
        if (btnEl) {
            btnEl.disabled = false;
            btnEl.innerHTML = '<span class="material-icons-round">cloud_download</span>';
        }
    });
}

// ── Import All Visible ────────────────────────────────
function importAllVisible() {
    if (articlesData.length === 0) { showToast('No articles to import', 'warning'); return; }
    if (!confirm(`Import all ${articlesData.length} visible articles?`)) return;
    batchImport(articlesData.map((_, i) => i));
}

// ── Import Selected ────────────────────────────────────
function importSelected() {
    if (selectedArticles.size === 0) { showToast('No articles selected', 'warning'); return; }
    const indices = [...selectedArticles];
    if (!confirm(`Import ${indices.length} selected article${indices.length > 1 ? 's' : ''}?`)) return;
    batchImport(indices);
}

function batchImport(indices) {
    let done = 0, saved = 0, exists = 0, failed = 0;
    showToast(`Importing ${indices.length} articles…`, 'info');

    indices.forEach((idx, i) => {
        const article = articlesData[idx];
        const btnEl = document.getElementById('importbtn-' + idx);
        if (btnEl) { btnEl.disabled = true; btnEl.innerHTML = '<span class="material-icons-round" style="animation:spin 1s linear infinite">refresh</span>'; }

        setTimeout(() => {
            fetch('news_import/save_article.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    title: article.title, content: article.description || article.title,
                    category: article.category, source: article.source,
                    author: article.author, published_at: article.published_at, url: article.url
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    saved++;
                    if (btnEl) { btnEl.classList.add('done'); btnEl.innerHTML = '<span class="material-icons-round">check_circle</span>'; }
                } else if ((data.message || '').includes('already exists')) {
                    exists++;
                    if (btnEl) { btnEl.innerHTML = '<span class="material-icons-round">info</span>'; btnEl.style.color='#7C3AED'; }
                } else {
                    failed++;
                    if (btnEl) { btnEl.disabled=false; btnEl.innerHTML='<span class="material-icons-round">cloud_download</span>'; }
                }
                done++;
                if (done === indices.length) {
                    let msg = `✓ ${saved} imported`;
                    if (exists) msg += `, ${exists} already existed`;
                    if (failed) msg += `, ${failed} failed`;
                    showToast(msg, failed ? 'warning' : 'success');
                }
            })
            .catch(() => {
                failed++; done++;
                if (btnEl) { btnEl.disabled=false; btnEl.innerHTML='<span class="material-icons-round">cloud_download</span>'; }
            });
        }, i * 150);
    });
}

// ── Copy URL ──────────────────────────────────────────
function copyToClipboard(url) {
    navigator.clipboard.writeText(url).then(() => showToast('URL copied to clipboard', 'success'))
        .catch(() => showToast('Failed to copy URL', 'error'));
}

// ── Toast ─────────────────────────────────────────────
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const icons = { success: 'check_circle', error: 'error', warning: 'warning', info: 'info' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <span class="material-icons-round">${icons[type]}</span>
        <span style="flex:1">${message}</span>
        <span class="toast-close material-icons-round" onclick="this.parentElement.remove()">close</span>
    `;
    container.appendChild(toast);
    setTimeout(() => toast.style.opacity = '0', 4500);
    setTimeout(() => toast.remove(), 5000);
}

// ── Helpers ───────────────────────────────────────────
function timeAgoJS(datetime) {
    const diff = (Date.now() - new Date(datetime).getTime()) / 1000;
    if (diff < 60)   return 'Just now';
    if (diff < 3600) return Math.floor(diff/60)   + 'm ago';
    if (diff < 86400) return Math.floor(diff/3600) + 'h ago';
    return Math.floor(diff/86400) + 'd ago';
}
function readingTimeJS(text) {
    const words = (text || '').trim().split(/\s+/).length;
    return Math.max(1, Math.ceil(words / 200)) + ' min read';
}
function capitalize(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
function esc(s) { return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

function getCatColor(cat) {
    const map = {
        business:      { bg:'#F5F3FF', text:'#5B21B6' },
        health:        { bg:'#FDF4FF', text:'#7E22CE' },
        sports:        { bg:'#EDE9FE', text:'#4C1D95' },
        technology:    { bg:'#F0EFFE', text:'#6D28D9' },
        entertainment: { bg:'#FAF5FF', text:'#9333EA' },
        science:       { bg:'#EEE8FD', text:'#5B21B6' },
        general:       { bg:'#F5F3FF', text:'#6B6485' },
    };
    return map[cat] || map.general;
}

// CSS spin for inline elements
const styleEl = document.createElement('style');
styleEl.textContent = '@keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }';
document.head.appendChild(styleEl);

// ── Search debounce ───────────────────────────────────
let searchTimeout;
document.getElementById('searchInput')?.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        document.getElementById('searchForm').submit();
    }, 600);
});

// ── Mobile sidebar close on click outside ─────────────
document.addEventListener('click', function(e) {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth <= 768 && sb.classList.contains('mobile-open')) {
        if (!sb.contains(e.target) && !e.target.closest('.toggle-sidebar-btn')) {
            sb.classList.remove('mobile-open');
        }
    }
});
</script>
</body>
</html>