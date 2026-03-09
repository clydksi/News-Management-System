<?php
require '../auth.php';
require '../db.php';
require '../csrf.php';
require '../admin/includes/access_control.php';

// Ensure approval columns exist (idempotent — fails silently if already present)
try { $pdo->exec("ALTER TABLE news ADD COLUMN pending_approval TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE news ADD COLUMN rejection_note TEXT NULL"); } catch (PDOException $e) {}

$visibleDeptIds = getVisibleDepartmentIds($pdo, $_SESSION);
$isSuperAdmin   = $_SESSION['role'] === 'superadmin';
$isAdmin        = in_array($_SESSION['role'], ['admin', 'superadmin']);
[$dw_n, $dp_n]  = buildDeptWhere($visibleDeptIds, 'n.department_id');

$s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE {$dw_n}"); $s->execute($dp_n); $totalArticles = $s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.is_active = 1"); $s->execute(); $activeUsers = $s->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$pendingReviews  = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Pending approval count (for admins)
$pendingApprovalCount = 0;
if ($isAdmin) {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE {$dw_n} AND n.pending_approval = 1");
        $s->execute($dp_n);
        $pendingApprovalCount = (int)$s->fetchColumn();
    } catch (PDOException $e) { /* column not yet created */ }
}

$itemsPerPage = isset($_GET['per_page']) ? max(9, min(60, intval($_GET['per_page']))) : 9;
$currentPage  = isset($_GET['page'])     ? max(1, intval($_GET['page']))              : 1;
$offset       = ($currentPage - 1) * $itemsPerPage;

$section          = $_GET['section']            ?? 'all';
$viewMode         = $_GET['view']               ?? 'flat';
$filterCategory   = $_GET['filter_category']    ?? '';
$filterDepartment = $_GET['filter_department']  ?? '';
$filterAuthor     = $_GET['filter_author']      ?? '';
$filterDateFrom   = $_GET['filter_date_from']   ?? '';
$filterDateTo     = $_GET['filter_date_to']     ?? '';
$filterSearch     = $_GET['filter_search']      ?? '';
$filterStatus     = $_GET['filter_status']      ?? ''; // NEW: replaces section tabs

$activeFilters = 0;
if (!empty($filterCategory))   $activeFilters++;
if (!empty($filterDepartment)) $activeFilters++;
if (!empty($filterAuthor))     $activeFilters++;
if (!empty($filterDateFrom))   $activeFilters++;
if (!empty($filterDateTo))     $activeFilters++;
if (!empty($filterSearch))     $activeFilters++;
if ($filterStatus !== '')      $activeFilters++;

$baseQuery = "
    SELECT n.*, u.username, d.name AS dept_name, c.name AS category_name,
           n.thumbnail, n.parent_article_id, n.is_update, n.update_type, n.update_number,
           COALESCE(n.pending_approval, 0) AS pending_approval,
           n.rejection_note,
           (SELECT COUNT(*) FROM news WHERE parent_article_id = n.id) AS update_count,
           (SELECT MAX(created_at) FROM news WHERE parent_article_id = n.id) AS latest_update_time,
           parent.title AS parent_title, parent.id AS parent_id
    FROM   news n
    JOIN   users       u ON n.created_by    = u.id
    JOIN   departments d ON n.department_id = d.id
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN news parent  ON n.parent_article_id = parent.id
";
$countQuery = "SELECT COUNT(*) AS total FROM news n
    JOIN users u ON n.created_by=u.id
    JOIN departments d ON n.department_id=d.id
    LEFT JOIN categories c ON n.category_id=c.id";

$whereClauses = []; $params = [];
$whereClauses[] = $dw_n; $params = array_merge($params, $dp_n);
if ($viewMode === 'threaded') $whereClauses[] = "n.is_update = 0";

// Archive section (sidebar link) always shows only archive
if ($section === 'archive') {
    $whereClauses[] = "n.is_pushed = 3";
} elseif ($filterStatus !== '') {
    // Status filter from advanced filters overrides the default exclusion
    $whereClauses[] = "n.is_pushed = ?"; $params[] = intval($filterStatus);
} else {
    // Default "All Articles" excludes archive
    $whereClauses[] = "n.is_pushed != 3";
}

if (!empty($filterCategory))               { $whereClauses[] = "n.category_id = ?";      $params[] = $filterCategory; }
if (!empty($filterDepartment) && $isAdmin) { $whereClauses[] = "n.department_id = ?";    $params[] = $filterDepartment; }
if (!empty($filterAuthor))                 { $whereClauses[] = "n.created_by = ?";        $params[] = $filterAuthor; }
if (!empty($filterDateFrom))               { $whereClauses[] = "DATE(n.created_at) >= ?"; $params[] = $filterDateFrom; }
if (!empty($filterDateTo))                 { $whereClauses[] = "DATE(n.created_at) <= ?"; $params[] = $filterDateTo; }
if (!empty($filterSearch)) {
    $whereClauses[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
}

if (!empty($whereClauses)) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
    $baseQuery  .= $whereSQL;
    $countQuery .= $whereSQL;
}

$countStmt = $pdo->prepare($countQuery); $countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];
$totalPages = max(1, ceil($totalItems / $itemsPerPage));
if ($currentPage > $totalPages) { $currentPage = $totalPages; $offset = ($currentPage - 1) * $itemsPerPage; }

$stmt = $pdo->prepare($baseQuery . " ORDER BY n.created_at DESC LIMIT " . intval($itemsPerPage) . " OFFSET " . intval($offset));
$stmt->execute($params); $paginatedNews = $stmt->fetchAll();

$bsw = "WHERE {$dw_n}";
$s = $pdo->prepare("SELECT COUNT(*) FROM news n $bsw AND n.is_pushed=0"); $s->execute($dp_n); $regularNewsCount  = $s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM news n $bsw AND n.is_pushed=1"); $s->execute($dp_n); $editedNewsCount   = $s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM news n $bsw AND n.is_pushed=2"); $s->execute($dp_n); $headlineNewsCount = $s->fetchColumn();
$s = $pdo->prepare("SELECT COUNT(*) FROM news n $bsw AND n.is_pushed=3"); $s->execute($dp_n); $archiveNewsCount  = $s->fetchColumn();

$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
[$dw_d, $dp_d] = buildDeptWhere($visibleDeptIds, 'd.id');
$deptStmt = $pdo->prepare("SELECT id, name FROM departments d WHERE {$dw_d} ORDER BY name"); $deptStmt->execute($dp_d); $departments = $deptStmt->fetchAll();
$authors = $pdo->query("SELECT id, username FROM users WHERE is_active=1 ORDER BY username")->fetchAll();

$maxPL = 5;
$ps    = max(1, $currentPage - floor($maxPL / 2));
$pe    = min($totalPages, $ps + $maxPL - 1);
if ($pe - $ps < $maxPL - 1) $ps = max(1, $pe - $maxPL + 1);

function getPaginationUrl($page, $section = null, $perPage = null) {
    $p = $_GET; $p['page'] = $page;
    if ($section && $section !== 'all') $p['section'] = $section;
    if ($perPage) $p['per_page'] = $perPage;
    return '?' . http_build_query($p);
}
function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function getThumbnailUrl($p) {
    if (!empty($p) && file_exists($p)) return $p;
    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="250"%3E%3Cdefs%3E%3ClinearGradient id="g" x1="0%25" y1="0%25" x2="100%25" y2="100%25"%3E%3Cstop offset="0%25" style="stop-color:%234C1D95"%3E%3C/stop%3E%3Cstop offset="100%25" style="stop-color:%237C3AED"%3E%3C/stop%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width="400" height="250" fill="url(%23g)"%3E%3C/rect%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="serif" font-size="18" fill="rgba(255,255,255,0.35)"%3ENo Image%3C/text%3E%3C/svg%3E';
}
function getStatusInfo($p) {
    return [
        0 => ['label'=>'Regular', 'dot'=>'#F59E0B','bg'=>'#FFF7ED','text'=>'#92400E'],
        1 => ['label'=>'Edited',  'dot'=>'#10B981','bg'=>'#ECFDF5','text'=>'#065F46'],
        2 => ['label'=>'Headline','dot'=>'#3B82F6','bg'=>'#EFF6FF','text'=>'#1D4ED8'],
        3 => ['label'=>'Archive', 'dot'=>'#EF4444','bg'=>'#FFF1F2','text'=>'#9F1239'],
    ][$p] ?? ['label'=>'Regular','dot'=>'#F59E0B','bg'=>'#FFF7ED','text'=>'#92400E'];
}
function getSectionTitle($s) {
    return match($s) {
        'archive' => 'Archive', default => 'All Articles'
    };
}
function getActiveFiltersArray() {
    global $filterCategory,$filterDepartment,$filterAuthor,$filterDateFrom,$filterDateTo,$filterSearch,$filterStatus,$categories,$departments,$authors;
    $out = [];
    $statusLabels = ['0'=>'Regular','1'=>'Edited','2'=>'Headlines','3'=>'Archive'];
    if ($filterStatus !== '') $out[]=['type'=>'Status','value'=>$statusLabels[$filterStatus]??'?','param'=>'filter_status'];
    if (!empty($filterCategory))   { $c=array_filter($categories,  fn($x)=>$x['id']==$filterCategory);  $out[]=['type'=>'Category','value'=>reset($c)['name']??'?',    'param'=>'filter_category']; }
    if (!empty($filterDepartment)) { $d=array_filter($departments, fn($x)=>$x['id']==$filterDepartment);$out[]=['type'=>'Dept',    'value'=>reset($d)['name']??'?',    'param'=>'filter_department']; }
    if (!empty($filterAuthor))     { $a=array_filter($authors,     fn($x)=>$x['id']==$filterAuthor);    $out[]=['type'=>'Author',  'value'=>reset($a)['username']??'?','param'=>'filter_author']; }
    if (!empty($filterDateFrom)) $out[]=['type'=>'From',  'value'=>date('M d, Y',strtotime($filterDateFrom)),'param'=>'filter_date_from'];
    if (!empty($filterDateTo))   $out[]=['type'=>'To',    'value'=>date('M d, Y',strtotime($filterDateTo)),  'param'=>'filter_date_to'];
    if (!empty($filterSearch))   $out[]=['type'=>'Search','value'=>$filterSearch,'param'=>'filter_search'];
    return $out;
}
function getArticleTypeBadge($a) {
    if ($a['is_update']==1) return ['label'=>($a['update_type']?:'Update').' #'.$a['update_number'],'type'=>'update','icon'=>'fiber_manual_record'];
    if ($a['update_count']>0) {
        $live = $a['latest_update_time'] && ((time()-strtotime($a['latest_update_time']))/3600)<24;
        return ['label'=>($live?'MAIN · ':'').$a['update_count'].' update'.($a['update_count']>1?'s':''),'type'=>$live?'live':'parent','icon'=>'account_tree','isLive'=>$live];
    }
    return null;
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>"/>
<title>Articles Dashboard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Sora:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
:root {
    --purple:#7C3AED;--purple-md:#6D28D9;--purple-dark:#4C1D95;
    --purple-light:#EDE9FE;--purple-pale:#F5F3FF;--purple-glow:rgba(124,58,237,.18);
    --ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;
    --canvas:#F3F1FA;--surface:#FFFFFF;--surface-2:#EEEAF8;
    --border:#E2DDEF;--border-md:#C9C2E0;
    --sb:#130F23;--sb2:#1A1535;--sb-txt:#D4CFE8;--sb-muted:#6B6485;--sb-act:rgba(124,58,237,.2);--sb-bd:rgba(255,255,255,.07);
    --r:13px;--r-sm:8px;--r-xs:5px;
    --sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);
    --sh-md:0 4px 16px rgba(60,20,120,.10),0 2px 5px rgba(60,20,120,.05);
    --sh-lg:0 12px 36px rgba(60,20,120,.16),0 4px 8px rgba(60,20,120,.07);
    --sh-xl:0 24px 60px rgba(0,0,0,.22),0 8px 16px rgba(0,0,0,.10);
}
[data-theme="dark"]{
    --ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;
    --canvas:#0E0C18;--surface:#17142A;--surface-2:#1E1A30;
    --border:#2A2540;--border-md:#362F50;
    --purple-light:#1E1440;--purple-pale:#150F2E;
    --sb:#0A0815;--sb2:#110D22;
    --sh:0 1px 3px rgba(0,0,0,.4);--sh-md:0 4px 16px rgba(0,0,0,.45);
    --sh-lg:0 12px 36px rgba(0,0,0,.55);--sh-xl:0 24px 60px rgba(0,0,0,.65);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;scroll-behavior:smooth;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility;font-synthesis:none}
