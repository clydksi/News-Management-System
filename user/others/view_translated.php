<?php
/**
 * translated_list.php - Enhanced Diplomatic Codex Edition
 * Lists all translated articles in a refined dual-document layout.
 */

require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/admin/includes/access_control.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// ─── Export Handler (runs before any HTML output) ─────────────────────────────
if (isset($_GET['export']) && in_array($_GET['export'], ['csv', 'json'], true)) {
    $exportFormat = $_GET['export'];
    $visibleDeptIds = getVisibleDepartmentIds($pdo, $_SESSION);
    [$dw_n, $dp_n]  = buildDeptWhere($visibleDeptIds, 'n.department_id');
    $exportParams   = $dp_n;
    $exportWhere    = [$dw_n, "n.is_translated = 1"];
    $exportSearch   = trim($_GET['search'] ?? '');
    $exportDialect  = trim($_GET['dialect'] ?? '');
    $exportStatus   = $_GET['status'] ?? '';

    if (!empty($exportSearch)) {
        $exportWhere[] = "(n.title LIKE ? OR n.translated_title LIKE ? OR n.translated_lang LIKE ?)";
        $exportParams  = array_merge($exportParams, ["%{$exportSearch}%", "%{$exportSearch}%", "%{$exportSearch}%"]);
    }
    if (!empty($exportDialect)) {
        $exportWhere[] = "n.translated_lang = ?";
        $exportParams[] = $exportDialect;
    }
    if ($exportStatus !== '') {
        $exportWhere[] = "n.is_pushed = ?";
        $exportParams[] = (int)$exportStatus;
    }

    $exportSQL = "SELECT n.id, n.title, n.translated_title, n.translated_lang, n.translated_at,
                         n.is_pushed, u.username, d.name AS dept_name, c.name AS category_name
                  FROM news n
                  JOIN users u       ON n.created_by    = u.id
                  JOIN departments d ON n.department_id = d.id
                  LEFT JOIN categories c ON n.category_id = c.id
                  WHERE " . implode(" AND ", $exportWhere) . "
                  ORDER BY n.translated_at DESC";

    $exportStmt = $pdo->prepare($exportSQL);
    $exportStmt->execute($exportParams);
    $exportRows = $exportStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($exportFormat === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="translated_articles_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-cache');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM for Excel
        fputcsv($out, ['ID', 'Original Title', 'Translated Title', 'Dialect', 'Author',
                       'Department', 'Category', 'Status', 'Translated At']);
        $statusLabels = [0 => 'Regular', 1 => 'Edited', 2 => 'Headline', 3 => 'Archive'];
        foreach ($exportRows as $row) {
            fputcsv($out, [
                $row['id'], $row['title'], $row['translated_title'] ?? '',
                $row['translated_lang'] ?? '', $row['username'], $row['dept_name'],
                $row['category_name'] ?? '', $statusLabels[$row['is_pushed']] ?? 'Unknown',
                $row['translated_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    if ($exportFormat === 'json') {
        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="translated_articles_' . date('Ymd_His') . '.json"');
        echo json_encode(['exported_at' => date('c'), 'total' => count($exportRows), 'articles' => $exportRows],
                         JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ─── Main Query Setup ─────────────────────────────────────────────────────────
$visibleDeptIds = getVisibleDepartmentIds($pdo, $_SESSION);
[$dw_n, $dp_n]  = buildDeptWhere($visibleDeptIds, 'n.department_id');

$itemsPerPage = isset($_GET['per_page']) ? max(5, min(50, (int) $_GET['per_page'])) : 10;
$currentPage  = isset($_GET['page'])     ? max(1, (int) $_GET['page'])              : 1;
$offset       = ($currentPage - 1) * $itemsPerPage;
$search       = trim($_GET['search']  ?? '');
$filterDialect= trim($_GET['dialect'] ?? '');
$filterStatus = $_GET['status'] ?? '';
$sortField    = in_array($_GET['sort'] ?? '', ['translated_at', 'created_at', 'title']) ? $_GET['sort'] : 'translated_at';
$sortDir      = ($_GET['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$diffMode     = isset($_GET['diff']);

$whereClauses = [$dw_n, "n.is_translated = 1"];
$params       = $dp_n;

if (!empty($search)) {
    $whereClauses[] = "(n.title LIKE ? OR n.translated_title LIKE ? OR n.translated_lang LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}
if (!empty($filterDialect)) {
    $whereClauses[] = "n.translated_lang = ?";
    $params[] = $filterDialect;
}
if ($filterStatus !== '') {
    $whereClauses[] = "n.is_pushed = ?";
    $params[] = (int)$filterStatus;
}

$whereSQL = "WHERE " . implode(" AND ", $whereClauses);

// Total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM news n {$whereSQL}");
$countStmt->execute($params);
$totalItems = (int) $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalItems / $itemsPerPage));
if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Fetch articles
$stmt = $pdo->prepare("
    SELECT n.*, u.username, d.name AS dept_name, c.name AS category_name
    FROM news n
    JOIN users u       ON n.created_by    = u.id
    JOIN departments d ON n.department_id = d.id
    LEFT JOIN categories c ON n.category_id = c.id
    {$whereSQL}
    ORDER BY n.{$sortField} {$sortDir}
    LIMIT " . intval($itemsPerPage) . " OFFSET " . intval($offset)
);
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// All distinct dialects for filter dropdown
$dialectsStmt = $pdo->prepare("
    SELECT DISTINCT translated_lang
    FROM news n
    {$whereSQL}
    AND translated_lang IS NOT NULL AND translated_lang != ''
    ORDER BY translated_lang ASC
");
$dialectsStmt->execute($params);
$allDialects = $dialectsStmt->fetchAll(PDO::FETCH_COLUMN);

// ─── Static Maps ─────────────────────────────────────────────────────────────
$dialectIcons = [
    'Tagalog'              => '🏙️',
    'Cebuano (Bisaya)'     => '🌊',
    'Ilocano'              => '⛰️',
    'Hiligaynon (Ilonggo)' => '🎶',
    'Bicolano'             => '🌋',
    'Waray'                => '🌺',
    'Kapampangan'          => '🦅',
    'Pangasinan'           => '🐟',
];

$statusMap = [
    0 => ['label' => 'Regular',  'dot' => '#F59E0B', 'bg' => '#FFF7ED', 'text' => '#92400E'],
    1 => ['label' => 'Edited',   'dot' => '#10B981', 'bg' => '#ECFDF5', 'text' => '#065F46'],
    2 => ['label' => 'Headline', 'dot' => '#3B82F6', 'bg' => '#EFF6FF', 'text' => '#1D4ED8'],
    3 => ['label' => 'Archive',  'dot' => '#EF4444', 'bg' => '#FFF1F2', 'text' => '#9F1239'],
];

function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function buildSortUrl(string $field, string $currentField, string $currentDir, array $base): string {
    $dir = ($currentField === $field && $currentDir === 'DESC') ? 'asc' : 'desc';
    return '?' . http_build_query(array_merge($base, ['sort' => $field, 'dir' => $dir]));
}

// Dialect distribution
$dialectCounts = [];
foreach ($articles as $a) {
    $lang = $a['translated_lang'] ?? 'Unknown';
    $dialectCounts[$lang] = ($dialectCounts[$lang] ?? 0) + 1;
}
arsort($dialectCounts);

// Base params for URL building (preserves all active filters)
$baseQueryParams = array_filter([
    'search'  => $search,
    'dialect' => $filterDialect,
    'status'  => $filterStatus,
    'per_page'=> $itemsPerPage,
    'sort'    => $sortField,
    'dir'     => strtolower($sortDir),
    'diff'    => $diffMode ? '1' : null,
], fn($v) => $v !== '' && $v !== null);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Translated Articles — Codex</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
/* ════════════════════════════════════════════════════════
   DESIGN TOKENS
════════════════════════════════════════════════════════ */
:root {
    --purple:       #7C3AED;
    --purple-md:    #6D28D9;
    --purple-dark:  #4C1D95;
    --purple-light: #EDE9FE;
    --purple-pale:  #F5F3FF;
    --purple-glow:  rgba(124,58,237,.18);
    --purple-rule:  rgba(124,58,237,.35);
    --ink:          #13111A;
    --ink-muted:    #4A4560;
    --ink-faint:    #8E89A8;
    --ink-ghost:    rgba(19,17,26,.04);
    --canvas:       #F3F1FA;
    --surface:      #FFFFFF;
    --surface-2:    #EEEAF8;
    --surface-3:    #F8F7FD;
    --orig-bg:      #F0F7FF;
    --orig-bg-hd:   #E8F2FF;
    --orig-border:  #BFDBFE;
    --orig-label:   #1D4ED8;
    --orig-dot:     #3B82F6;
    --orig-rule:    rgba(59,130,246,.25);
    --trans-bg:     #F5F3FF;
    --trans-bg-hd:  #EDE9FE;
    --trans-border: #C4B5FD;
    --trans-label:  #6D28D9;
    --trans-dot:    #7C3AED;
    --trans-rule:   rgba(124,58,237,.25);
    --border:       #E2DDEF;
    --border-md:    #C9C2E0;
    --border-strong:#ADA5CC;
    --r:    14px;
    --r-sm:  9px;
    --r-xs:  5px;
    --sh:    0 1px 3px rgba(60,20,120,.07), 0 1px 2px rgba(60,20,120,.04);
    --sh-md: 0 4px 18px rgba(60,20,120,.11), 0 2px 6px rgba(60,20,120,.06);
    --sh-lg: 0 14px 44px rgba(60,20,120,.16), 0 4px 10px rgba(60,20,120,.07);
    --sh-xl: 0 24px 64px rgba(60,20,120,.22), 0 6px 14px rgba(60,20,120,.08);
    --success: #059669;
    --warn:    #D97706;
    --danger:  #DC2626;
    --diff-add: rgba(16,185,129,.15);
    --diff-add-border: rgba(16,185,129,.4);
}
[data-theme="dark"] {
    --ink:          #EAE6F8;
    --ink-muted:    #9E98B8;
    --ink-faint:    #635D7A;
    --ink-ghost:    rgba(255,255,255,.03);
    --canvas:       #0E0C18;
    --surface:      #17142A;
    --surface-2:    #1E1A30;
    --surface-3:    #13102A;
    --orig-bg:      #0A1220;
    --orig-bg-hd:   #0D1829;
    --orig-border:  #1A3356;
    --orig-label:   #60A5FA;
    --orig-rule:    rgba(59,130,246,.2);
    --trans-bg:     #0F0A22;
    --trans-bg-hd:  #130E28;
    --trans-border: #241A4A;
    --trans-label:  #A78BFA;
    --trans-rule:   rgba(124,58,237,.22);
    --border:       #2A2540;
    --border-md:    #362F50;
    --border-strong:#4A4265;
    --purple-light: #1E1440;
    --purple-pale:  #150F2E;
    --purple-rule:  rgba(124,58,237,.45);
    --sh:    0 1px 4px rgba(0,0,0,.35);
    --sh-md: 0 4px 18px rgba(0,0,0,.45);
    --sh-lg: 0 14px 44px rgba(0,0,0,.55);
    --sh-xl: 0 24px 64px rgba(0,0,0,.7);
    --diff-add: rgba(16,185,129,.1);
}

/* ════════════════════════════════════════════════════════
   BASE
════════════════════════════════════════════════════════ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { min-height: 100%; scroll-behavior: smooth; }
body { font-family: 'Sora', sans-serif; background: var(--canvas); color: var(--ink); min-height: 100vh; transition: background .25s, color .25s; }
::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-md); border-radius: 99px; }
::-webkit-scrollbar-thumb:hover { background: var(--purple); }

/* ─── READING PROGRESS BAR ─── */
#readingProgress {
    position: fixed; top: 0; left: 0; height: 3px; z-index: 200;
    background: linear-gradient(90deg, var(--purple), #A78BFA, #60A5FA);
    width: 0%; transition: width .1s linear;
    box-shadow: 0 0 8px var(--purple-glow);
}

/* ─── PAGE SHELL ─── */
.page-shell { max-width: 1320px; margin: 0 auto; padding: 0 20px 80px; }

/* ─── STICKY TOPBAR ─── */
.topbar {
    position: sticky; top: 0; z-index: 60;
    padding: 14px 20px; margin: 0 -20px;
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    transition: background .2s, box-shadow .2s;
}
.topbar.scrolled {
    background: rgba(243,241,250,.88);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    box-shadow: 0 1px 0 var(--border), 0 2px 12px rgba(60,20,120,.07);
}
[data-theme="dark"] .topbar.scrolled {
    background: rgba(14,12,24,.88);
    box-shadow: 0 1px 0 var(--border), 0 2px 12px rgba(0,0,0,.45);
}
.back-link {
    display: inline-flex; align-items: center; gap: 7px;
    font-size: 13px; font-weight: 500; color: var(--ink-faint);
    text-decoration: none; transition: color .15s;
    padding: 6px 10px; border-radius: var(--r-sm);
}
.back-link:hover { color: var(--purple); background: var(--purple-pale); }
.back-link .material-icons-round { font-size: 17px !important; }
.topbar-right { display: flex; align-items: center; gap: 6px; }
.tb-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 13px; border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 500;
    cursor: pointer; border: 1px solid var(--border);
    background: var(--surface); color: var(--ink-muted);
    text-decoration: none; transition: all .15s;
}
.tb-btn .material-icons-round { font-size: 15px !important; }
.tb-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.tb-btn-icon { width: 34px; height: 34px; padding: 0; justify-content: center; }
.tb-btn.active { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }

/* ─── HERO ─── */
.hero { padding: 36px 0 0; margin-bottom: 20px; }
.hero-top { display: grid; grid-template-columns: 1fr auto; gap: 20px; align-items: start; margin-bottom: 18px; }
@media(max-width:700px){ .hero-top { grid-template-columns:1fr; } .search-panel { min-width:0; width:100%; } }
.hero-eyebrow {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; border-radius: 99px;
    background: var(--purple-light); border: 1px solid var(--trans-border);
    font-size: 10px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase;
    color: var(--purple-md); margin-bottom: 14px; font-family: 'Fira Code', monospace;
}
.hero-eyebrow .material-icons-round { font-size: 13px !important; }
.hero-title {
    font-family: 'Playfair Display', serif;
    font-size: clamp(26px, 4vw, 38px);
    font-weight: 700; color: var(--ink);
    line-height: 1.15; margin-bottom: 10px; letter-spacing: -.02em;
}
.hero-title em { font-style: italic; color: var(--purple); }
.hero-sub { font-size: 14px; color: var(--ink-faint); max-width: 480px; line-height: 1.6; }
.stat-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 18px; }
.stat-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 13px; border-radius: 99px;
    border: 1px solid var(--border); background: var(--surface);
    font-size: 11px; color: var(--ink-muted); font-family: 'Fira Code', monospace;
    box-shadow: var(--sh); transition: all .2s;
}
.stat-pill:hover { border-color: var(--purple); color: var(--purple); box-shadow: 0 2px 10px var(--purple-glow); }
.stat-pill strong { color: var(--ink); font-weight: 700; }
.stat-pill .material-icons-round { font-size: 14px !important; color: var(--purple); }
.dialect-pill-emoji { font-size: 14px; line-height: 1; }

/* ─── SEARCH PANEL ─── */
.search-panel {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 14px 18px;
    display: flex; align-items: center; gap: 10px;
    align-self: start; min-width: 300px;
}
.search-input-wrap { position: relative; flex: 1; }
.search-input-wrap input {
    width: 100%; padding: 9px 14px 9px 37px;
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--canvas); font-family: 'Sora', sans-serif;
    font-size: 13px; color: var(--ink); outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.search-input-wrap input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }
.search-input-wrap input::placeholder { color: var(--ink-faint); }
.search-input-wrap .si { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--ink-faint); font-size: 17px !important; pointer-events: none; }
.search-clear-btn {
    width: 30px; height: 30px; border-radius: 50%; border: 1px solid var(--border);
    background: transparent; cursor: pointer; color: var(--ink-faint);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all .15s;
}
.search-clear-btn:hover { background: #FFF1F2; color: #DC2626; border-color: #FECDD3; }
.search-clear-btn .material-icons-round { font-size: 15px !important; }

/* ─── FILTER BAR ─── */
.filter-bar {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 12px 18px; margin-bottom: 18px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.filter-label { font-size: 11px; font-weight: 700; color: var(--ink-faint); font-family: 'Fira Code', monospace; text-transform: uppercase; letter-spacing: .08em; white-space: nowrap; }
.filter-select {
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--canvas); padding: 6px 28px 6px 10px;
    font-family: 'Sora', sans-serif; font-size: 12px; color: var(--ink);
    cursor: pointer; outline: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center;
    transition: border-color .15s;
}
.filter-select:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }
.filter-divider { width: 1px; height: 20px; background: var(--border); flex-shrink: 0; }
.sort-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 11px; border-radius: var(--r-sm);
    border: 1px solid var(--border); background: var(--canvas);
    font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 500;
    color: var(--ink-muted); cursor: pointer; text-decoration: none; transition: all .15s;
}
.sort-btn:hover, .sort-btn.active { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.sort-btn .material-icons-round { font-size: 13px !important; }
.filter-bar-right { margin-left: auto; display: flex; align-items: center; gap: 6px; }
.active-filter-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 99px;
    background: var(--purple-light); border: 1px solid var(--trans-border);
    font-size: 11px; font-weight: 600; color: var(--purple-md);
    font-family: 'Fira Code', monospace;
}
.active-filter-chip a { color: inherit; text-decoration: none; display: flex; align-items: center; }
.active-filter-chip .material-icons-round { font-size: 12px !important; }

/* ─── BULK SELECT ─── */
.bulk-bar {
    background: var(--purple); color: white;
    border-radius: var(--r); padding: 10px 18px;
    display: flex; align-items: center; gap: 12px;
    margin-bottom: 14px; box-shadow: 0 4px 18px var(--purple-glow);
    opacity: 0; pointer-events: none;
    transition: opacity .2s, transform .2s;
    transform: translateY(-6px);
}
.bulk-bar.visible { opacity: 1; pointer-events: all; transform: none; }
.bulk-bar-count { font-size: 13px; font-weight: 700; }
.bulk-divider { width: 1px; height: 18px; background: rgba(255,255,255,.3); }
.bulk-act-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 12px; border-radius: var(--r-sm);
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25);
    color: white; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: background .15s;
}
.bulk-act-btn:hover { background: rgba(255,255,255,.25); }
.bulk-act-btn .material-icons-round { font-size: 14px !important; }
.bulk-dismiss { margin-left: auto; background: none; border: none; color: rgba(255,255,255,.7); cursor: pointer; padding: 4px; border-radius: var(--r-xs); transition: color .15s; }
.bulk-dismiss:hover { color: white; }
.bulk-dismiss .material-icons-round { font-size: 17px !important; }

/* ─── SELECT CHECKBOX ─── */
.card-select-wrap {
    position: absolute; top: 12px; left: 14px; z-index: 10;
    opacity: 0; transition: opacity .15s;
}
.article-card:hover .card-select-wrap,
.article-card.selected .card-select-wrap,
.select-mode .card-select-wrap { opacity: 1; }
.card-checkbox {
    width: 18px; height: 18px; border-radius: 5px;
    border: 2px solid var(--border-md); background: var(--surface);
    cursor: pointer; appearance: none; transition: all .15s;
    display: grid; place-items: center;
}
.card-checkbox:checked { background: var(--purple); border-color: var(--purple); }
.card-checkbox:checked::after {
    content: ''; width: 10px; height: 7px;
    border-left: 2px solid white; border-bottom: 2px solid white;
    transform: rotate(-45deg) translate(0px, -1px);
}
.article-card.selected { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow), var(--sh-md); }

/* ─── SEARCH RESULTS BAR ─── */
.search-results-bar {
    display: flex; align-items: center; gap: 8px;
    margin-bottom: 16px;
    font-size: 12px; color: var(--ink-faint); font-family: 'Fira Code', monospace;
}
.search-results-bar .sr-count {
    padding: 3px 10px; border-radius: 99px;
    background: var(--purple-light); color: var(--purple-md);
    font-weight: 700; border: 1px solid var(--trans-border); font-size: 11px;
}

/* ─── ARTICLE CARDS ─── */
.article-list { display: flex; flex-direction: column; gap: 22px; }
.article-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh-md); overflow: hidden;
    position: relative; transition: box-shadow .25s, border-color .25s, transform .25s;
    opacity: 0; transform: translateY(16px);
}
.article-card.visible { opacity: 1; transform: none; transition: opacity .45s ease, transform .45s ease, box-shadow .25s, border-color .25s; }
.article-card:hover { box-shadow: var(--sh-xl); border-color: var(--border-md); transform: translateY(-2px); }
.article-card.selected { transform: translateY(-2px); }

.card-watermark {
    position: absolute; top: 12px; right: 16px; z-index: 0;
    font-family: 'Playfair Display', serif;
    font-size: 80px; font-weight: 700;
    color: var(--ink-ghost); pointer-events: none; user-select: none; line-height: 1;
    transition: color .25s;
}
.article-card:hover .card-watermark { color: rgba(124,58,237,.055); }