body{font-family:'Sora',sans-serif;font-size:15px;line-height:1.65;background:var(--canvas);color:var(--ink);height:100vh;overflow:hidden;display:flex;flex-direction:column;transition:background .2s,color .2s}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--purple)}
.ld-overlay{position:fixed;inset:0;background:rgba(255,255,255,.9);display:flex;align-items:center;justify-content:center;z-index:9999;opacity:0;pointer-events:none;transition:opacity .25s;flex-direction:column;gap:10px}
[data-theme="dark"] .ld-overlay{background:rgba(14,12,24,.9)}
.ld-overlay.show{opacity:1;pointer-events:all}
.ld-ring{width:44px;height:44px;border:4px solid var(--border);border-top-color:var(--purple);border-radius:50%;animation:spin .85s linear infinite}
.ld-label{font-size:12px;color:var(--ink-faint);font-weight:500}
@keyframes spin{to{transform:rotate(360deg)}}
.mobile-header{display:none;background:var(--sb);padding:12px 16px;align-items:center;justify-content:space-between;border-bottom:1px solid var(--sb-bd);flex-shrink:0}
@media(max-width:1023px){.mobile-header{display:flex}}
.mb{display:flex;align-items:center;gap:9px}
.mb-mark{width:32px;height:32px;background:var(--purple);border-radius:8px;display:flex;align-items:center;justify-content:center}
.mb-mark .material-icons-round{font-size:17px!important;color:white}
.mb-name{font-family:'Playfair Display',serif;font-size:15px;color:#EAE6F8;font-weight:700}
.mh-r{display:flex;gap:6px;align-items:center}
.mh-btn{width:34px;height:34px;border-radius:8px;border:none;cursor:pointer;background:rgba(255,255,255,.08);color:var(--sb-txt);display:flex;align-items:center;justify-content:center;transition:background .15s;position:relative}
.mh-btn:hover{background:rgba(255,255,255,.15)}
.mh-btn .material-icons-round{font-size:18px!important}
.filter-dot{position:absolute;top:-3px;right:-3px;width:16px;height:16px;border-radius:50%;background:#EF4444;color:white;font-size:9px;font-weight:700;display:flex;align-items:center;justify-content:center}
.av-sm{width:32px;height:32px;border-radius:8px;background:var(--purple);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px}
.app-body{display:flex;flex:1;overflow:hidden;position:relative}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:25;backdrop-filter:blur(3px)}
.sb-overlay.show{display:block}
.sidebar{width:240px;flex-shrink:0;background:var(--sb);display:flex;flex-direction:column;z-index:30;transition:transform .28s cubic-bezier(.4,0,.2,1);border-right:1px solid var(--sb-bd)}
@media(max-width:1023px){.sidebar{position:fixed;top:0;left:0;height:100%;transform:translateX(-100%)}.sidebar.open{transform:none}}
.sb-hd{padding:18px 16px 14px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--sb-bd);flex-shrink:0}
.sb-mark{width:36px;height:36px;background:var(--purple);border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.sb-mark .material-icons-round{font-size:19px!important;color:white}
.sb-bname{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:#EAE6F8;line-height:1.1}
.sb-bsub{font-size:10px;color:var(--sb-muted);margin-top:1px;font-family:'Fira Code',monospace}
.sb-close{margin-left:auto;width:28px;height:28px;border-radius:6px;border:none;background:rgba(255,255,255,.07);color:var(--sb-txt);cursor:pointer;display:none;align-items:center;justify-content:center;transition:background .15s}
.sb-close .material-icons-round{font-size:16px!important}
.sb-close:hover{background:rgba(255,255,255,.15)}
@media(max-width:1023px){.sb-close{display:flex}}
.sb-nav{flex:1;overflow-y:auto;padding:12px 10px;display:flex;flex-direction:column;gap:16px}
.sb-sec{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--sb-muted);padding:0 8px;margin-bottom:4px}
.sb-links{display:flex;flex-direction:column;gap:2px}
.sb-link{display:flex;align-items:center;gap:9px;padding:9px 10px;border-radius:var(--r-sm);font-size:13px;font-weight:500;color:var(--sb-txt);text-decoration:none;cursor:pointer;border:none;width:100%;background:transparent;transition:all .15s;position:relative}
.sb-link .material-icons-round{font-size:17px!important;flex-shrink:0;color:var(--sb-muted)}
.sb-link:hover{background:rgba(255,255,255,.07);color:#EAE6F8}
.sb-link:hover .material-icons-round{color:var(--sb-txt)}
.sb-link.active{background:var(--sb-act);color:#EAE6F8}
.sb-link.active .material-icons-round{color:var(--purple-light)}
.sb-link.active::before{content:'';position:absolute;left:0;top:20%;height:60%;width:3px;background:var(--purple);border-radius:0 3px 3px 0}
.sb-cnt{margin-left:auto;padding:2px 7px;border-radius:99px;font-size:10px;font-weight:600;background:rgba(255,255,255,.1);color:var(--sb-muted);font-family:'Fira Code',monospace}
.sb-link.active .sb-cnt{background:var(--purple);color:white}
.sb-ext .mi-ext{font-size:12px!important;margin-left:auto;color:var(--sb-muted)}
.sb-dd-toggle{cursor:pointer}
.sb-dd-arrow{font-size:16px!important;margin-left:auto;transition:transform .25s}
.sb-dd-menu{overflow:hidden;max-height:0;transition:max-height .28s ease-out}
.sb-dd-menu.open{max-height:300px}
.sb-dd-item{display:flex;align-items:center;gap:8px;padding:8px 10px 8px 34px;font-size:12px;font-weight:500;color:var(--sb-muted);text-decoration:none;border-radius:var(--r-sm);transition:all .15s}
.sb-dd-item .material-icons-round{font-size:14px!important}
.sb-dd-item:hover{background:rgba(255,255,255,.06);color:var(--sb-txt)}
.sb-foot{padding:12px 10px;border-top:1px solid var(--sb-bd);flex-shrink:0;display:flex;flex-direction:column;gap:2px}
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);box-shadow:var(--sh);padding:14px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-shrink:0}
@media(max-width:1023px){.topbar{display:none}}
.tb-l{display:flex;align-items:center;gap:14px}
.tb-icon{width:44px;height:44px;background:var(--purple-light);border-radius:11px;display:flex;align-items:center;justify-content:center}
.tb-icon .material-icons-round{font-size:22px!important;color:var(--purple)}
.tb-title{font-family:'Playfair Display',serif;font-size:22px;color:var(--ink)}
.tb-sub{font-size:11px;color:var(--ink-faint);margin-top:2px;font-family:'Fira Code',monospace}
.tb-r{display:flex;align-items:center;gap:10px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:13px;font-weight:500;cursor:pointer;border:none;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn .material-icons-round{font-size:16px!important}
.btn-purple{background:var(--purple);color:white}
.btn-purple:hover{background:var(--purple-md);box-shadow:0 4px 12px var(--purple-glow)}
.btn-outline{background:var(--surface);border:1px solid var(--border);color:var(--ink-muted)}
.btn-outline:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.btn-icon{width:36px;height:36px;padding:0;justify-content:center}
.filter-badge{background:#EF4444;color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:99px;font-family:'Fira Code',monospace;margin-left:2px}
.user-chip{display:flex;align-items:center;gap:9px;padding:7px 12px 7px 7px;background:var(--canvas);border:1px solid var(--border);border-radius:99px}
.user-av{width:28px;height:28px;border-radius:50%;background:var(--purple);color:white;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px}
.user-name{font-size:13px;font-weight:600;color:var(--ink)}
.user-role{font-size:10px;color:var(--ink-faint)}
.msbar{display:none;padding:10px 14px;background:var(--surface);border-bottom:1px solid var(--border);flex-shrink:0}
@media(max-width:1023px){.msbar{display:block}}
.ms-wrap{position:relative}
.ms-wrap input{width:100%;padding:9px 14px 9px 36px;border:1px solid var(--border);border-radius:99px;background:var(--canvas);font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);outline:none;transition:border-color .15s,box-shadow .15s}
.ms-wrap input:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}
.ms-wrap input::placeholder{color:var(--ink-faint)}
.ms-wrap .si{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--ink-faint);font-size:16px!important;pointer-events:none}
.scroll-area{flex:1;overflow-y:auto;padding:22px 28px 60px}
@media(max-width:1023px){.scroll-area{padding:16px 14px 60px}}
.page-in{animation:pageIn .4s ease}
@keyframes pageIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:16px;display:flex;align-items:center;gap:12px;transition:all .2s;animation:pageIn .4s ease}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--sh-md);border-color:var(--border-md)}
.st-icon{width:44px;height:44px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.st-icon .material-icons-round{font-size:22px!important}
.st-num{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--ink);line-height:1}
.st-lbl{font-size:11px;color:var(--ink-faint);margin-top:3px;font-weight:500}
.fc{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:14px 18px;margin-bottom:18px}
.fc-hd{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.fc-title{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:var(--ink)}
.fc-title .material-icons-round{font-size:16px!important;color:var(--purple)}
.clear-btn{display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;color:#E11D48;border:none;background:none;cursor:pointer;padding:4px 8px;border-radius:var(--r-xs);transition:background .15s}
.clear-btn:hover{background:#FFF1F2}
.clear-btn .material-icons-round{font-size:13px!important}
.chips{display:flex;flex-wrap:wrap;gap:7px}
.chip{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;background:var(--purple-light);border:1px solid #C4B5FD;border-radius:99px;font-size:11px;font-weight:500;color:var(--purple-md);animation:chipIn .2s ease}
@keyframes chipIn{from{opacity:0;transform:scale(.9)}to{opacity:1;transform:scale(1)}}
.chip strong{font-weight:700}
.chip-x{width:16px;height:16px;border-radius:50%;border:none;cursor:pointer;background:var(--purple-md);color:white;display:flex;align-items:center;justify-content:center;transition:background .15s}
.chip-x:hover{background:var(--purple-dark)}
.chip-x .material-icons-round{font-size:10px!important}
.sec-hd{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:14px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px}
.sec-tl-wrap{display:flex;align-items:center;gap:10px}
.sec-icon{width:38px;height:38px;background:var(--purple-light);border-radius:9px;display:flex;align-items:center;justify-content:center}
.sec-icon .material-icons-round{font-size:19px!important;color:var(--purple)}
.sec-title{font-family:'Playfair Display',serif;font-size:18px;color:var(--ink)}
.sec-sub{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.sec-acts{display:flex;gap:8px;flex-wrap:wrap}
.ref-btn{width:34px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);color:var(--ink-faint);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .15s}
.ref-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.ref-btn .material-icons-round{font-size:18px!important}
.ref-btn.spinning .material-icons-round{animation:spin .7s linear infinite}
.articles-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:16px}
@media(max-width:600px){.articles-grid{grid-template-columns:1fr}}
.a-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;display:flex;flex-direction:column;transition:transform .22s,box-shadow .22s,border-color .22s;animation:pageIn .35s ease both}
.a-card:nth-child(1){animation-delay:.03s}.a-card:nth-child(2){animation-delay:.06s}.a-card:nth-child(3){animation-delay:.09s}.a-card:nth-child(4){animation-delay:.12s}.a-card:nth-child(5){animation-delay:.15s}.a-card:nth-child(6){animation-delay:.18s}.a-card:nth-child(7){animation-delay:.21s}.a-card:nth-child(8){animation-delay:.24s}.a-card:nth-child(9){animation-delay:.27s}
.a-card:hover{transform:translateY(-3px);box-shadow:var(--sh-lg);border-color:var(--border-md)}
.a-thumb{height:185px;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--purple-light),var(--surface-2));flex-shrink:0}
.a-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.a-card:hover .a-thumb img{transform:scale(1.06)}
.a-veil{position:absolute;inset:0;background:linear-gradient(to top,rgba(19,17,26,.55) 0%,transparent 55%);pointer-events:none}
.a-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.a-ph .material-icons-round{font-size:48px!important;color:rgba(255,255,255,.25)}
.a-tl{position:absolute;top:10px;left:10px;display:flex;flex-direction:column;gap:5px;z-index:3}
.a-tr{position:absolute;top:10px;right:10px;z-index:3}
.a-badge{display:inline-flex;align-items:center;gap:4px;padding:4px 9px;border-radius:6px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.a-badge .material-icons-round{font-size:11px!important}
.sb-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:99px;font-size:10px;font-weight:600;border:1px solid transparent}
.sb-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.live-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:6px;background:rgba(239,68,68,.9);color:white;font-size:10px;font-weight:700;animation:lp 2s ease-in-out infinite}
.ld-dot{width:7px;height:7px;border-radius:50%;background:white;animation:lp 1.5s ease-in-out infinite}
@keyframes lp{0%,100%{opacity:1}50%{opacity:.6}}
.a-body{padding:14px 16px;flex:1;display:flex;flex-direction:column;border-left:3px solid var(--purple)}
.a-body.ub{border-left-color:#F97316}
.a-body.pb{border-left-color:#3B82F6}
.a-body.lb{border-left-color:#EF4444}
.par-ref{display:flex;align-items:center;gap:5px;padding:6px 10px;border-radius:var(--r-sm);background:#FFF7ED;border:1px solid #FED7AA;margin-bottom:10px;font-size:11px;color:#92400E}
.par-ref .material-icons-round{font-size:12px!important;flex-shrink:0}
.par-ref button{background:none;border:none;cursor:pointer;color:inherit;font-size:11px;flex:1;text-align:left;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-family:'Sora',sans-serif}
.par-ref button:hover{text-decoration:underline}
[data-theme="dark"] .par-ref{background:#1A1010;border-color:#431407}
.a-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px}
.a-tag{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:99px;font-size:10px;font-weight:600;border:1px solid transparent}
.a-tag .material-icons-round{font-size:11px!important}
.t-cat{background:var(--purple-light);color:var(--purple-md);border-color:#C4B5FD}
.t-upd{background:#FFF7ED;color:#92400E;border-color:#FED7AA}
.t-par{background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE}
[data-theme="dark"] .t-upd{background:#1A1010;border-color:#431407}
[data-theme="dark"] .t-par{background:#0E1829;border-color:#1E3A5F}
.a-title{font-family:'Playfair Display',serif;font-size:15px;line-height:1.4;color:var(--ink);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:8px;cursor:pointer;transition:color .15s}
.a-title:hover{color:var(--purple)}
.a-prev{font-size:12px;color:var(--ink-muted);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:10px;flex:1}
.a-meta{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:10px 0;border-top:1px solid var(--border);margin-bottom:10px}
.a-mi{display:flex;align-items:center;gap:7px;min-width:0}
.a-mi-icon{width:28px;height:28px;border-radius:7px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.a-mi-icon .material-icons-round{font-size:14px!important}
.a-mi-v{font-size:11px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.a-mi-s{font-size:10px;color:var(--ink-faint)}
.a-date{display:flex;align-items:center;gap:6px;padding:6px 10px;background:var(--canvas);border-radius:var(--r-sm);font-size:11px;color:var(--ink-faint);margin-bottom:12px;font-family:'Fira Code',monospace}
.a-date .material-icons-round{font-size:13px!important;color:var(--purple)}
.a-acts{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.ab{display:flex;align-items:center;justify-content:center;gap:4px;padding:7px 10px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:all .15s;white-space:nowrap}
.ab .material-icons-round{font-size:13px!important}
.ab-bl{background:#EFF6FF;border-color:#BFDBFE;color:#1D4ED8}.ab-bl:hover{background:#DBEAFE}
.ab-gr{background:#ECFDF5;border-color:#A7F3D0;color:#065F46}.ab-gr:hover{background:#D1FAE5}
.ab-rd{background:#FFF1F2;border-color:#FECDD3;color:#9F1239}.ab-rd:hover{background:#FFE4E6}
.ab-yl{background:#FFFBEB;border-color:#FDE68A;color:#92400E}.ab-yl:hover{background:#FEF3C7}
.ab-vi{background:var(--purple-light);border-color:#C4B5FD;color:var(--purple-md)}.ab-vi:hover{background:var(--purple-pale)}
.ab-tl{background:#F0FDFA;border-color:#99F6E4;color:#0F766E}.ab-tl:hover{background:#CCFBF1}
.ab-or{background:#FFF7ED;border-color:#FED7AA;color:#92400E}.ab-or:hover{background:#FFEDD5}
.ab-am{background:#FFFBEB;border-color:#FDE68A;color:#78350F}.ab-am:hover{background:#FEF3C7}
.ab-in{background:#EEF2FF;border-color:#C7D2FE;color:#3730A3}.ab-in:hover{background:#E0E7FF}
.ab-gy{background:var(--canvas);border-color:var(--border);color:var(--ink-muted)}.ab-gy:hover{background:var(--surface-2)}
.ab-pu{background:linear-gradient(135deg,#10B981,#059669);border:none;color:white}.ab-pu:hover{box-shadow:0 4px 12px rgba(16,185,129,.35)}
.ab-full{grid-column:1/-1}
[data-theme="dark"] .ab-bl{background:#0E1829;border-color:#1E3A5F;color:#60A5FA}
[data-theme="dark"] .ab-gr{background:#052E1C;border-color:#065F46;color:#34D399}
[data-theme="dark"] .ab-rd{background:#1F0A0A;border-color:#7F1D1D;color:#FCA5A5}
[data-theme="dark"] .ab-yl{background:#1A1005;border-color:#78350F;color:#FCD34D}
[data-theme="dark"] .ab-vi{background:var(--purple-light);border-color:#2A1A5E;color:#A78BFA}
[data-theme="dark"] .ab-tl{background:#042F2E;border-color:#0F766E;color:#2DD4BF}
[data-theme="dark"] .ab-gy{background:var(--surface-2);border-color:var(--border-md);color:var(--ink-muted)}
.empty-st{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:72px 24px;text-align:center}
.es-icon{width:72px;height:72px;border-radius:50%;background:var(--purple-light);display:flex;align-items:center;justify-content:center;margin:0 auto 18px}
.es-icon .material-icons-round{font-size:34px!important;color:var(--purple)}
.empty-st h4{font-family:'Playfair Display',serif;font-size:22px;margin-bottom:8px}
.empty-st p{font-size:13px;color:var(--ink-faint);max-width:340px;margin:0 auto 20px;line-height:1.6}
.pg-bar{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);padding:12px 18px;margin-top:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
.pg-info{font-size:12px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.pg-info strong{color:var(--purple-md)}
.pg-btns{display:flex;gap:4px;align-items:center}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface);color:var(--ink-muted);font-size:12px;font-weight:500;font-family:'Sora',sans-serif;text-decoration:none;cursor:pointer;transition:all .15s}
.pg-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.pg-btn.active{background:var(--purple);border-color:var(--purple);color:white;font-weight:700;box-shadow:0 2px 8px var(--purple-glow)}
.pg-btn .material-icons-round{font-size:16px!important}
.pg-ell{display:flex;align-items:center;padding:0 5px;color:var(--ink-faint);font-size:13px}
.pp-wrap{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--ink-faint)}
.pp-wrap select{border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);padding:5px 22px 5px 8px;font-family:'Sora',sans-serif;font-size:12px;color:var(--ink);cursor:pointer;outline:none;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 6px center;transition:border-color .15s}
.pp-wrap select:focus{border-color:var(--purple)}
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:50;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex;animation:fadeBg .2s ease}
@keyframes fadeBg{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--surface);border-radius:16px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sh-xl);animation:modalIn .25s cubic-bezier(.4,0,.2,1)}
@keyframes modalIn{from{transform:translateY(14px) scale(.98);opacity:0}to{transform:none;opacity:1}}
.m-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;background:var(--surface)}
.m-hi{display:flex;align-items:center;gap:10px}
.m-hi-icon{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center}
.m-hi-icon .material-icons-round{font-size:20px!important}
.m-hi-title{font-family:'Playfair Display',serif;font-size:17px;color:var(--ink)}
.m-hi-sub{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.m-close{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:transparent;cursor:pointer;color:var(--ink-faint);display:flex;align-items:center;justify-content:center;transition:all .15s}
.m-close:hover{background:var(--canvas);color:var(--ink)}
.m-close .material-icons-round{font-size:17px!important}
.m-scroll{overflow-y:auto;flex:1}
.m-scroll::-webkit-scrollbar{width:5px}
.m-scroll::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
.m-body{padding:20px 24px}
.m-foot{padding:14px 22px;border-top:1px solid var(--border);background:var(--canvas);flex-shrink:0;display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.fg{margin-bottom:18px}
.fl{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px}
.fl .material-icons-round{font-size:15px!important;color:var(--purple)}
.fi{width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);outline:none;transition:border-color .15s,box-shadow .15s}
.fi:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}
.fi::placeholder{color:var(--ink-faint)}
.fh{font-size:11px;color:var(--ink-faint);margin-top:5px;display:flex;align-items:center;gap:4px}
.fh .material-icons-round{font-size:12px!important}
.info-box{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;background:var(--purple-pale);border:1px solid #C4B5FD;border-radius:var(--r-sm);margin:16px 0}
.info-box .material-icons-round{font-size:18px!important;color:var(--purple-md);flex-shrink:0;margin-top:1px}
.ib-body{font-size:12px;color:var(--ink-muted);line-height:1.6}
.ib-body strong{color:var(--ink);display:block;margin-bottom:4px}
.par-hl{background:linear-gradient(135deg,#EFF6FF,#EDE9FE);border:1px solid #C4B5FD;border-radius:var(--r);padding:18px;margin-bottom:18px}
[data-theme="dark"] .par-hl{background:linear-gradient(135deg,#0E1829,#150F2E);border-color:#2A1A5E}
.par-hl-title{font-family:'Playfair Display',serif;font-size:17px;color:var(--ink);margin-bottom:6px}
.par-hl-meta{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:10px}
.par-hl-prev{font-size:13px;color:var(--ink-muted);line-height:1.6}
.timeline{position:relative;padding-left:26px}
.timeline::before{content:'';position:absolute;left:7px;top:0;bottom:0;width:2px;background:linear-gradient(to bottom,#F97316,#DC2626)}
.tl-item{position:relative;margin-bottom:16px;animation:pageIn .35s ease}
.tl-dot{position:absolute;left:-22px;top:8px;width:12px;height:12px;border-radius:50%;background:white;border:3px solid #F97316;box-shadow:0 0 0 4px rgba(249,115,22,.1)}
[data-theme="dark"] .tl-dot{background:var(--surface)}
.tl-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);padding:14px 16px;transition:box-shadow .15s}
.tl-card:hover{box-shadow:var(--sh-md)}
.tl-type{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:6px;background:#FFF7ED;border:1px solid #FED7AA;color:#92400E;font-size:10px;font-weight:700;text-transform:uppercase;margin-bottom:8px}
[data-theme="dark"] .tl-type{background:#1A1010;border-color:#431407;color:#FCA5A5}
.tl-title{font-family:'Playfair Display',serif;font-size:14px;color:var(--ink);margin-bottom:6px}
.tl-prev{font-size:12px;color:var(--ink-muted);line-height:1.6;margin-bottom:10px}
.tl-meta{display:flex;gap:14px;flex-wrap:wrap;padding-top:10px;border-top:1px solid var(--border)}
.tl-meta-i{display:flex;align-items:center;gap:4px;font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.tl-meta-i .material-icons-round{font-size:12px!important}
.art-hero{position:relative;height:250px;overflow:hidden;background:linear-gradient(135deg,var(--purple-light),var(--surface-2));flex-shrink:0}
.art-hero img{width:100%;height:100%;object-fit:cover}
.art-hero-veil{position:absolute;inset:0;background:linear-gradient(to bottom,transparent 30%,rgba(0,0,0,.5));pointer-events:none}
.art-title{font-family:'Playfair Display',serif;font-size:22px;line-height:1.35;color:var(--ink);margin-bottom:16px}
.art-meta-row{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:20px}
.art-mc{display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm)}
.art-mc .material-icons-round{font-size:16px!important}
.amc-l{font-size:12px;font-weight:600;color:var(--ink)}
.amc-s{font-size:10px;color:var(--ink-faint)}
.art-content{font-size:14px;color:var(--ink-muted);line-height:1.8;padding:18px 0;border-top:1px solid var(--border);margin-bottom:18px}
.pw-alert{display:flex;gap:8px;align-items:flex-start;padding:10px 12px;border-radius:var(--r-sm);font-size:12px;margin-top:12px}
.pw-alert .material-icons-round{font-size:15px!important;flex-shrink:0}
.pw-err{background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239}
.pw-suc{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46}
.toast-stack{position:fixed;top:70px;right:14px;z-index:9999;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{display:flex;align-items:center;gap:9px;padding:11px 14px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;min-width:240px;max-width:340px;box-shadow:var(--sh-lg);pointer-events:all;animation:toastIn .22s ease;border:1px solid}
@keyframes toastIn{from{transform:translateX(10px);opacity:0}to{transform:none;opacity:1}}
.toast.success{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.toast.error{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.toast.warning{background:#FFFBEB;color:#92400E;border-color:#FDE68A}
.toast.info{background:var(--purple-pale);color:var(--purple-md);border-color:#C4B5FD}
.toast .material-icons-round{font-size:16px!important;flex-shrink:0}
.toast-msg{flex:1}
.toast-x{cursor:pointer;opacity:.6;font-size:15px;line-height:1}
.toast-x:hover{opacity:1}
.stt-btn{position:fixed;bottom:22px;right:20px;width:40px;height:40px;border-radius:50%;background:var(--purple);color:white;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 4px 14px var(--purple-glow);opacity:0;pointer-events:none;z-index:40;transition:opacity .25s,transform .25s}
.stt-btn.show{opacity:1;pointer-events:all}
.stt-btn:hover{transform:translateY(-2px)}
.stt-btn .material-icons-round{font-size:20px!important}
/* ── Bulk actions ── */
.bulk-bar{display:none;align-items:center;gap:10px;padding:10px 14px;background:var(--purple-dark);border-radius:var(--r);margin-bottom:14px;flex-wrap:wrap;animation:pageIn .2s ease}
.bulk-bar.show{display:flex}
.bulk-cnt{font-size:13px;font-weight:600;color:white;font-family:'Fira Code',monospace;margin-right:4px}
.bulk-sep{width:1px;height:20px;background:rgba(255,255,255,.2)}
.bulk-btn{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;cursor:pointer;border:none;transition:all .15s}
.bulk-btn .material-icons-round{font-size:14px!important}
.bulk-btn-del{background:#EF4444;color:white}
.bulk-btn-del:hover{background:#DC2626}
.bulk-btn-arc{background:rgba(255,255,255,.15);color:white}
.bulk-btn-arc:hover{background:rgba(255,255,255,.25)}
.bulk-btn-push{background:#10B981;color:white}
.bulk-btn-push:hover{background:#059669}
.bulk-btn-hl{background:#3B82F6;color:white}
.bulk-btn-hl:hover{background:#2563EB}
.bulk-clear{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:12px;font-family:'Sora',sans-serif;display:flex;align-items:center;gap:4px;transition:color .15s}
.bulk-clear:hover{color:white}
.bulk-clear .material-icons-round{font-size:14px!important}
.a-cb{position:absolute;top:8px;left:8px;z-index:5;width:20px;height:20px;cursor:pointer;accent-color:var(--purple)}
.a-card{position:relative}
/* ── Approval badges ── */
.pend-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:600;background:#FEF3C7;border:1px solid #FDE68A;color:#92400E;font-family:'Fira Code',monospace}
.rej-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:99px;font-size:10px;font-weight:600;background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239;font-family:'Fira Code',monospace}
.rej-badge .material-icons-round,.pend-badge .material-icons-round{font-size:11px!important}
.rej-note{font-size:11px;color:#9F1239;padding:6px 10px;background:#FFF1F2;border-radius:var(--r-xs);margin-top:6px;display:flex;gap:5px;align-items:flex-start}
.rej-note .material-icons-round{font-size:13px!important;flex-shrink:0;margin-top:1px}
/* ── Pending approval banner ── */
.pend-banner{background:linear-gradient(135deg,#FEF3C7,#FDE68A);border:1px solid #FCD34D;border-radius:var(--r);padding:12px 16px;margin-bottom:18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.pend-banner .material-icons-round{color:#92400E;font-size:20px!important}
.pend-banner-text{flex:1;font-size:13px;font-weight:500;color:#78350F}
.pend-banner strong{font-weight:700}
.pend-banner a{color:#92400E;font-weight:600;text-decoration:none;padding:5px 12px;border-radius:var(--r-sm);background:rgba(0,0,0,.08);transition:background .15s}
.pend-banner a:hover{background:rgba(0,0,0,.15)}
/* ── Approve/Reject buttons ── */
.ab-apr{background:#ECFDF5;border:1px solid #6EE7B7;color:#065F46}
.ab-apr:hover{background:#D1FAE5}
.ab-rej{background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239}
.ab-rej:hover{background:#FFE4E6}
/* ── Rejection modal ── */
.rej-modal-note{width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);outline:none;resize:vertical;min-height:80px;transition:border-color .15s}
.rej-modal-note:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}
@media print{.mobile-header,.sidebar,.topbar,.msbar,.stt-btn,.toast-stack,.ld-overlay,.a-acts,.sec-acts,.pg-bar{display:none!important}body{overflow:auto}.app-body{display:block}.main{overflow:visible}.scroll-area{padding:0;overflow:visible}.a-card{break-inside:avoid;margin-bottom:16px}}
</style>
</head>
<body>
<div id="loadingOverlay" class="ld-overlay">
    <div class="ld-ring"></div>
    <div class="ld-label">Loading…</div>
</div>
<!-- Mobile Header -->
<header class="mobile-header">
    <button class="mh-btn" id="sidebarToggle"><span class="material-icons-round">menu</span></button>
    <div class="mb">
        <div class="mb-mark"><span class="material-icons-round">newspaper</span></div>
        <span class="mb-name">News Dashboard</span>
    </div>
    <div class="mh-r">
        <button class="mh-btn" id="advancedFilterBtnMobile">
            <span class="material-icons-round">tune</span>
            <?php if($activeFilters>0): ?><span class="filter-dot"><?= $activeFilters ?></span><?php endif; ?>
        </button>
        <div class="av-sm"><?= strtoupper(substr($_SESSION['username']??'A',0,1)) ?></div>
    </div>
</header>
<div class="app-body">
    <div class="sb-overlay" id="sidebarOverlay"></div>
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sb-hd">
            <div class="sb-mark"><span class="material-icons-round">newspaper</span></div>
            <div><div class="sb-bname">News CMS</div><div class="sb-bsub">Editorial Suite</div></div>
            <button class="sb-close" id="sidebarClose"><span class="material-icons-round">close</span></button>
        </div>
        <nav class="sb-nav">
            <div>
                <div class="sb-sec">Navigation</div>
                <div class="sb-links">
                    <a href="user_dashboard.php" class="sb-link <?= $section==='all'?'active':'' ?>">
                        <span class="material-icons-round">dashboard</span>All Articles
                        <span class="sb-cnt"><?= $totalArticles ?></span>
                    </a>
                    <a href="?section=archive" class="sb-link <?= $section==='archive'?'active':'' ?>">
                        <span class="material-icons-round">inventory_2</span>Archive
                        <span class="sb-cnt"><?= $archiveNewsCount ?></span>
                    </a>
                    <a href="others/view_translated.php" class="sb-link sb-ext">
                        <span class="material-icons-round">translate</span>Dialect Translated
                        <span class="material-icons-round mi-ext">open_in_new</span>
                    </a>
                </div>
            </div>
            <div>
                <div class="sb-sec">Generate</div>
                <div style="position:relative">
                    <button class="sb-link sb-dd-toggle" style="width:100%;text-align:left"><span class="material-icons-round">auto_awesome</span>AI &amp; API News<span class="material-icons-round sb-dd-arrow">expand_more</span></button>
                    <div class="sb-dd-menu" id="aiDropdown">
                        <a href="news.php" target="_blank" class="sb-dd-item"><span class="material-icons-round">smart_toy</span>AI Generated</a>
                        <a href="mediastack.php" class="sb-dd-item"><span class="material-icons-round">link</span>Mediastack</a>
                        <a href="newsapi.php" class="sb-dd-item"><span class="material-icons-round">feed</span>NewsAPI</a>
                    </div>
                </div>
                <a href="others/ai_clips.php" class="sb-link sb-ext"><span class="material-icons-round">smart_toy</span>AI Clips<span class="material-icons-round mi-ext">video_camera_front</span></a>
            </div>
            <div>
                <div class="sb-sec">Community</div>
                <div class="sb-links">
                    <a href="https://project.mbcradio.net/chatv4/chat.php" class="sb-link sb-ext"><span class="material-icons-round">forum</span>Chat Community<span class="material-icons-round mi-ext">open_in_new</span></a>
                </div>
            </div>
        </nav>
        <div class="sb-foot">
            <a href="profile.php" class="sb-link"><span class="material-icons-round">account_circle</span>My Profile</a>
            <button class="sb-link" onclick="openSettingsModal()"><span class="material-icons-round">lock</span>Change Password</button>
            <button class="sb-link" onclick="toggleDark()"><span class="material-icons-round" id="darkIcon">dark_mode</span><span id="darkLabel">Dark Mode</span></button>
            <a href="../logout.php" class="sb-link" style="color:#FCA5A5"><span class="material-icons-round" style="color:#F87171">logout</span>Logout</a>
        </div>
    </aside>
    <!-- Main -->
    <main class="main">
        <header class="topbar">
            <div class="tb-l">
                <div class="tb-icon"><span class="material-icons-round">newspaper</span></div>
                <div>
                    <div class="tb-title">Articles Dashboard</div>
                    <div class="tb-sub"><?= getSectionTitle($section) ?> · <?= $totalItems ?> results</div>
                </div>
            </div>
            <div class="tb-r">
                <button id="advancedFilterBtn" class="btn btn-outline"><span class="material-icons-round">tune</span>Filters<?php if($activeFilters>0): ?><span class="filter-badge"><?= $activeFilters ?></span><?php endif; ?></button>
                <button onclick="toggleDark()" class="btn btn-outline btn-icon" id="darkBtnTop"><span class="material-icons-round">dark_mode</span></button>
                <div class="user-chip">
                    <div class="user-av"><?= strtoupper(substr($_SESSION['username']??'A',0,1)) ?></div>
                    <div><div class="user-name"><?= e($_SESSION['username']??'User') ?></div><div class="user-role"><?= ucfirst($_SESSION['role']??'user') ?></div></div>
                </div>
            </div>
        </header>
        <div class="msbar">
            <div class="ms-wrap"><span class="material-icons-round si">search</span><input type="text" id="searchInputMobile" placeholder="Search articles…" autocomplete="off"/></div>
        </div>
        <div class="scroll-area page-in" id="scrollArea">
            <!-- Stat Grid -->
            <div class="stat-grid">
                <div class="stat-card"><div class="st-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">article</span></div><div><div class="st-num"><?= $totalItems ?></div><div class="st-lbl">Filtered Results</div></div></div>
                <div class="stat-card"><div class="st-icon" style="background:#ECFDF5"><span class="material-icons-round" style="color:#059669">group</span></div><div><div class="st-num"><?= $activeUsers ?></div><div class="st-lbl">Active Users</div></div></div>
                <div class="stat-card"><div class="st-icon" style="background:#FFF7ED"><span class="material-icons-round" style="color:#D97706">category</span></div><div><div class="st-num"><?= $totalCategories ?></div><div class="st-lbl">Categories</div></div></div>
                <div class="stat-card"><div class="st-icon" style="background:#FFF1F2"><span class="material-icons-round" style="color:#E11D48">business</span></div><div><div class="st-num"><?= $pendingReviews ?></div><div class="st-lbl">Departments</div></div></div>
            </div>

            <!-- Active Filters -->
            <?php $afl=getActiveFiltersArray(); if(!empty($afl)): ?>
            <div class="fc">
                <div class="fc-hd">
                    <div class="fc-title"><span class="material-icons-round">filter_alt</span>Active Filters (<?= count($afl) ?>)</div>
                    <button onclick="clearAllFilters()" class="clear-btn"><span class="material-icons-round">clear</span>Clear All</button>
                </div>
                <div class="chips">
                    <?php foreach($afl as $f): ?>
                    <div class="chip"><strong><?= e($f['type']) ?>:</strong> <?= e($f['value']) ?><button class="chip-x" onclick="removeFilter('<?= $f['param'] ?>')"><span class="material-icons-round">close</span></button></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section Header -->
            <div class="sec-hd">
                <div class="sec-tl-wrap">
                    <div class="sec-icon"><span class="material-icons-round">newspaper</span></div>
                    <div>
                        <div class="sec-title"><?= getSectionTitle($section) ?></div>
                        <div class="sec-sub"><?= $totalItems ?> articles · page <?= $currentPage ?>/<?= $totalPages ?>
                            <?php if($section==='all' && $filterStatus===''): ?><span style="opacity:.65"> · archive excluded</span><?php endif; ?></div>
                    </div>
                    <button class="ref-btn" id="refreshArticles"><span class="material-icons-round">refresh</span></button>
                </div>
                <div class="sec-acts">
                    <button onclick="selectAllArticles()" class="btn btn-outline" id="selectAllBtn" title="Select all articles on this page"><span class="material-icons-round">check_box</span><span id="selectAllLabel">Select</span></button>
                    <a href="function/export.php?<?= e(http_build_query(array_filter($_GET))) ?>" class="btn btn-outline"><span class="material-icons-round">download</span>Export CSV</a>
                    <button onclick="openCategoryModal()" class="btn btn-outline"><span class="material-icons-round">label</span>Categories</button>
                    <button onclick="window.location.href='function/create.php'" class="btn btn-purple"><span class="material-icons-round">add_circle</span>New Article</button>
                </div>
            </div>

            <?php if ($isAdmin && $pendingApprovalCount > 0): ?>
            <div class="pend-banner">
                <span class="material-icons-round">pending_actions</span>
                <div class="pend-banner-text"><strong><?= $pendingApprovalCount ?> article<?= $pendingApprovalCount > 1 ? 's' : '' ?></strong> pending your approval.</div>
                <a href="?filter_pending=1">Review Now</a>
            </div>
            <?php endif; ?>

            <!-- Bulk Action Bar -->
            <div class="bulk-bar" id="bulkBar">
                <span class="bulk-cnt" id="bulkCount">0 selected</span>
                <div class="bulk-sep"></div>
                <button class="bulk-btn bulk-btn-push" onclick="doBulkAction('push_edited')"><span class="material-icons-round">rocket_launch</span>Mark Edited</button>
                <?php if ($isAdmin): ?>
                <button class="bulk-btn bulk-btn-hl" onclick="doBulkAction('push_headline')"><span class="material-icons-round">star</span>Headline</button>
                <?php endif; ?>
                <button class="bulk-btn bulk-btn-arc" onclick="doBulkAction('archive')"><span class="material-icons-round">inventory_2</span>Archive</button>
                <button class="bulk-btn bulk-btn-del" onclick="doBulkAction('delete')"><span class="material-icons-round">delete</span>Delete</button>
                <button class="bulk-clear" onclick="clearSelection()"><span class="material-icons-round">close</span>Clear</button>
            </div>

            <?php if(empty($paginatedNews)): ?>
            <div class="empty-st">
                <div class="es-icon"><span class="material-icons-round">article</span></div>
                <h4>No Articles Found</h4>
                <p><?php if($activeFilters>0): ?>No articles match your filters. Try adjusting your search criteria.<?php elseif($section!=='all'): ?>No articles in this section yet.<?php else: ?>Start by creating your first news article.<?php endif; ?></p>
                <?php if($activeFilters>0): ?><button onclick="clearAllFilters()" class="btn" style="background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239;margin-right:8px"><span class="material-icons-round">clear</span>Clear Filters</button><?php endif; ?>
                <button onclick="window.location.href='function/create.php'" class="btn btn-purple"><span class="material-icons-round">add_circle</span>Create Article</button>
            </div>
            <?php else: ?>
            <div class="articles-grid" id="articlesGrid">
            <?php foreach($paginatedNews as $article):
                $st=getStatusInfo($article['is_pushed']);
                $at=getArticleTypeBadge($article);
                $bc='a-body';
                if($article['is_update']==1) $bc.=' ub';
                elseif(isset($at['isLive'])&&$at['isLive']) $bc.=' lb';
                elseif($at&&$at['type']==='parent') $bc.=' pb';
            ?>
            <div class="a-card" data-id="<?= $article['id'] ?>">
                <input type="checkbox" class="a-cb article-cb" value="<?= $article['id'] ?>" onchange="updateBulkBar()" title="Select article">
                <div class="a-thumb">
                    <img src="<?= e(getThumbnailUrl($article['thumbnail'])) ?>" alt="" loading="lazy" onerror="this.parentNode.innerHTML='<div class=\'a-ph\'><span class=\'material-icons-round\'>image</span></div><div class=\'a-veil\'></div>'"/>
                    <div class="a-veil"></div>
                    <div class="a-tl">
                        <?php if($article['is_update']==1): ?>
                        <span class="a-badge" style="background:rgba(249,115,22,.9);color:white"><span class="material-icons-round">fiber_manual_record</span><?= e($at['label']??'Update') ?></span>
                        <?php elseif(isset($at['isLive'])&&$at['isLive']): ?>
                        <span class="live-badge"><span class="ld-dot"></span>LIVE</span>
                        <?php elseif($at&&$at['type']==='parent'): ?>
                        <span class="a-badge" style="background:rgba(59,130,246,.9);color:white"><span class="material-icons-round">account_tree</span><?= $article['update_count'] ?> update<?= $article['update_count']>1?'s':'' ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="a-tr">
                        <span class="sb-badge" style="background:<?= $st['bg'] ?>;color:<?= $st['text'] ?>;border-color:<?= $st['dot'] ?>44">
                            <span class="sb-dot" style="background:<?= $st['dot'] ?>"></span><?= $st['label'] ?>
                        </span>
                    </div>
                </div>
                <div class="<?= $bc ?>">
                    <?php if($article['is_update']==1&&$article['parent_title']): ?>
                    <div class="par-ref"><span class="material-icons-round">link</span><strong style="flex-shrink:0">Parent:</strong><button onclick="openModal('modal-<?= $article['parent_id'] ?>')"><?= e($article['parent_title']) ?></button></div>
                    <?php endif; ?>
                    <div class="a-tags">
                        <span class="a-tag t-cat"><span class="material-icons-round">label</span><?= e($article['category_name']?:'Uncategorized') ?></span>
                        <?php if($at): ?><span class="a-tag <?= $article['is_update']==1?'t-upd':'t-par' ?>"><span class="material-icons-round"><?= $at['icon'] ?></span><?= e($at['label']) ?></span><?php endif; ?>
                        <?php if(!empty($article['pending_approval'])): ?><span class="pend-badge"><span class="material-icons-round">pending</span>Pending Review</span><?php endif; ?>
                        <?php if(empty($article['pending_approval']) && !empty($article['rejection_note'])): ?><span class="rej-badge"><span class="material-icons-round">cancel</span>Rejected</span><?php endif; ?>
                    </div>
                    <div class="a-title" onclick="openModal('modal-<?= $article['id'] ?>')"><?= e($article['title']) ?></div>
                    <?php if(empty($article['pending_approval']) && !empty($article['rejection_note'])): ?>
                    <div class="rej-note"><span class="material-icons-round">info</span><?= e($article['rejection_note']) ?></div>
                    <?php endif; ?>
                    <div class="a-prev"><?= e(strip_tags($article['content'])) ?></div>
                    <div class="a-meta">
                        <div class="a-mi"><div class="a-mi-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">person</span></div><div><div class="a-mi-v"><?= e($article['username']?:'—') ?></div><div class="a-mi-s">Author</div></div></div>
                        <div class="a-mi"><div class="a-mi-icon" style="background:#EFF6FF"><span class="material-icons-round" style="color:#1D4ED8">business</span></div><div><div class="a-mi-v"><?= e($article['dept_name']?:'—') ?></div><div class="a-mi-s">Dept</div></div></div>
                    </div>
                    <div class="a-date"><span class="material-icons-round">schedule</span><?= date('M d, Y · g:i A',strtotime($article['created_at'])) ?></div>
                    <div class="a-acts">
                        <button class="ab ab-bl" onclick="openModal('modal-<?= $article['id'] ?>')"><span class="material-icons-round">visibility</span>View</button>
                        <button class="ab ab-gr" onclick="window.location.href='function/update.php?id=<?= $article['id'] ?>'"><span class="material-icons-round">edit</span>Edit</button>
                        <button class="ab ab-rd" onclick="confirmDelete(<?= $article['id'] ?>)"><span class="material-icons-round">delete</span>Delete</button>
                        <button class="ab ab-yl" onclick="window.location.href='function/translate.php?id=<?= $article['id'] ?>'"><span class="material-icons-round">translate</span>Translate</button>
                        <button class="ab ab-vi" onclick="window.open('others/print_headline.php?id=<?= $article['id'] ?>','_blank')"><span class="material-icons-round">print</span>Print</button>
                        <?php if($article['is_update']==0): ?>
                        <button class="ab ab-tl ab-full" onclick="window.location.href='function/link_to_parent.php?id=<?= $article['id'] ?>'"><span class="material-icons-round">link</span>Link to Parent</button>
                        <button class="ab ab-or ab-full" onclick="window.location.href='function/add_update.php?parent_id=<?= $article['id'] ?>'"><span class="material-icons-round">add_circle</span>Add Update to Article</button>
                        <?php endif; ?>
                        <?php if($article['is_pushed']==0): ?>
                        <button class="ab ab-am ab-full" onclick="window.location.href='function/push.php?id=<?= $article['id'] ?>&to=1'"><span class="material-icons-round">rocket_launch</span>Push to Edited</button>
                        <?php elseif($article['is_pushed']==1): ?>
                        <?php if(!empty($article['pending_approval'])): ?>
                        <button class="ab ab-gy ab-full" disabled title="Waiting for admin approval"><span class="material-icons-round">hourglass_top</span>Awaiting Approval</button>
                        <?php elseif($isAdmin): ?>
                        <button class="ab ab-in" onclick="window.location.href='function/push.php?id=<?= $article['id'] ?>&to=2'"><span class="material-icons-round">star</span>Headlines</button>
                        <?php else: ?>
                        <button class="ab ab-in ab-full" onclick="window.location.href='function/push.php?id=<?= $article['id'] ?>&to=2'"><span class="material-icons-round">send</span>Submit for Review</button>
                        <?php endif; ?>
                        <?php if(!$isAdmin || !empty($article['pending_approval'])): /* admins: revert shown below */ endif; ?>
                        <?php if(!empty($article['pending_approval']) && $isAdmin): ?>
                        <button class="ab ab-apr" onclick="approveArticle(<?= $article['id'] ?>)"><span class="material-icons-round">check_circle</span>Approve</button>
                        <button class="ab ab-rej" onclick="openRejectModal(<?= $article['id'] ?>)"><span class="material-icons-round">cancel</span>Reject</button>
                        <?php elseif(empty($article['pending_approval'])): ?>
                        <button class="ab ab-gy" onclick="confirmRevert(<?= $article['id'] ?>,0)"><span class="material-icons-round">undo</span>Revert</button>
                        <?php endif; ?>
                        <?php elseif($article['is_pushed']==2): ?>
                        <button class="ab ab-pu" onclick="if(confirm('Publish this article?')){const r=encodeURIComponent(window.location.href);window.location.href='public/simple_publish.php?id=<?= $article['id'] ?>&to=1&return_url='+r;}"><span class="material-icons-round">publish</span>Publish</button>
                        <button class="ab ab-gy" onclick="confirmRevert(<?= $article['id'] ?>,1)"><span class="material-icons-round">undo</span>Revert</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
            <!-- Pagination -->
            <?php if($totalPages>1): ?>
            <div class="pg-bar">
                <div class="pg-info">Showing <strong><?= $offset+1 ?></strong>–<strong><?= min($offset+$itemsPerPage,$totalItems) ?></strong> of <strong><?= $totalItems ?></strong></div>
                <div class="pg-btns">
                    <?php if($currentPage>1): ?><a href="<?= getPaginationUrl($currentPage-1,$section,$itemsPerPage) ?>" class="pg-btn"><span class="material-icons-round">chevron_left</span></a><?php else: ?><span class="pg-btn" style="opacity:.4;pointer-events:none"><span class="material-icons-round">chevron_left</span></span><?php endif; ?>
                    <?php if($ps>1): ?><a href="<?= getPaginationUrl(1,$section,$itemsPerPage) ?>" class="pg-btn">1</a><?php if($ps>2): ?><span class="pg-ell">…</span><?php endif; ?><?php endif; ?>
                    <?php for($i=$ps;$i<=$pe;$i++): ?><a href="<?= getPaginationUrl($i,$section,$itemsPerPage) ?>" class="pg-btn <?= $i==$currentPage?'active':'' ?>"><?= $i ?></a><?php endfor; ?>
                    <?php if($pe<$totalPages): ?><?php if($pe<$totalPages-1): ?><span class="pg-ell">…</span><?php endif; ?><a href="<?= getPaginationUrl($totalPages,$section,$itemsPerPage) ?>" class="pg-btn"><?= $totalPages ?></a><?php endif; ?>
                    <?php if($currentPage<$totalPages): ?><a href="<?= getPaginationUrl($currentPage+1,$section,$itemsPerPage) ?>" class="pg-btn"><span class="material-icons-round">chevron_right</span></a><?php else: ?><span class="pg-btn" style="opacity:.4;pointer-events:none"><span class="material-icons-round">chevron_right</span></span><?php endif; ?>
                </div>
                <div class="pp-wrap"><span>Show</span>
                    <select onchange="changeItemsPerPage(this.value)">
                        <?php foreach([9,18,27,60] as $n): ?><option value="<?= $n ?>" <?= $itemsPerPage==$n?'selected':'' ?>><?= $n ?></option><?php endforeach; ?>
                    </select><span>per page</span>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <div style="text-align:center;font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:20px;padding-bottom:8px">
                News CMS &bull; <?= $totalArticles ?> total articles &bull; <?= date('F j, Y') ?>
            </div>
        </div>
    </main>
</div>
<div class="toast-stack" id="toastStack"></div>
<button class="stt-btn" id="sttBtn"><span class="material-icons-round">arrow_upward</span></button>

<!-- MODALS -->
<!-- Advanced Filter -->
<div class="modal-bg" id="advancedFilterModal">
    <div class="modal-box" style="max-width:680px">
        <div class="m-hd">
            <div class="m-hi"><div class="m-hi-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">tune</span></div><div><div class="m-hi-title">Advanced Filters</div><div class="m-hi-sub">Narrow down articles by criteria</div></div></div>
            <button class="m-close" onclick="closeAdvancedFilter()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll"><div class="m-body">
            <form id="advancedFilterForm">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="fg" style="grid-column:1/-1">
                        <label class="fl"><span class="material-icons-round">search</span>Search Text</label>
                        <input class="fi" type="text" id="filterSearch" value="<?= e($filterSearch) ?>" placeholder="Keywords in title or content…"/>
                    </div>
                    <!-- Status filter (replaces sidebar tabs) -->
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">flag</span>Status</label>
                        <select class="fi" id="filterStatus">
                            <option value="">All (excl. Archive)</option>
                            <option value="0" <?= $filterStatus==='0'?'selected':'' ?>>
                                🟡 Regular
                            </option>
                            <option value="1" <?= $filterStatus==='1'?'selected':'' ?>>
                                🟢 Edited
                            </option>
                            <option value="2" <?= $filterStatus==='2'?'selected':'' ?>>
                                🔵 Headlines
                            </option>
                            <option value="3" <?= $filterStatus==='3'?'selected':'' ?>>
                                🔴 Archive
                            </option>
                        </select>
                    </div>
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">label</span>Category</label>
                        <select class="fi" id="filterCategory">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filterCategory==$cat['id']?'selected':'' ?>><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if($_SESSION['role']==='admin'): ?>
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">business</span>Department</label>
                        <select class="fi" id="filterDepartment">
                            <option value="">All Departments</option>
                            <?php foreach($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" <?= $filterDepartment==$dept['id']?'selected':'' ?>><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="fg">
                        <label class="fl"><span class="material-icons-round">person</span>Author</label>
                        <select class="fi" id="filterAuthor">
                            <option value="">All Authors</option>
                            <?php foreach($authors as $author): ?>
                            <option value="<?= $author['id'] ?>" <?= $filterAuthor==$author['id']?'selected':'' ?>><?= e($author['username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg"><label class="fl"><span class="material-icons-round">event</span>Date From</label><input class="fi" type="date" id="filterDateFrom" value="<?= e($filterDateFrom) ?>"/></div>
                    <div class="fg"><label class="fl"><span class="material-icons-round">event</span>Date To</label><input class="fi" type="date" id="filterDateTo" value="<?= e($filterDateTo) ?>"/></div>
                </div>
                <div class="info-box"><span class="material-icons-round">info</span><div class="ib-body"><strong>Filter Tips</strong>Use Status to filter by Regular, Edited, Headlines, or Archive. Leave empty to show all active articles (archive excluded by default). Combine multiple filters to narrow results further.</div></div>
            </form>
        </div></div>
        <div class="m-foot" style="justify-content:space-between">
            <button onclick="resetFilters()" class="btn" style="background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239"><span class="material-icons-round">clear</span>Reset All</button>
            <div style="display:flex;gap:8px">
                <button onclick="closeAdvancedFilter()" class="btn btn-outline">Cancel</button>
                <button onclick="applyFilters()" class="btn btn-purple"><span class="material-icons-round">filter_alt</span>Apply Filters</button>
            </div>
        </div>
    </div>
</div>
<!-- Update Thread -->
<div class="modal-bg" id="updateThreadModal">
    <div class="modal-box" style="max-width:820px">
        <div class="m-hd">
            <div class="m-hi"><div class="m-hi-icon" style="background:#FFF7ED"><span class="material-icons-round" style="color:#F97316">timeline</span></div><div><div class="m-hi-title">Update Thread</div><div class="m-hi-sub">All updates linked to this article</div></div></div>
            <button class="m-close" onclick="closeUpdateThread()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll"><div class="m-body" id="updateThreadContent">
            <div style="text-align:center;padding:40px"><div class="ld-ring" style="margin:0 auto 12px"></div><div style="font-size:13px;color:var(--ink-faint)">Loading…</div></div>
        </div></div>
    </div>
</div>
<!-- Category -->
<div class="modal-bg" id="categoryModal">
    <div class="modal-box" style="max-width:480px">
        <div class="m-hd">
            <div class="m-hi"><div class="m-hi-icon" style="background:#EFF6FF"><span class="material-icons-round" style="color:#1D4ED8">label</span></div><div><div class="m-hi-title">Create Category</div><div class="m-hi-sub">Add a new article category</div></div></div>
            <button class="m-close" onclick="closeCategoryModal()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll"><div class="m-body">
            <form id="categoryForm" onsubmit="handleCategorySubmit(event)">
                <div class="fg"><label class="fl"><span class="material-icons-round">title</span>Category Name</label><input class="fi" type="text" name="name" id="categoryName" placeholder="Enter category name…" maxlength="100" required/>
                <div style="display:flex;justify-content:space-between;margin-top:5px"><span class="fh"><span class="material-icons-round">info</span>Concise, descriptive name (max 100 chars)</span><span style="font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace" id="nameCounter">0/100</span></div></div>
                <div class="info-box"><span class="material-icons-round">label</span><div class="ib-body"><strong>Category Tips</strong>Categories help organize and filter articles. Choose names that are clear and easy to understand.</div></div>
            </form>
        </div></div>
        <div class="m-foot"><button onclick="closeCategoryModal()" class="btn btn-outline">Cancel</button><button onclick="document.getElementById('categoryForm').requestSubmit()" id="submitCatBtn" class="btn btn-purple"><span class="material-icons-round">save</span>Save Category</button></div>
    </div>
</div>
<!-- Change Password -->
<div class="modal-bg" id="settingsModal">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi"><div class="m-hi-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">lock</span></div><div><div class="m-hi-title">Change Password</div><div class="m-hi-sub">Update your account credentials</div></div></div>
            <button class="m-close" onclick="closeSettingsModal()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll"><div class="m-body">
            <form id="changePasswordForm">
                <div class="fg"><label class="fl"><span class="material-icons-round">lock_outline</span>Current Password</label><input class="fi" type="password" id="currentPassword" placeholder="Enter current password" required/></div>
                <div class="fg"><label class="fl"><span class="material-icons-round">vpn_key</span>New Password</label><input class="fi" type="password" id="newPassword" placeholder="At least 8 characters" required/><div class="fh"><span class="material-icons-round">info</span>Minimum 8 characters</div></div>
                <div class="fg"><label class="fl"><span class="material-icons-round">check_circle_outline</span>Confirm New Password</label><input class="fi" type="password" id="confirmPassword" placeholder="Re-enter new password" required/></div>
                <div id="pwError"   class="pw-alert pw-err" style="display:none"><span class="material-icons-round">error</span><span id="pwErrorText"></span></div>
                <div id="pwSuccess" class="pw-alert pw-suc" style="display:none"><span class="material-icons-round">check_circle</span><span id="pwSuccessText"></span></div>
            </form>
        </div></div>
        <div class="m-foot"><button onclick="closeSettingsModal()" class="btn btn-outline">Cancel</button><button onclick="changePassword()" class="btn btn-purple"><span class="material-icons-round">save</span>Change Password</button></div>
    </div>
</div>
<!-- Reject Article Modal -->
<div class="modal-bg" id="rejectModal">
    <div class="modal-box" style="max-width:440px">
        <div class="m-hd">
            <div class="m-hi"><div class="m-hi-icon" style="background:#FFF1F2"><span class="material-icons-round" style="color:#E11D48">cancel</span></div><div><div class="m-hi-title">Reject Article</div><div class="m-hi-sub">Provide a reason for the author</div></div></div>
            <button class="m-close" onclick="closeRejectModal()"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll"><div class="m-body">
            <input type="hidden" id="rejectArticleId" value="">
            <div class="fg"><label class="fl"><span class="material-icons-round">comment</span>Rejection Reason</label><textarea class="rej-modal-note" id="rejectNote" placeholder="Explain why this article needs revision…"></textarea></div>
            <div id="rejectError" style="display:none" class="pw-alert pw-err"><span class="material-icons-round">error</span><span id="rejectErrorText"></span></div>
        </div></div>
        <div class="m-foot"><button onclick="closeRejectModal()" class="btn btn-outline">Cancel</button><button onclick="submitReject()" class="btn" style="background:#E11D48;color:white"><span class="material-icons-round">cancel</span>Reject Article</button></div>
    </div>
</div>

<!-- Article view modals -->
<?php foreach($paginatedNews as $article): $st=getStatusInfo($article['is_pushed']); ?>
<div class="modal-bg" id="modal-<?= $article['id'] ?>">
    <div class="modal-box" style="max-width:780px">
        <div class="m-scroll">
            <?php if(!empty($article['thumbnail'])): ?>
            <div class="art-hero">
                <img src="<?= e(getThumbnailUrl($article['thumbnail'])) ?>" alt="<?= e($article['title']) ?>" onerror="this.parentElement.style.display='none'"/>
                <div class="art-hero-veil"></div>
                <button onclick="closeModal('modal-<?= $article['id'] ?>')" style="position:absolute;top:12px;right:12px;z-index:5" class="m-close"><span class="material-icons-round">close</span></button>
            </div>
            <?php endif; ?>
            <div class="m-hd">
                <div class="m-hi"><div class="m-hi-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">article</span></div><div><div class="m-hi-title" style="max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($article['title']) ?></div><div class="m-hi-sub">#<?= $article['id'] ?> · <?= e($article['dept_name']) ?></div></div></div>
                <?php if(empty($article['thumbnail'])): ?><button class="m-close" onclick="closeModal('modal-<?= $article['id'] ?>')"><span class="material-icons-round">close</span></button><?php endif; ?>
            </div>
            <div class="m-body">
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
                    <span class="a-tag t-cat"><span class="material-icons-round">label</span><?= e($article['category_name']?:'Uncategorized') ?></span>
                    <span class="sb-badge" style="background:<?= $st['bg'] ?>;color:<?= $st['text'] ?>;border-color:<?= $st['dot'] ?>44"><span class="sb-dot" style="background:<?= $st['dot'] ?>"></span><?= $st['label'] ?></span>
                </div>
                <h2 class="art-title"><?= e($article['title']) ?></h2>
                <div class="art-meta-row">
                    <div class="art-mc"><span class="material-icons-round" style="color:var(--purple)">person</span><div><div class="amc-l"><?= e($article['username']?:'—') ?></div><div class="amc-s">Author</div></div></div>
                    <div class="art-mc"><span class="material-icons-round" style="color:#1D4ED8">business</span><div><div class="amc-l"><?= e($article['dept_name']?:'—') ?></div><div class="amc-s">Department</div></div></div>
                    <div class="art-mc"><span class="material-icons-round" style="color:#059669">schedule</span><div><div class="amc-l"><?= date('M d, Y · g:i A',strtotime($article['created_at'])) ?></div><div class="amc-s">Published</div></div></div>
                </div>
                <div class="art-content"><?= nl2br(e($article['content'])) ?></div>
            </div>
        </div>
        <div class="m-foot">
            <?php if($article['is_update']==0&&$article['update_count']>0): ?>
            <button onclick="closeModal('modal-<?= $article['id'] ?>');viewUpdateThread(<?= $article['id'] ?>)" class="btn" style="background:#FFF7ED;border:1px solid #FED7AA;color:#92400E"><span class="material-icons-round">timeline</span>Updates (<?= $article['update_count'] ?>)</button>
            <?php endif; ?>
            <button onclick="window.open('attachment/view_attachment.php?id=<?= $article['id'] ?>','_blank')" class="btn btn-outline"><span class="material-icons-round">visibility</span>Attachments</button>
            <button onclick="window.open('attachment/get_attachment.php?id=<?= $article['id'] ?>','_blank')" class="btn btn-outline"><span class="material-icons-round">download</span>Download</button>
            <button onclick="window.open('others/print_headline.php?id=<?= $article['id'] ?>','_blank')" class="btn btn-purple"><span class="material-icons-round">print</span>Print</button>
            <button onclick="closeModal('modal-<?= $article['id'] ?>')" class="btn btn-outline">Close</button>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
function toggleDark(){
    const h=document.documentElement,dark=h.dataset.theme==='dark';
    h.dataset.theme=dark?'light':'dark';
    localStorage.setItem('theme',dark?'light':'dark');
    const di=document.getElementById('darkIcon'); if(di) di.textContent=dark?'dark_mode':'light_mode';
    const dl=document.getElementById('darkLabel'); if(dl) dl.textContent=dark?'Dark Mode':'Light Mode';
    const bt=document.getElementById('darkBtnTop'); if(bt) bt.querySelector('.material-icons-round').textContent=dark?'dark_mode':'light_mode';
}
(function(){
    const t=localStorage.getItem('theme')||'light';
    document.documentElement.dataset.theme=t;
    if(t==='dark'){
        const di=document.getElementById('darkIcon'); if(di) di.textContent='light_mode';
        const dl=document.getElementById('darkLabel'); if(dl) dl.textContent='Light Mode';
        const bt=document.getElementById('darkBtnTop'); if(bt) bt.querySelector('.material-icons-round').textContent='light_mode';
    }
})();

const TICONS={success:'check_circle',error:'error',warning:'warning',info:'info'};
function showNotification(msg,type='info',dur=3500){
    const stack=document.getElementById('toastStack');
    const t=document.createElement('div');
    t.className=`toast ${type}`;
    t.innerHTML=`<span class="material-icons-round">${TICONS[type]}</span><span class="toast-msg">${msg}</span><span class="toast-x" onclick="this.parentElement.remove()">&#x2715;</span>`;
    stack.appendChild(t);
    setTimeout(()=>{t.style.transition='opacity .3s';t.style.opacity='0';setTimeout(()=>t.remove(),300);},dur);
}
function showLoading(){document.getElementById('loadingOverlay').classList.add('show')}
function hideLoading(){document.getElementById('loadingOverlay').classList.remove('show')}
window.addEventListener('load',hideLoading);

const sb=document.getElementById('sidebar'),sbo=document.getElementById('sidebarOverlay');
document.getElementById('sidebarToggle')?.addEventListener('click',()=>{sb.classList.toggle('open');sbo.classList.toggle('show');});
document.getElementById('sidebarClose')?.addEventListener('click',()=>{sb.classList.remove('open');sbo.classList.remove('show');});
sbo?.addEventListener('click',()=>{sb.classList.remove('open');sbo.classList.remove('show');});

document.querySelectorAll('.sb-dd-toggle').forEach(t=>{
    t.addEventListener('click',function(){
        const menu=this.parentElement.querySelector('.sb-dd-menu');
        const arrow=this.querySelector('.sb-dd-arrow');
        menu.classList.toggle('open');
        if(arrow) arrow.style.transform=menu.classList.contains('open')?'rotate(180deg)':'';
    });
});

function openModal(id){const m=document.getElementById(id);if(m){m.classList.add('open');document.body.style.overflow='hidden';}}
function closeModal(id){const m=document.getElementById(id);if(m){m.classList.remove('open');document.body.style.overflow='';}}
function openAdvancedFilter(){document.getElementById('advancedFilterModal').classList.add('open');document.body.style.overflow='hidden';}
function closeAdvancedFilter(){document.getElementById('advancedFilterModal').classList.remove('open');document.body.style.overflow='';}
function openCategoryModal(){document.getElementById('categoryModal').classList.add('open');document.body.style.overflow='hidden';setTimeout(()=>document.getElementById('categoryName')?.focus(),100);}
function closeCategoryModal(){document.getElementById('categoryModal').classList.remove('open');document.body.style.overflow='';document.getElementById('categoryForm')?.reset();const nc=document.getElementById('nameCounter');if(nc)nc.textContent='0/100';}
function openSettingsModal(){
    document.getElementById('settingsModal').classList.add('open');document.body.style.overflow='hidden';
    document.getElementById('changePasswordForm')?.reset();
    ['pwError','pwSuccess'].forEach(id=>{const el=document.getElementById(id);if(el)el.style.display='none';});
}
function closeSettingsModal(){document.getElementById('settingsModal').classList.remove('open');document.body.style.overflow='';}

document.querySelectorAll('.modal-bg').forEach(m=>{m.addEventListener('click',function(e){if(e.target===this){this.classList.remove('open');document.body.style.overflow='';}});});
document.addEventListener('keydown',e=>{if(e.key==='Escape')document.querySelectorAll('.modal-bg.open').forEach(m=>{m.classList.remove('open');document.body.style.overflow='';});});

document.getElementById('advancedFilterBtn')?.addEventListener('click',openAdvancedFilter);
document.getElementById('advancedFilterBtnMobile')?.addEventListener('click',openAdvancedFilter);

function applyFilters(){
    showLoading();
    const sp=new URLSearchParams(window.location.search),np=new URLSearchParams();
    if(sp.has('section'))np.set('section',sp.get('section'));
    if(sp.has('view'))np.set('view',sp.get('view'));
    const statusVal=document.getElementById('filterStatus')?.value??'';
    const vals={
        filter_search:document.getElementById('filterSearch')?.value||'',
        filter_category:document.getElementById('filterCategory')?.value||'',
        filter_department:document.getElementById('filterDepartment')?.value||'',
        filter_author:document.getElementById('filterAuthor')?.value||'',
        filter_date_from:document.getElementById('filterDateFrom')?.value||'',
        filter_date_to:document.getElementById('filterDateTo')?.value||''
    };
    Object.entries(vals).forEach(([k,v])=>{if(v!=='')np.set(k,v);});
    if(statusVal!=='')np.set('filter_status',statusVal);
    np.set('page','1');window.location.search=np.toString();
}
function resetFilters(){
    ['filterSearch','filterDateFrom','filterDateTo'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    ['filterCategory','filterDepartment','filterAuthor','filterStatus'].forEach(id=>{const el=document.getElementById(id);if(el)el.value='';});
    showNotification('Filters cleared','info',1500);
}
function clearAllFilters(){showLoading();const sp=new URLSearchParams(window.location.search),np=new URLSearchParams();if(sp.has('section'))np.set('section',sp.get('section'));if(sp.has('view'))np.set('view',sp.get('view'));window.location.search=np.toString();}
function removeFilter(param){showLoading();const sp=new URLSearchParams(window.location.search);sp.delete(param);sp.set('page','1');window.location.search=sp.toString();}

function changeItemsPerPage(val){showLoading();const sp=new URLSearchParams(window.location.search);sp.set('per_page',val);sp.set('page','1');window.location.search=sp.toString();}
document.querySelectorAll('.pg-btn[href]').forEach(a=>a.addEventListener('click',showLoading));

document.getElementById('refreshArticles')?.addEventListener('click',function(){
    this.classList.add('spinning');
    showNotification('Refreshing…','info',1000);
    setTimeout(()=>window.location.reload(),800);
});

function confirmDelete(id){if(confirm('Delete this article? This cannot be undone.')){showLoading();window.location.href=`function/delete.php?id=${id}`;}}
function confirmRevert(id,to){const msgs={0:'Revert to Regular News?',1:'Revert to Edited News?'};if(confirm(msgs[to])){showLoading();window.location.href=`function/revert.php?id=${id}&to=${to}`;}}

function viewUpdateThread(parentId){
    showLoading();
    document.getElementById('updateThreadContent').innerHTML='<div style="text-align:center;padding:40px"><div class="ld-ring" style="margin:0 auto 12px"></div><div style="font-size:13px;color:var(--ink-faint)">Loading updates…</div></div>';
    document.getElementById('updateThreadModal').classList.add('open');document.body.style.overflow='hidden';
    fetch(`ajax/get_updates.php?parent_id=${parentId}`)
        .then(r=>r.json())
        .then(data=>{hideLoading();if(data.success)renderUpdateThread(data.parent,data.updates);else{showNotification('Failed to load updates','error');closeUpdateThread();}})
        .catch(()=>{hideLoading();showNotification('Error loading updates','error');closeUpdateThread();});
}
function closeUpdateThread(){document.getElementById('updateThreadModal').classList.remove('open');document.body.style.overflow='';}

function renderUpdateThread(parent,updates){
    const esc=s=>{const d=document.createElement('div');d.textContent=s??'';return d.innerHTML;};
    const fdt=s=>new Date(s).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
    let html=`
    <div class="par-hl">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:10px">
            <span class="a-tag t-par"><span class="material-icons-round">article</span>Parent Article</span>
            <button onclick="closeUpdateThread();openModal('modal-${parent.id}')" class="btn btn-outline" style="font-size:11px;padding:5px 10px"><span class="material-icons-round" style="font-size:13px!important">open_in_full</span>View Full</button>
        </div>
        <div class="par-hl-title">${esc(parent.title)}</div>
        <div class="par-hl-meta">By ${esc(parent.username)} &bull; ${fdt(parent.created_at)}</div>
        <div class="par-hl-prev">${esc((parent.content||'').substring(0,280))}${(parent.content||'').length>280?'&hellip;':''}</div>
    </div>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
        <div style="font-size:14px;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:7px"><span class="material-icons-round" style="font-size:18px!important;color:#F97316">timeline</span>Updates (${updates.length})</div>
        <button onclick="window.location.href='function/add_update.php?parent_id=${parent.id}'" class="btn" style="background:#FFF7ED;border:1px solid #FED7AA;color:#92400E;font-size:11px;padding:6px 12px"><span class="material-icons-round" style="font-size:13px!important">add_circle</span>Add Update</button>
    </div>`;
    if(updates.length===0){
        html+=`<div style="text-align:center;padding:40px;background:var(--canvas);border-radius:var(--r)"><span class="material-icons-round" style="font-size:40px;color:var(--border-md);display:block;margin-bottom:10px">update</span><div style="font-size:13px;color:var(--ink-faint)">No updates yet for this article.</div></div>`;
    }else{
        html+='<div class="timeline">';
        updates.forEach(u=>{
            html+=`<div class="tl-item"><div class="tl-dot"></div><div class="tl-card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:8px">
                <span class="tl-type"><span class="material-icons-round" style="font-size:11px!important">fiber_manual_record</span>${esc(u.update_type||'Update')} #${u.update_number}</span>
                <button onclick="closeUpdateThread();openModal('modal-${u.id}')" style="font-size:11px;color:var(--purple);background:none;border:none;cursor:pointer;font-family:'Sora',sans-serif;font-weight:600">View &rarr;</button>
            </div>
            <div class="tl-title">${esc(u.title)}</div>
            <div class="tl-prev">${esc((u.content||'').substring(0,200))}${(u.content||'').length>200?'&hellip;':''}</div>
            <div class="tl-meta">
                <span class="tl-meta-i"><span class="material-icons-round">person</span>${esc(u.username)}</span>
                <span class="tl-meta-i"><span class="material-icons-round">business</span>${esc(u.dept_name)}</span>
                <span class="tl-meta-i"><span class="material-icons-round">schedule</span>${fdt(u.created_at)}</span>
            </div></div></div>`;
        });
        html+='</div>';
    }
    document.getElementById('updateThreadContent').innerHTML=html;
}

document.getElementById('categoryName')?.addEventListener('input',function(){const c=document.getElementById('nameCounter');if(c)c.textContent=`${this.value.length}/100`;});

async function handleCategorySubmit(e){
    e.preventDefault();
    const btn=document.getElementById('submitCatBtn'),name=document.getElementById('categoryName').value.trim();
    if(!name)return;
    btn.disabled=true;btn.innerHTML='<span class="material-icons-round" style="animation:spin .7s linear infinite">refresh</span> Saving…';
    const fd=new FormData();fd.append('name',name);
    try{
        const r=await fetch('others/category.php',{method:'POST',body:fd});
        if(r.ok){closeCategoryModal();showNotification('Category created!','success');setTimeout(()=>window.location.reload(),800);}
        else throw 0;
    }catch{showNotification('Error saving category','error');btn.disabled=false;btn.innerHTML='<span class="material-icons-round">save</span> Save Category';}
}

function changePassword(){
    const cur=document.getElementById('currentPassword').value,nw=document.getElementById('newPassword').value,cnf=document.getElementById('confirmPassword').value;
    const err=document.getElementById('pwError'),suc=document.getElementById('pwSuccess');
    err.style.display='none';suc.style.display='none';
    if(!cur||!nw||!cnf){document.getElementById('pwErrorText').textContent='Please fill in all fields.';err.style.display='flex';return;}
    if(nw.length<8){document.getElementById('pwErrorText').textContent='New password must be at least 8 characters.';err.style.display='flex';return;}
    if(nw!==cnf){document.getElementById('pwErrorText').textContent='Passwords do not match.';err.style.display='flex';return;}
    showLoading();
    fetch('change_password.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({current_password:cur,new_password:nw})})
        .then(r=>r.json())
        .then(data=>{hideLoading();if(data.success){document.getElementById('pwSuccessText').textContent='Password changed successfully!';suc.style.display='flex';showNotification('Password updated','success');setTimeout(closeSettingsModal,2000);}else{document.getElementById('pwErrorText').textContent=data.message||'Failed to change password.';err.style.display='flex';}})
        .catch(()=>{hideLoading();document.getElementById('pwErrorText').textContent='An error occurred.';err.style.display='flex';});
}

let st;
document.getElementById('searchInputMobile')?.addEventListener('input',function(){
    clearTimeout(st);const term=this.value.toLowerCase().trim();
    st=setTimeout(()=>{
        let vis=0;
        document.querySelectorAll('.a-card').forEach(card=>{
            const ok=!term||card.querySelector('.a-title')?.textContent.toLowerCase().includes(term)||card.querySelector('.a-prev')?.textContent.toLowerCase().includes(term);
            card.style.display=ok?'':'none';if(ok)vis++;
        });
        const g=document.getElementById('articlesGrid');let nr=document.getElementById('noSR');
        if(vis===0&&term){if(!nr){nr=document.createElement('div');nr.id='noSR';nr.style.cssText='grid-column:1/-1;text-align:center;padding:40px';nr.innerHTML='<span class="material-icons-round" style="font-size:40px;color:var(--border-md);display:block;margin-bottom:10px">search_off</span><div style="font-size:14px;font-weight:600;color:var(--ink);margin-bottom:6px">No Results Found</div><div style="font-size:12px;color:var(--ink-faint)">Try different keywords</div>';g?.appendChild(nr);}}
        else nr?.remove();
    },280);
});

const sa=document.getElementById('scrollArea'),sttBtn=document.getElementById('sttBtn');
sa?.addEventListener('scroll',()=>sttBtn?.classList.toggle('show',sa.scrollTop>300));
sttBtn?.addEventListener('click',()=>sa?.scrollTo({top:0,behavior:'smooth'}));

// ── Bulk actions ──────────────────────────────────────────────────────────────
const csrfToken=document.querySelector('meta[name="csrf-token"]')?.content??'';
let _allSelected=false;
function getCheckedIds(){return [...document.querySelectorAll('.article-cb:checked')].map(c=>c.value);}
function updateBulkBar(){
    const ids=getCheckedIds(),bar=document.getElementById('bulkBar'),cnt=document.getElementById('bulkCount');
    if(ids.length>0){bar.classList.add('show');cnt.textContent=ids.length+' selected';}
    else{bar.classList.remove('show');}
    _allSelected=document.querySelectorAll('.article-cb').length===ids.length&&ids.length>0;
    const lbl=document.getElementById('selectAllLabel');
    if(lbl) lbl.textContent=_allSelected?'Deselect':'Select';
}
function selectAllArticles(){
    const cbs=document.querySelectorAll('.article-cb');
    const newVal=!_allSelected;
    cbs.forEach(c=>c.checked=newVal);
    updateBulkBar();
}
function clearSelection(){
    document.querySelectorAll('.article-cb').forEach(c=>c.checked=false);
    updateBulkBar();
}
async function doBulkAction(action){
    const ids=getCheckedIds();
    if(!ids.length){showNotification('No articles selected.','warning');return;}
    const confirmMsgs={delete:`Delete ${ids.length} article(s)? This cannot be undone.`,archive:`Archive ${ids.length} article(s)?`};
    if(confirmMsgs[action]&&!confirm(confirmMsgs[action]))return;
    showLoading();
    const fd=new FormData();
    fd.append('action',action);
    fd.append('csrf_token',csrfToken);
    ids.forEach(id=>fd.append('ids[]',id));
    try{
        const r=await fetch('function/bulk_action.php',{method:'POST',body:fd});
        const data=await r.json();
        hideLoading();
        if(data.success){showNotification(data.message,'success');setTimeout(()=>window.location.reload(),800);}
        else showNotification(data.message||'Action failed.','error');
    }catch{hideLoading();showNotification('Network error.','error');}
}

// ── Approval workflow ─────────────────────────────────────────────────────────
async function approveArticle(id){
    if(!confirm('Approve this article and push it to Headlines?'))return;
    showLoading();
    const fd=new FormData();
    fd.append('id',id);fd.append('action','approve');fd.append('csrf_token',csrfToken);
    try{
        const r=await fetch('function/approve.php',{method:'POST',body:fd});
        const data=await r.json();
        hideLoading();
        if(data.success){showNotification('Article approved!','success');setTimeout(()=>window.location.reload(),800);}
        else showNotification(data.message||'Failed.','error');
    }catch{hideLoading();showNotification('Network error.','error');}
}
function openRejectModal(id){
    document.getElementById('rejectArticleId').value=id;
    document.getElementById('rejectNote').value='';
    document.getElementById('rejectError').style.display='none';
    document.getElementById('rejectModal').classList.add('open');
    document.body.style.overflow='hidden';
    setTimeout(()=>document.getElementById('rejectNote')?.focus(),100);
}
function closeRejectModal(){document.getElementById('rejectModal').classList.remove('open');document.body.style.overflow='';}
async function submitReject(){
    const id=document.getElementById('rejectArticleId').value;
    const note=document.getElementById('rejectNote').value.trim();
    if(!note){document.getElementById('rejectErrorText').textContent='Please enter a rejection reason.';document.getElementById('rejectError').style.display='flex';return;}
    showLoading();
    const fd=new FormData();
    fd.append('id',id);fd.append('action','reject');fd.append('note',note);fd.append('csrf_token',csrfToken);
    try{
        const r=await fetch('function/approve.php',{method:'POST',body:fd});
        const data=await r.json();
        hideLoading();
        if(data.success){closeRejectModal();showNotification('Article rejected.','info');setTimeout(()=>window.location.reload(),800);}
        else showNotification(data.message||'Failed.','error');
    }catch{hideLoading();showNotification('Network error.','error');}
}

// Show approval_submitted toast if coming from push.php redirect
if(new URLSearchParams(window.location.search).get('approval_submitted')==='1'){
    showNotification('Article submitted for admin review.','info',4000);
}
</script>
</body>
</html>