/* Card Header */
.card-hd {
    padding: 18px 22px 14px; position: relative; z-index: 1;
    border-bottom: 1px solid var(--border);
    display: grid; grid-template-columns: auto 1fr auto; gap: 16px; align-items: start;
}
.dialect-glyph {
    width: 54px; height: 54px; border-radius: 12px;
    background: var(--trans-bg); border: 1px solid var(--trans-border);
    display: flex; align-items: center; justify-content: center;
    font-size: 26px; line-height: 1; flex-shrink: 0;
    transition: transform .2s, box-shadow .2s;
}
.article-card:hover .dialect-glyph { transform: scale(1.05) rotate(-3deg); box-shadow: 0 4px 14px var(--purple-glow); }
.card-info { min-width: 0; }
.badges-row { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 9px; }
.badge {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 3px 9px; border-radius: 99px;
    font-size: 10px; font-weight: 600; letter-spacing: .03em; border: 1px solid transparent;
}
.badge .material-icons-round { font-size: 11px !important; }
.badge-id   { background: var(--purple-light); color: var(--purple-md); border-color: var(--trans-border); font-family: 'Fira Code', monospace; }
.badge-done { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
.badge-cat  { background: var(--surface-3); color: var(--ink-faint); border-color: var(--border); }
.status-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 99px;
    font-size: 10px; font-weight: 600; border: 1px solid transparent;
}
.status-dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
.card-title { font-family: 'Playfair Display', serif; font-size: 16px; line-height: 1.4; color: var(--ink); margin-bottom: 9px; font-weight: 600; }
.card-meta-row { display: flex; flex-wrap: wrap; gap: 10px; font-size: 11px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }
.card-meta-item { display: flex; align-items: center; gap: 4px; }
.card-meta-item .material-icons-round { font-size: 12px !important; }
.card-meta-item.highlight { color: var(--purple-md); }
.card-actions { display: flex; flex-direction: column; gap: 6px; flex-shrink: 0; align-items: flex-end; }
.dialect-tag {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 99px;
    background: var(--purple-light); border: 1px solid var(--trans-border);
    font-size: 11px; font-weight: 700; color: var(--purple-md); white-space: nowrap;
}
.card-act-row { display: flex; gap: 5px; flex-wrap: wrap; justify-content: flex-end; }
.act-btn {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 6px 11px; border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 600;
    cursor: pointer; border: none; text-decoration: none;
    transition: all .15s; white-space: nowrap;
}
.act-btn .material-icons-round { font-size: 13px !important; }
.act-purple { background: var(--purple); color: white; }
.act-purple:hover { background: var(--purple-md); box-shadow: 0 3px 12px var(--purple-glow); }
.act-ghost  { background: var(--canvas); border: 1px solid var(--border); color: var(--ink-muted); }
.act-ghost:hover  { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.act-green  { background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; }
.act-green:hover  { background: #D1FAE5; }
.act-red    { background: #FFF1F2; border: 1px solid #FECDD3; color: #DC2626; }
.act-red:hover    { background: #FFE4E6; }
@media(max-width:760px){
    .card-hd { grid-template-columns: auto 1fr; }
    .card-actions { grid-column: 1/-1; flex-direction: row; align-items: center; flex-wrap: wrap; }
}

/* ─── DUAL PANELS ─── */
.dual-panels { display: grid; grid-template-columns: 1fr 3px 1fr; position: relative; z-index: 1; }
@media(max-width:900px) { .dual-panels { grid-template-columns: 1fr; } .panel-rule { display: none; } }
.panel-rule {
    background: linear-gradient(180deg,transparent 0%,var(--orig-rule) 15%,var(--purple-rule) 50%,var(--trans-rule) 85%,transparent 100%);
    position: relative;
}
.panel-rule::after {
    content: ''; position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 7px; height: 7px; border-radius: 50%;
    background: var(--purple);
    box-shadow: 0 0 0 3px var(--surface), 0 0 0 5px var(--trans-border);
}
.doc-panel { display: flex; flex-direction: column; overflow: hidden; }
.panel-orig  { background: var(--orig-bg); }
.panel-trans { background: var(--trans-bg); }

/* Panel components */
.panel-top { padding: 10px 20px; border-bottom: 1px solid; display: flex; align-items: center; gap: 8px; }
.panel-orig  .panel-top { border-color: var(--orig-border); background: var(--orig-bg-hd); }
.panel-trans .panel-top { border-color: var(--trans-border); background: var(--trans-bg-hd); }
.panel-dot-ring { width: 10px; height: 10px; border-radius: 50%; position: relative; flex-shrink: 0; }
.panel-orig  .panel-dot-ring { background: var(--orig-dot); box-shadow: 0 0 0 3px rgba(59,130,246,.2); }
.panel-trans .panel-dot-ring { background: var(--trans-dot); box-shadow: 0 0 0 3px rgba(124,58,237,.2); }
.panel-lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .12em; }
.panel-orig  .panel-lbl { color: var(--orig-label); }
.panel-trans .panel-lbl { color: var(--trans-label); }
.panel-lang-chip {
    margin-left: auto; padding: 2px 10px; border-radius: 99px;
    font-size: 10px; font-weight: 600; font-family: 'Fira Code', monospace;
    background: var(--surface); border: 1px solid var(--border-md); color: var(--ink-muted);
}
.panel-meta-chips { display: flex; gap: 5px; align-items: center; margin-left: 8px; }
.panel-meta-chip {
    padding: 2px 7px; border-radius: 99px;
    font-size: 9px; font-weight: 600; font-family: 'Fira Code', monospace;
    background: var(--surface); border: 1px solid var(--border); color: var(--ink-faint);
}
.panel-title-strip { padding: 10px 20px; border-bottom: 1px solid; }
.panel-orig  .panel-title-strip { border-color: var(--orig-border); }
.panel-trans .panel-title-strip { border-color: var(--trans-border); }
.strip-eyebrow { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; margin-bottom: 5px; font-family: 'Fira Code', monospace; }
.panel-orig  .strip-eyebrow { color: var(--orig-label); }
.panel-trans .strip-eyebrow { color: var(--trans-label); }
.strip-title { font-family: 'Playfair Display', serif; font-size: 13.5px; line-height: 1.45; color: var(--ink); }
.strip-empty { font-size: 12px; color: var(--ink-faint); font-style: italic; }

.panel-content { padding: 16px 20px; flex: 1; position: relative; }
.panel-content-inner {
    font-size: 13px; color: var(--ink-muted); line-height: 1.85;
    max-height: 240px; overflow: hidden;
    transition: max-height .4s cubic-bezier(.4,0,.2,1);
}
.panel-content-inner.expanded { max-height: 2000px; }
.panel-content-inner p { margin-bottom: .6rem; }

/* Diff highlight */
.diff-word-added {
    background: var(--diff-add);
    border-bottom: 1px solid var(--diff-add-border);
    border-radius: 3px; padding: 0 2px;
}

.content-fade { position: absolute; bottom: 46px; left: 0; right: 0; height: 50px; pointer-events: none; transition: opacity .3s; }
.panel-orig  .content-fade { background: linear-gradient(transparent, var(--orig-bg)); }
.panel-trans .content-fade { background: linear-gradient(transparent, var(--trans-bg)); }
.content-fade.hidden { opacity: 0; }

.panel-footer { padding: 10px 16px; border-top: 1px solid; display: flex; align-items: center; gap: 8px; }
.panel-orig  .panel-footer { border-color: var(--orig-border); background: var(--orig-bg-hd); }
.panel-trans .panel-footer { border-color: var(--trans-border); background: var(--trans-bg-hd); }
.expand-btn {
    display: inline-flex; align-items: center; gap: 5px;
    font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 600;
    cursor: pointer; border: none; background: none; padding: 5px 9px;
    border-radius: var(--r-sm); transition: all .15s;
}
.panel-orig  .expand-btn { color: var(--orig-label); }
.panel-trans .expand-btn { color: var(--trans-label); }
.expand-btn:hover { background: var(--surface); }
.expand-btn .material-icons-round { font-size: 14px !important; transition: transform .3s; }
.expand-btn.expanded .material-icons-round { transform: rotate(180deg); }
.copy-btn {
    margin-left: auto;
    display: inline-flex; align-items: center; gap: 4px;
    font-family: 'Sora', sans-serif; font-size: 10px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border); background: var(--surface);
    color: var(--ink-faint); padding: 5px 10px; border-radius: var(--r-sm); transition: all .15s;
}
.copy-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.copy-btn.copied { border-color: #A7F3D0; background: #ECFDF5; color: #065F46; }
.copy-btn .material-icons-round { font-size: 12px !important; }
.word-diff-btn {
    display: inline-flex; align-items: center; gap: 4px;
    font-family: 'Sora', sans-serif; font-size: 10px; font-weight: 600;
    cursor: pointer; border: 1px solid var(--border); background: var(--surface);
    color: var(--ink-faint); padding: 5px 10px; border-radius: var(--r-sm); transition: all .15s;
}
.word-diff-btn:hover, .word-diff-btn.active { border-color: #A7F3D0; background: #ECFDF5; color: #065F46; }
.word-diff-btn .material-icons-round { font-size: 12px !important; }

/* Empty translation state */
.no-trans-state {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 32px 20px; text-align: center;
}
.nt-icon {
    width: 44px; height: 44px; border-radius: 50%;
    background: var(--purple-light); border: 1px solid var(--trans-border);
    display: flex; align-items: center; justify-content: center; margin-bottom: 12px;
}
.nt-icon .material-icons-round { font-size: 20px !important; color: var(--purple); }
.no-trans-state p { font-size: 12px; color: var(--ink-faint); margin-bottom: 14px; line-height: 1.5; }

/* ─── EMPTY STATE ─── */
.empty-state {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh-md);
    padding: 80px 24px; text-align: center;
}
.empty-icon {
    width: 80px; height: 80px; border-radius: 50%;
    background: var(--purple-light); border: 2px solid var(--trans-border);
    display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;
    animation: floatIdle 3s ease-in-out infinite;
}
@keyframes floatIdle { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-8px); } }
.empty-icon .material-icons-round { font-size: 38px !important; color: var(--purple); }
.empty-state h3 { font-family: 'Playfair Display', serif; font-size: 22px; margin-bottom: 10px; }
.empty-state p { font-size: 13px; color: var(--ink-faint); max-width: 340px; margin: 0 auto 22px; line-height: 1.65; }

/* ─── PAGINATION ─── */
.pagination-bar {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 14px 22px; margin-top: 22px;
    display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;
}
.pg-info { font-size: 12px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }
.pg-info strong { color: var(--purple-md); }
.pg-btns { display: flex; gap: 4px; align-items: center; }
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 9px;
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--surface); color: var(--ink-muted);
    font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 500;
    text-decoration: none; cursor: pointer; transition: all .15s;
}
.pg-btn:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.pg-btn.active { background: var(--purple); border-color: var(--purple); color: white; font-weight: 700; box-shadow: 0 3px 10px var(--purple-glow); }
.pg-btn .material-icons-round { font-size: 17px !important; }
.pg-ellipsis { width: 28px; text-align: center; color: var(--ink-faint); font-size: 14px; }
.per-page-wrap { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--ink-faint); }
.per-page-wrap select {
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--canvas); padding: 5px 26px 5px 10px;
    font-family: 'Sora', sans-serif; font-size: 12px; color: var(--ink);
    cursor: pointer; outline: none; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 8px center;
    transition: border-color .15s;
}
.per-page-wrap select:focus { border-color: var(--purple); }

/* ─── KEYBOARD SHORTCUT MODAL ─── */
.shortcut-backdrop {
    display: none; position: fixed; inset: 0; z-index: 500;
    background: rgba(0,0,0,.6); backdrop-filter: blur(4px);
    align-items: center; justify-content: center; padding: 24px;
}
.shortcut-backdrop.open { display: flex; animation: fadeIn .2s ease; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.shortcut-modal {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh-xl);
    width: 100%; max-width: 480px; overflow: hidden;
}
.shortcut-hd {
    padding: 18px 22px; border-bottom: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between;
}
.shortcut-hd h3 { font-family: 'Playfair Display', serif; font-size: 18px; }
.shortcut-close { background: none; border: none; cursor: pointer; color: var(--ink-faint); padding: 4px; border-radius: var(--r-xs); transition: color .15s; }
.shortcut-close:hover { color: var(--danger); }
.shortcut-close .material-icons-round { font-size: 18px !important; }
.shortcut-list { padding: 16px 22px; display: flex; flex-direction: column; gap: 10px; }
.shortcut-row { display: flex; align-items: center; justify-content: space-between; }
.shortcut-desc { font-size: 13px; color: var(--ink-muted); }
.shortcut-keys { display: flex; gap: 4px; }
.key {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 26px; height: 24px; padding: 0 6px;
    border-radius: 5px; border: 1px solid var(--border-md);
    background: var(--canvas); font-size: 11px; font-weight: 700;
    font-family: 'Fira Code', monospace; color: var(--ink-muted);
    box-shadow: 0 2px 0 var(--border-strong);
}

/* ─── TOAST ─── */
.toast-stack { position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 6px; pointer-events: none; }
.toast {
    display: flex; align-items: center; gap: 9px;
    padding: 10px 14px; border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 500;
    box-shadow: var(--sh-lg); pointer-events: all;
    animation: toastIn .22s ease; border: 1px solid; max-width: 300px;
}
.toast.success { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }
.toast.error   { background: #FFF1F2; color: #9F1239; border-color: #FECDD3; }
.toast.info    { background: var(--purple-light); color: var(--purple-md); border-color: var(--trans-border); }
.toast .material-icons-round { font-size: 16px !important; flex-shrink: 0; }
@keyframes toastIn { from { transform: translateX(10px); opacity: 0; } to { transform: none; opacity: 1; } }

/* ─── PRINT ─── */
@media print {
    .no-print { display: none !important; }
    body { background: white; }
    .article-card { box-shadow: none; border: 1px solid #ddd; break-inside: avoid; margin-bottom: 20px; opacity: 1 !important; transform: none !important; }
    .panel-content-inner { max-height: none !important; }
    .content-fade, #readingProgress { display: none; }
    .topbar { position: relative; }
    .panel-rule { display: none; }
    .dual-panels { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- Reading progress bar -->
<div id="readingProgress" aria-hidden="true"></div>

<div class="page-shell">

<!-- ══════════════════════════════════════ TOPBAR ══ -->
<nav class="topbar no-print" id="topbar">
    <a href="../user_dashboard.php" class="back-link">
        <span class="material-icons-round">arrow_back</span>Back to Dashboard
    </a>
    <div class="topbar-right">
        <!-- Keyboard shortcuts -->
        <button onclick="openShortcuts()" class="tb-btn tb-btn-icon" title="Keyboard shortcuts (?)">
            <span class="material-icons-round">keyboard</span>
        </button>
        <!-- Diff mode toggle -->
        <a href="?<?= http_build_query(array_merge($baseQueryParams, $diffMode ? [] : ['diff' => '1'])) ?>"
           class="tb-btn <?= $diffMode ? 'active' : '' ?>" title="Toggle word diff highlight">
            <span class="material-icons-round">compare</span>
            <?= $diffMode ? 'Diff On' : 'Diff' ?>
        </a>
        <!-- Export -->
        <div style="position:relative" id="exportWrap">
            <button onclick="toggleExportMenu()" class="tb-btn">
                <span class="material-icons-round">file_download</span>Export
                <span class="material-icons-round" style="font-size:14px!important;margin-left:-2px">arrow_drop_down</span>
            </button>
            <div id="exportMenu" style="display:none;position:absolute;right:0;top:calc(100% + 6px);
                 background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);
                 box-shadow:var(--sh-lg);z-index:100;min-width:160px;overflow:hidden;">
                <a href="?<?= http_build_query(array_merge($baseQueryParams, ['export' => 'csv'])) ?>"
                   style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:12px;
                          color:var(--ink-muted);text-decoration:none;transition:background .15s;"
                   onmouseover="this.style.background='var(--purple-pale)'"
                   onmouseout="this.style.background=''">
                    <span class="material-icons-round" style="font-size:16px!important;color:#059669">table_view</span>
                    Export as CSV
                </a>
                <a href="?<?= http_build_query(array_merge($baseQueryParams, ['export' => 'json'])) ?>"
                   style="display:flex;align-items:center;gap:8px;padding:10px 16px;font-size:12px;
                          color:var(--ink-muted);text-decoration:none;transition:background .15s;border-top:1px solid var(--border)"
                   onmouseover="this.style.background='var(--purple-pale)'"
                   onmouseout="this.style.background=''">
                    <span class="material-icons-round" style="font-size:16px!important;color:#3B82F6">data_object</span>
                    Export as JSON
                </a>
            </div>
        </div>
        <button onclick="window.print()" class="tb-btn no-print">
            <span class="material-icons-round">print</span>Print
        </button>
        <button onclick="toggleDark()" id="darkBtn" class="tb-btn tb-btn-icon" title="Toggle dark mode">
            <span class="material-icons-round">dark_mode</span>
        </button>
    </div>
</nav>

<!-- ══════════════════════════════════════ HERO ══ -->
<div class="hero">
    <div class="hero-top">
        <div class="hero-left">
            <div class="hero-eyebrow">
                <span class="material-icons-round">translate</span>
                Filipino Dialect Archive
            </div>
            <h1 class="hero-title"><em>Translated</em> Articles</h1>
            <p class="hero-sub">
                Side-by-side bilingual view of all articles translated into Filipino dialects.
            </p>
            <div class="stat-pills">
                <span class="stat-pill">
                    <span class="material-icons-round">article</span>
                    <strong><?= $totalItems ?></strong> article<?= $totalItems !== 1 ? 's' : '' ?>
                </span>
                <?php foreach (array_slice($dialectCounts, 0, 4, true) as $lang => $cnt):
                    $icon = $dialectIcons[$lang] ?? '🌐'; ?>
                <span class="stat-pill">
                    <span class="dialect-pill-emoji"><?= $icon ?></span>
                    <strong><?= $cnt ?></strong> <?= e($lang) ?>
                </span>
                <?php endforeach; ?>
                <?php if (count($dialectCounts) > 4): ?>
                <span class="stat-pill">
                    <span class="material-icons-round">more_horiz</span>
                    +<?= count($dialectCounts) - 4 ?> more dialects
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Search panel -->
        <form method="GET" id="searchForm" class="search-panel no-print">
            <?php foreach (array_diff_key($baseQueryParams, ['search' => '', 'page' => '']) as $k => $v): ?>
                <input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>">
            <?php endforeach; ?>
            <div class="search-input-wrap">
                <span class="material-icons-round si">search</span>
                <input type="text" name="search" id="searchInput"
                       value="<?= e($search) ?>"
                       placeholder="Search articles, titles, dialects…"
                       autocomplete="off"/>
            </div>
            <?php if (!empty($search)): ?>
            <a href="translated_list.php" class="search-clear-btn" title="Clear search">
                <span class="material-icons-round">close</span>
            </a>
            <?php else: ?>
            <button type="submit" class="tb-btn" style="flex-shrink:0">
                <span class="material-icons-round">search</span>
            </button>
            <?php endif; ?>
        </form>
    </div>

    <!-- ─── Filter Bar ─── -->
    <div class="filter-bar no-print">
        <span class="filter-label">Filter</span>

        <!-- Dialect -->
        <select class="filter-select" onchange="applyFilter('dialect', this.value)" title="Filter by dialect">
            <option value="">All Dialects</option>
            <?php foreach ($allDialects as $d): ?>
            <option value="<?= e($d) ?>" <?= $filterDialect === $d ? 'selected' : '' ?>>
                <?= ($dialectIcons[$d] ?? '🌐') . ' ' . e($d) ?>
            </option>
            <?php endforeach; ?>
        </select>

        <!-- Status -->
        <select class="filter-select" onchange="applyFilter('status', this.value)" title="Filter by status">
            <option value="">All Statuses</option>
            <?php foreach ($statusMap as $val => $s): ?>
            <option value="<?= $val ?>" <?= $filterStatus === (string)$val ? 'selected' : '' ?>>
                <?= $s['label'] ?>
            </option>
            <?php endforeach; ?>
        </select>

        <div class="filter-divider"></div>

        <!-- Sort -->
        <span class="filter-label">Sort</span>
        <a href="<?= e(buildSortUrl('translated_at', $sortField, $sortDir, $baseQueryParams)) ?>"
           class="sort-btn <?= $sortField === 'translated_at' ? 'active' : '' ?>">
            <span class="material-icons-round"><?= $sortField === 'translated_at' ? ($sortDir === 'DESC' ? 'arrow_downward' : 'arrow_upward') : 'schedule' ?></span>
            Translated
        </a>
        <a href="<?= e(buildSortUrl('created_at', $sortField, $sortDir, $baseQueryParams)) ?>"
           class="sort-btn <?= $sortField === 'created_at' ? 'active' : '' ?>">
            <span class="material-icons-round"><?= $sortField === 'created_at' ? ($sortDir === 'DESC' ? 'arrow_downward' : 'arrow_upward') : 'calendar_today' ?></span>
            Created
        </a>
        <a href="<?= e(buildSortUrl('title', $sortField, $sortDir, $baseQueryParams)) ?>"
           class="sort-btn <?= $sortField === 'title' ? 'active' : '' ?>">
            <span class="material-icons-round"><?= $sortField === 'title' ? ($sortDir === 'DESC' ? 'arrow_downward' : 'arrow_upward') : 'sort_by_alpha' ?></span>
            Title
        </a>

        <div class="filter-bar-right">
            <!-- Active filter chips -->
            <?php if (!empty($filterDialect)): ?>
            <span class="active-filter-chip">
                <?= $dialectIcons[$filterDialect] ?? '🌐' ?> <?= e($filterDialect) ?>
                <a href="?<?= http_build_query(array_merge($baseQueryParams, ['dialect' => ''])) ?>">
                    <span class="material-icons-round">close</span>
                </a>
            </span>
            <?php endif; ?>
            <?php if ($filterStatus !== ''): ?>
            <span class="active-filter-chip">
                <?= $statusMap[(int)$filterStatus]['label'] ?? '' ?>
                <a href="?<?= http_build_query(array_merge($baseQueryParams, ['status' => ''])) ?>">
                    <span class="material-icons-round">close</span>
                </a>
            </span>
            <?php endif; ?>

            <!-- Select all toggle -->
            <button onclick="toggleSelectMode()" id="selectModeBtn" class="tb-btn">
                <span class="material-icons-round">checklist</span>
                Select
            </button>

            <!-- Clear all filters -->
            <?php if (!empty($search) || !empty($filterDialect) || $filterStatus !== ''): ?>
            <a href="translated_list.php" class="tb-btn" style="color:var(--danger);border-color:var(--danger)">
                <span class="material-icons-round">filter_alt_off</span>Clear All
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($search)): ?>
<div class="search-results-bar no-print">
    <span>Results for</span>
    <span class="sr-count" id="srCount"><?= $totalItems ?> found</span>
    <span>matching <strong style="color:var(--ink)">"<?= e($search) ?>"</strong></span>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════ BULK SELECT BAR ══ -->
<div class="bulk-bar no-print" id="bulkBar">
    <span class="material-icons-round" style="font-size:18px!important">checklist</span>
    <span class="bulk-bar-count" id="bulkCount">0 selected</span>
    <div class="bulk-divider"></div>
    <button class="bulk-act-btn" onclick="bulkExportSelected('csv')">
        <span class="material-icons-round">table_view</span>Export CSV
    </button>
    <button class="bulk-act-btn" onclick="bulkExportSelected('json')">
        <span class="material-icons-round">data_object</span>Export JSON
    </button>
    <button class="bulk-act-btn" onclick="bulkCopyTitles()">
        <span class="material-icons-round">content_copy</span>Copy Titles
    </button>
    <button class="bulk-act-btn" onclick="selectAll()">
        <span class="material-icons-round">select_all</span>Select All
    </button>
    <button class="bulk-dismiss" onclick="clearSelection()" title="Clear selection">
        <span class="material-icons-round">close</span>
    </button>
</div>

<?php if (empty($articles)): ?>
<!-- ══════════════════════════════════ EMPTY STATE ══ -->
<div class="empty-state">
    <div class="empty-icon"><span class="material-icons-round">translate</span></div>
    <h3><?= !empty($search) ? 'No Results Found' : 'No Translated Articles Yet' ?></h3>
    <p><?= !empty($search)
        ? 'No articles match &ldquo;' . e($search) . '&rdquo;. Try a different keyword or clear the filter.'
        : 'Head to the dashboard and translate an article to see it appear here in the codex.' ?>
    </p>
    <a href="../user_dashboard.php" class="act-btn act-purple">
        <span class="material-icons-round">arrow_back</span>Go to Dashboard
    </a>
</div>

<?php else: ?>

<!-- ══════════════════════════════════ ARTICLE LIST ══ -->
<div class="article-list" id="articleList">
<?php foreach ($articles as $idx => $article):
    $status      = $statusMap[$article['is_pushed']] ?? $statusMap[0];
    $dialectIcon = $dialectIcons[$article['translated_lang']] ?? '🌐';
    $translatedAt = !empty($article['translated_at'])
        ? date('M d, Y · g:i A', strtotime($article['translated_at'])) : '—';
    $createdAt    = !empty($article['created_at'])
        ? date('M d, Y · g:i A', strtotime($article['created_at'])) : '—';
    $origWords  = $article['content']         ? str_word_count(strip_tags($article['content'])) : 0;
    $transWords = $article['translated_body'] ? str_word_count(strip_tags($article['translated_body'])) : 0;
    $origRead   = max(1, ceil($origWords / 200));
    $transRead  = max(1, ceil($transWords / 200));
?>
<article class="article-card"
         data-index="<?= $idx ?>"
         data-id="<?= $article['id'] ?>"
         data-title="<?= e($article['title']) ?>"
         data-lang="<?= e($article['translated_lang'] ?? '') ?>"
         style="transition-delay: <?= min($idx * 60, 360) ?>ms">

    <!-- Bulk select checkbox -->
    <div class="card-select-wrap no-print">
        <input type="checkbox" class="card-checkbox" data-id="<?= $article['id'] ?>"
               data-title="<?= e($article['title']) ?>"
               onchange="updateBulkBar()"/>
    </div>

    <!-- Watermark -->
    <span class="card-watermark" aria-hidden="true"><?= $article['id'] ?></span>

    <!-- ── Card Header ── -->
    <div class="card-hd">
        <div class="dialect-glyph" title="<?= e($article['translated_lang'] ?? '') ?>"><?= $dialectIcon ?></div>

        <div class="card-info">
            <div class="badges-row">
                <span class="badge badge-id"><span class="material-icons-round">tag</span><?= $article['id'] ?></span>
                <span class="status-badge" style="background:<?= $status['bg'] ?>;color:<?= $status['text'] ?>;border-color:<?= $status['dot'] ?>33">
                    <span class="status-dot" style="background:<?= $status['dot'] ?>"></span><?= $status['label'] ?>
                </span>
                <span class="badge badge-done"><span class="material-icons-round">check_circle</span>Translated</span>
                <?php if (!empty($article['category_name'])): ?>
                <span class="badge badge-cat"><span class="material-icons-round">label</span><?= e($article['category_name']) ?></span>
                <?php endif; ?>
            </div>
            <h2 class="card-title"><?= e($article['title']) ?></h2>
            <div class="card-meta-row">
                <span class="card-meta-item"><span class="material-icons-round">person</span><?= e($article['username']) ?></span>
                <span class="card-meta-item"><span class="material-icons-round">business</span><?= e($article['dept_name']) ?></span>
                <span class="card-meta-item"><span class="material-icons-round">schedule</span><?= $createdAt ?></span>
                <span class="card-meta-item highlight"><span class="material-icons-round">translate</span>Translated <?= $translatedAt ?></span>
            </div>
        </div>

        <div class="card-actions no-print">
            <span class="dialect-tag">
                <span style="font-size:13px;line-height:1"><?= $dialectIcon ?></span>
                <?= e($article['translated_lang'] ?? '') ?>
            </span>
            <div class="card-act-row">
                <a href="view_translated.php?id=<?= $article['id'] ?>" class="act-btn act-purple">
                    <span class="material-icons-round">open_in_full</span>Full View
                </a>
                <a href="../function/translate.php?id=<?= $article['id'] ?>" class="act-btn act-ghost">
                    <span class="material-icons-round">translate</span>Re-translate
                </a>
                <a href="../function/update.php?id=<?= $article['id'] ?>" class="act-btn act-green">
                    <span class="material-icons-round">edit</span>Edit
                </a>
            </div>
        </div>
    </div>

    <!-- ── Dual Panels ── -->
    <div class="dual-panels">

        <!-- Original -->
        <div class="doc-panel panel-orig">
            <div class="panel-top">
                <span class="panel-dot-ring"></span>
                <span class="panel-lbl">Original Source</span>
                <div class="panel-meta-chips">
                    <span class="panel-meta-chip"><?= $origWords ?> words</span>
                    <span class="panel-meta-chip"><?= $origRead ?> min</span>
                </div>
                <span class="panel-lang-chip">English</span>
            </div>
            <div class="panel-title-strip">
                <div class="strip-eyebrow">Title</div>
                <div class="strip-title"><?= e($article['title']) ?></div>
            </div>
            <div class="panel-content">
                <div class="panel-content-inner" id="orig-content-<?= $article['id'] ?>">
                    <?php
                    $orig = $article['content'] ?? '';
                    if (strip_tags($orig) === $orig) {
                        foreach (array_filter(explode("\n\n", $orig)) as $p) echo '<p>' . nl2br(e(trim($p))) . '</p>';
                    } else { echo $orig; }
                    ?>
                </div>
                <div class="content-fade" id="orig-fade-<?= $article['id'] ?>"></div>
            </div>
            <div class="panel-footer no-print">
                <button class="expand-btn" id="orig-expand-<?= $article['id'] ?>"
                        onclick="toggleExpand('orig', <?= $article['id'] ?>)">
                    <span class="material-icons-round">expand_more</span>
                    <span class="expand-label">Show more</span>
                </button>
                <button class="copy-btn" id="orig-copy-<?= $article['id'] ?>"
                        onclick="copyText('orig', <?= $article['id'] ?>)">
                    <span class="material-icons-round">content_copy</span>Copy
                </button>
            </div>
        </div>

        <!-- Rule -->
        <div class="panel-rule" aria-hidden="true"></div>

        <!-- Translated -->
        <div class="doc-panel panel-trans">
            <div class="panel-top">
                <span class="panel-dot-ring"></span>
                <span class="panel-lbl">Translated</span>
                <?php if ($transWords > 0): ?>
                <div class="panel-meta-chips">
                    <span class="panel-meta-chip"><?= $transWords ?> words</span>
                    <span class="panel-meta-chip"><?= $transRead ?> min</span>
                </div>
                <?php endif; ?>
                <span class="panel-lang-chip"><?= $dialectIcon ?> <?= e($article['translated_lang'] ?? '') ?></span>
            </div>
            <div class="panel-title-strip">
                <div class="strip-eyebrow">Title</div>
                <?php if (!empty($article['translated_title'])): ?>
                <div class="strip-title"><?= e($article['translated_title']) ?></div>
                <?php else: ?>
                <div class="strip-empty">No translated title available.</div>
                <?php endif; ?>
            </div>

            <?php if (!empty($article['translated_body'])): ?>
            <div class="panel-content">
                <div class="panel-content-inner" id="trans-content-<?= $article['id'] ?>"
                     data-orig-id="orig-content-<?= $article['id'] ?>">
                    <?php
                    $trans = $article['translated_body'];
                    if (strip_tags($trans) === $trans) {
                        foreach (array_filter(explode("\n\n", $trans)) as $p) echo '<p>' . nl2br(e(trim($p))) . '</p>';
                    } else { echo $trans; }
                    ?>
                </div>
                <div class="content-fade" id="trans-fade-<?= $article['id'] ?>"></div>
            </div>
            <div class="panel-footer no-print">
                <button class="expand-btn" id="trans-expand-<?= $article['id'] ?>"
                        onclick="toggleExpand('trans', <?= $article['id'] ?>)">
                    <span class="material-icons-round">expand_more</span>
                    <span class="expand-label">Show more</span>
                </button>
                <?php if ($diffMode): ?>
                <button class="word-diff-btn" id="diff-btn-<?= $article['id'] ?>"
                        onclick="toggleWordDiff(<?= $article['id'] ?>)">
                    <span class="material-icons-round">compare</span>Diff
                </button>
                <?php endif; ?>
                <button class="copy-btn" id="trans-copy-<?= $article['id'] ?>"
                        onclick="copyText('trans', <?= $article['id'] ?>)">
                    <span class="material-icons-round">content_copy</span>Copy
                </button>
            </div>
            <?php else: ?>
            <div class="no-trans-state">
                <div class="nt-icon"><span class="material-icons-round">translate</span></div>
                <p>No translated content available for this article yet.</p>
                <a href="../function/translate.php?id=<?= $article['id'] ?>" class="act-btn act-purple no-print">
                    <span class="material-icons-round">translate</span>Translate Now
                </a>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- .dual-panels -->
</article>
<?php endforeach; ?>
</div><!-- .article-list -->

<!-- ══════════════════════════════════ PAGINATION ══ -->
<?php
$ps = max(1, $currentPage - 2);
$pe = min($totalPages, $currentPage + 2);
if ($totalPages > 1):
?>
<div class="pagination-bar no-print">
    <div class="pg-info">
        Showing <strong><?= $offset + 1 ?></strong>–<strong><?= min($offset + $itemsPerPage, $totalItems) ?></strong>
        of <strong><?= $totalItems ?></strong> articles
    </div>
    <div class="pg-btns">
        <?php if ($currentPage > 1): ?>
        <a href="?<?= http_build_query(array_merge($baseQueryParams, ['page' => $currentPage-1])) ?>" class="pg-btn">
            <span class="material-icons-round">chevron_left</span>
        </a>
        <?php else: ?>
        <span class="pg-btn" style="opacity:.4;pointer-events:none"><span class="material-icons-round">chevron_left</span></span>
        <?php endif; ?>

        <?php if ($ps > 1): ?>
        <a href="?<?= http_build_query(array_merge($baseQueryParams, ['page' => 1])) ?>" class="pg-btn">1</a>
        <?php if ($ps > 2): ?><span class="pg-ellipsis">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $ps; $i <= $pe; $i++): ?>
        <a href="?<?= http_build_query(array_merge($baseQueryParams, ['page' => $i])) ?>"
           class="pg-btn <?= $i === $currentPage ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($pe < $totalPages): ?>
        <?php if ($pe < $totalPages - 1): ?><span class="pg-ellipsis">…</span><?php endif; ?>
        <a href="?<?= http_build_query(array_merge($baseQueryParams, ['page' => $totalPages])) ?>" class="pg-btn"><?= $totalPages ?></a>
        <?php endif; ?>

        <?php if ($currentPage < $totalPages): ?>
        <a href="?<?= http_build_query(array_merge($baseQueryParams, ['page' => $currentPage+1])) ?>" class="pg-btn">
            <span class="material-icons-round">chevron_right</span>
        </a>
        <?php else: ?>
        <span class="pg-btn" style="opacity:.4;pointer-events:none"><span class="material-icons-round">chevron_right</span></span>
        <?php endif; ?>
    </div>
    <div class="per-page-wrap">
        <span>Show</span>
        <select onchange="applyFilter('per_page', this.value)">
            <?php foreach ([5,10,20,50] as $n): ?>
            <option value="<?= $n ?>" <?= $itemsPerPage===$n?'selected':'' ?>><?= $n ?></option>
            <?php endforeach; ?>
        </select>
        <span>per page</span>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<p class="page-footer no-print">
    Translated Articles &bull; <?= $totalItems ?> total &bull; <?= date('F j, Y') ?>
    <?php if ($diffMode): ?>
    &bull; <span style="color:var(--purple)">Diff Mode Active</span>
    <?php endif; ?>
</p>
</div><!-- .page-shell -->

<!-- ══════════════════════════════════ SHORTCUTS MODAL ══ -->
<div class="shortcut-backdrop no-print" id="shortcutBackdrop" onclick="closeShortcuts()">
    <div class="shortcut-modal" onclick="event.stopPropagation()">
        <div class="shortcut-hd">
            <h3>Keyboard Shortcuts</h3>
            <button onclick="closeShortcuts()" class="shortcut-close">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="shortcut-list">
            <div class="shortcut-row">
                <span class="shortcut-desc">Open shortcuts</span>
                <div class="shortcut-keys"><span class="key">?</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Focus search</span>
                <div class="shortcut-keys"><span class="key">/</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Toggle dark mode</span>
                <div class="shortcut-keys"><span class="key">D</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Toggle diff mode</span>
                <div class="shortcut-keys"><span class="key">Shift</span><span class="key">D</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Expand all cards</span>
                <div class="shortcut-keys"><span class="key">E</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Collapse all cards</span>
                <div class="shortcut-keys"><span class="key">C</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Toggle select mode</span>
                <div class="shortcut-keys"><span class="key">S</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Select all / deselect</span>
                <div class="shortcut-keys"><span class="key">Ctrl</span><span class="key">A</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Close modal / clear</span>
                <div class="shortcut-keys"><span class="key">Esc</span></div>
            </div>
            <div class="shortcut-row">
                <span class="shortcut-desc">Print</span>
                <div class="shortcut-keys"><span class="key">Ctrl</span><span class="key">P</span></div>
            </div>
        </div>
    </div>
</div>

<!-- Toast stack -->
<div class="toast-stack" id="toastStack"></div>

<script>
'use strict';

// ═══════════════════════════════════════ DARK MODE
function toggleDark() {
    const html = document.documentElement;
    const dark = html.dataset.theme === 'dark';
    html.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('theme', dark ? 'light' : 'dark');
    document.getElementById('darkBtn').querySelector('.material-icons-round').textContent =
        dark ? 'dark_mode' : 'light_mode';
}
(function () {
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.dataset.theme = t;
    const btn = document.getElementById('darkBtn');
    if (btn) btn.querySelector('.material-icons-round').textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
})();

// ═══════════════════════════════════════ READING PROGRESS
(function () {
    const bar = document.getElementById('readingProgress');
    if (!bar) return;
    const onScroll = () => {
        const h  = document.documentElement;
        const pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
        bar.style.width = Math.min(100, pct) + '%';
        document.getElementById('topbar')?.classList.toggle('scrolled', h.scrollTop > 30);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
})();

// ═══════════════════════════════════════ CARD REVEAL
(function () {
    const cards = document.querySelectorAll('.article-card');
    if (!cards.length) return;
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
    }, { threshold: 0.06, rootMargin: '0px 0px -40px 0px' });
    cards.forEach(c => io.observe(c));
})();

// ═══════════════════════════════════════ EXPAND / COLLAPSE
const expandState = {};

function toggleExpand(side, id) {
    const key  = side + '-' + id;
    const body = document.getElementById(side + '-content-' + id);
    const fade = document.getElementById(side + '-fade-' + id);
    const btn  = document.getElementById(side + '-expand-' + id);
    if (!body || !btn) return;
    expandState[key] = !expandState[key];
    const expanded = expandState[key];
    body.classList.toggle('expanded', expanded);
    if (fade) fade.classList.toggle('hidden', expanded);
    btn.classList.toggle('expanded', expanded);
    btn.querySelector('.expand-label').textContent = expanded ? 'Show less' : 'Show more';
}

function expandAll() {
    document.querySelectorAll('.panel-content-inner').forEach(el => {
        if (!el.classList.contains('expanded')) {
            const id = el.id; // "orig-content-123"
            const parts = id.split('-'); // ["orig","content","123"]
            if (parts.length >= 3) toggleExpand(parts[0], parts[2]);
        }
    });
    showToast('All panels expanded', 'info');
}

function collapseAll() {
    document.querySelectorAll('.panel-content-inner.expanded').forEach(el => {
        const parts = el.id.split('-');
        if (parts.length >= 3) toggleExpand(parts[0], parts[2]);
    });
    showToast('All panels collapsed', 'info');
}

// Auto-hide expand button when content doesn't overflow
(function () {
    document.querySelectorAll('.panel-content-inner').forEach(el => {
        if (el.scrollHeight <= el.clientHeight + 8) {
            const parts = el.id.split('-');
            if (parts.length >= 3) {
                const fade = document.getElementById(parts[0] + '-fade-' + parts[2]);
                const btn  = document.getElementById(parts[0] + '-expand-' + parts[2]);
                if (fade) fade.style.display = 'none';
                if (btn)  btn.style.display  = 'none';
            }
        }
    });
})();

// ═══════════════════════════════════════ COPY TO CLIPBOARD
function copyText(side, id) {
    const body = document.getElementById(side + '-content-' + id);
    if (!body) return;
    navigator.clipboard.writeText((body.innerText || body.textContent || '').trim()).then(() => {
        const btn = document.getElementById(side + '-copy-' + id);
        if (btn) {
            const orig = btn.innerHTML;
            btn.classList.add('copied');
            btn.innerHTML = '<span class="material-icons-round">check</span> Copied!';
            setTimeout(() => { btn.classList.remove('copied'); btn.innerHTML = orig; }, 2200);
        }
        showToast('Content copied to clipboard', 'success');
    }).catch(() => showToast('Copy failed — select text manually', 'error'));
}

// ═══════════════════════════════════════ WORD DIFF HIGHLIGHT
const diffApplied = {};

function toggleWordDiff(id) {
    const transEl = document.getElementById('trans-content-' + id);
    const btn     = document.getElementById('diff-btn-' + id);
    if (!transEl) return;

    if (diffApplied[id]) {
        // Remove diff highlights
        transEl.querySelectorAll('.diff-word-added').forEach(el => {
            el.replaceWith(document.createTextNode(el.textContent));
        });
        diffApplied[id] = false;
        if (btn) { btn.classList.remove('active'); btn.querySelector('.material-icons-round').textContent = 'compare'; }
        return;
    }

    // Get all text words from translated panel
    const transWords = new Set(
        transEl.innerText.toLowerCase().match(/\b[\w']+\b/g) || []
    );

    // Get original panel words for comparison
    const origEl = document.getElementById('orig-content-' + id);
    const origWords = new Set(
        origEl ? (origEl.innerText.toLowerCase().match(/\b[\w']+\b/g) || []) : []
    );

    // Words in translated that don't appear in original = unique/translated words
    const uniqueWords = [...transWords].filter(w => !origWords.has(w) && w.length > 3);

    // Walk text nodes and wrap matches
    function highlightTextNode(node) {
        if (!uniqueWords.length) return;
        const text  = node.textContent;
        const regex = new RegExp(`\\b(${uniqueWords.map(w => w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|')})\\b`, 'gi');
        if (!regex.test(text)) return;
        const span = document.createElement('span');
        span.innerHTML = text.replace(regex, '<span class="diff-word-added">$1</span>');
        node.parentNode.replaceChild(span, node);
    }

    function walkNodes(el) {
        el.childNodes.forEach(node => {
            if (node.nodeType === 3 && node.textContent.trim()) highlightTextNode(node);
            else if (node.nodeType === 1 && !node.classList.contains('diff-word-added')) walkNodes(node);
        });
    }

    walkNodes(transEl);
    diffApplied[id] = true;
    if (btn) {
        btn.classList.add('active');
        btn.querySelector('.material-icons-round').textContent = 'compare_arrows';
    }
    showToast('Words unique to the translation are highlighted', 'info');
}

// ═══════════════════════════════════════ FILTER APPLY
function applyFilter(key, value) {
    const params = new URLSearchParams(window.location.search);
    if (value === '' || value === null) params.delete(key);
    else params.set(key, value);
    params.set('page', '1');
    window.location.href = '?' + params.toString();
}

// ═══════════════════════════════════════ EXPORT MENU
function toggleExportMenu() {
    const menu = document.getElementById('exportMenu');
    if (!menu) return;
    const open = menu.style.display !== 'none';
    menu.style.display = open ? 'none' : 'block';
}
document.addEventListener('click', e => {
    if (!document.getElementById('exportWrap')?.contains(e.target)) {
        const m = document.getElementById('exportMenu');
        if (m) m.style.display = 'none';
    }
});

// ═══════════════════════════════════════ BULK SELECTION
let selectMode = false;

function toggleSelectMode() {
    selectMode = !selectMode;
    document.getElementById('articleList')?.classList.toggle('select-mode', selectMode);
    const btn = document.getElementById('selectModeBtn');
    if (btn) {
        btn.classList.toggle('active', selectMode);
        btn.querySelector('.material-icons-round').textContent = selectMode ? 'check_box' : 'checklist';
    }
    if (!selectMode) clearSelection();
    else showToast('Click cards to select them', 'info');
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.card-checkbox:checked');
    const bar     = document.getElementById('bulkBar');
    const count   = document.getElementById('bulkCount');
    if (bar)   bar.classList.toggle('visible', checked.length > 0);
    if (count) count.textContent = checked.length + ' selected';

    // Mark selected state on card
    document.querySelectorAll('.article-card').forEach(card => {
        const cb = card.querySelector('.card-checkbox');
        card.classList.toggle('selected', cb?.checked || false);
    });
}

function selectAll() {
    document.querySelectorAll('.card-checkbox').forEach(cb => { cb.checked = true; });
    updateBulkBar();
}

function clearSelection() {
    document.querySelectorAll('.card-checkbox').forEach(cb => { cb.checked = false; });
    updateBulkBar();
    if (selectMode) toggleSelectMode();
}

function bulkExportSelected(format) {
    const ids = [...document.querySelectorAll('.card-checkbox:checked')]
        .map(cb => cb.dataset.id)
        .join(',');
    if (!ids) { showToast('No articles selected', 'error'); return; }
    showToast('Exporting ' + ids.split(',').length + ' articles…', 'info');
    // Appends selected IDs — your export handler can use GET['ids'] to filter
    window.location.href = '?' + new URLSearchParams({
        ...Object.fromEntries(new URLSearchParams(window.location.search)),
        export: format,
        ids: ids
    }).toString();
}

function bulkCopyTitles() {
    const titles = [...document.querySelectorAll('.card-checkbox:checked')]
        .map(cb => cb.dataset.title)
        .join('\n');
    if (!titles) { showToast('No articles selected', 'error'); return; }
    navigator.clipboard.writeText(titles).then(() => {
        showToast('Titles copied to clipboard!', 'success');
    });
}

// Click on card body to toggle checkbox in select mode
document.addEventListener('click', e => {
    if (!selectMode) return;
    const card = e.target.closest('.article-card');
    if (!card || e.target.closest('a') || e.target.closest('button') || e.target.classList.contains('card-checkbox')) return;
    const cb = card.querySelector('.card-checkbox');
    if (cb) { cb.checked = !cb.checked; updateBulkBar(); }
});

// ═══════════════════════════════════════ LIVE SEARCH
(function () {
    const input = document.getElementById('searchInput');
    if (!input) return;
    let timer;
    input.addEventListener('input', function () {
        clearTimeout(timer);
        const q = this.value.trim().toLowerCase();
        timer = setTimeout(() => {
            let visible = 0;
            document.querySelectorAll('.article-card').forEach(card => {
                const match = !q || card.textContent.toLowerCase().includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            const sr = document.getElementById('srCount');
            if (sr) sr.textContent = visible + ' found';
        }, 220);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Enter') { clearTimeout(timer); document.getElementById('searchForm')?.submit(); }
        if (e.key === 'Escape') { input.value = ''; input.dispatchEvent(new Event('input')); input.blur(); }
    });
})();

// ═══════════════════════════════════════ SHORTCUTS MODAL
function openShortcuts()  { document.getElementById('shortcutBackdrop')?.classList.add('open'); }
function closeShortcuts() { document.getElementById('shortcutBackdrop')?.classList.remove('open'); }

// ═══════════════════════════════════════ KEYBOARD SHORTCUTS
document.addEventListener('keydown', e => {
    const tag = document.activeElement?.tagName;
    const inInput = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';

    if (e.key === 'Escape') {
        closeShortcuts();
        if (selectMode) clearSelection();
        return;
    }
    if (e.key === '?') { openShortcuts(); return; }

    if (inInput) return; // Don't fire shortcuts while typing

    if (e.key === '/') {
        e.preventDefault();
        document.getElementById('searchInput')?.focus();
        return;
    }
    if (e.key === 'd' && !e.shiftKey && !e.ctrlKey && !e.metaKey) { toggleDark(); return; }
    if (e.key === 'D' && e.shiftKey) {
        // Toggle diff mode via URL
        const params = new URLSearchParams(window.location.search);
        params.has('diff') ? params.delete('diff') : params.set('diff', '1');
        window.location.href = '?' + params.toString();
        return;
    }
    if (e.key === 'e' && !e.ctrlKey) { expandAll(); return; }
    if (e.key === 'c' && !e.ctrlKey) { collapseAll(); return; }
    if (e.key === 's' && !e.ctrlKey) { toggleSelectMode(); return; }
    if (e.key === 'a' && (e.ctrlKey || e.metaKey)) {
        if (selectMode) { e.preventDefault(); selectAll(); }
    }
});

// ═══════════════════════════════════════ TOAST
function showToast(msg, type = 'success') {
    const stack = document.getElementById('toastStack');
    if (!stack) return;
    const icons = { success: 'check_circle', error: 'error_outline', info: 'info' };
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<span class="material-icons-round">${icons[type]||'info'}</span><span>${escHtml(msg)}</span>`;
    stack.appendChild(t);
    setTimeout(() => { t.style.transition='opacity .3s'; t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 2800);
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
</script>
</body>
</html>