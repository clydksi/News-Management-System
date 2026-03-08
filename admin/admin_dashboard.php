<?php
session_start();
require '../db.php';
require '../csrf.php';

// ── Auth check ────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    header('Location: ../login.php');
    exit;
}

$isSuperAdmin = $_SESSION['role'] === 'superadmin';

// ── CSRF token ────────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Handle Access Tab POST submissions BEFORE any HTML output ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_action'])) {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        header('Location: ?tab=access&error=invalid'); exit;
    }
    if (!$isSuperAdmin) { header('Location: ?tab=access&error=unauthorized'); exit; }

    if ($_POST['access_action'] === 'grant') {
        $userId       = filter_input(INPUT_POST, 'user_id',       FILTER_VALIDATE_INT);
        $departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
        $viewScope    = in_array($_POST['view_scope'] ?? '', ['own', 'granted', 'all']) ? $_POST['view_scope'] : 'granted';
        if (!$userId || !$departmentId) { header('Location: ?tab=access&error=invalid'); exit; }
        $s = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
        $s->execute([$userId]);
        if ((int)$s->fetchColumn() === $departmentId) { header('Location: ?tab=access&error=self'); exit; }
        try {
            $pdo->prepare("INSERT INTO department_access (user_id, department_id, granted_by) VALUES (?, ?, ?)")->execute([$userId, $departmentId, $_SESSION['user_id']]);
            $pdo->prepare("UPDATE users SET view_scope = ? WHERE id = ? AND view_scope = 'own'")->execute([$viewScope, $userId]);
            header('Location: ?tab=access&success=granted');
        } catch (PDOException $e) {
            $errorCode = $e->getCode() === '23000' ? 'duplicate' : 'invalid';
            error_log('grant error: ' . $e->getMessage());
            header("Location: ?tab=access&error={$errorCode}");
        }
        exit;
    }
    if ($_POST['access_action'] === 'revoke') {
        $grantId = filter_input(INPUT_POST, 'grant_id', FILTER_VALIDATE_INT);
        if (!$grantId) { header('Location: ?tab=access&error=invalid'); exit; }
        try {
            $s = $pdo->prepare("SELECT user_id FROM department_access WHERE id = ?");
            $s->execute([$grantId]); $userId = (int)$s->fetchColumn();
            $pdo->prepare("DELETE FROM department_access WHERE id = ?")->execute([$grantId]);
            if ($userId) {
                $pdo->prepare("UPDATE users SET view_scope = 'own' WHERE id = ? AND view_scope = 'granted' AND NOT EXISTS (SELECT 1 FROM department_access WHERE user_id = ?) AND NOT EXISTS (SELECT 1 FROM department_visibility WHERE source_dept_id = (SELECT department_id FROM users WHERE id = ?))")->execute([$userId, $userId, $userId]);
            }
            header('Location: ?tab=access&success=revoked');
        } catch (PDOException $e) {
            error_log('revoke error: ' . $e->getMessage());
            header('Location: ?tab=access&error=invalid');
        }
        exit;
    }
    if ($_POST['access_action'] === 'grant_dept') {
        $sourceDeptId = filter_input(INPUT_POST, 'source_dept_id', FILTER_VALIDATE_INT);
        $targetDeptId = filter_input(INPUT_POST, 'target_dept_id', FILTER_VALIDATE_INT);
        if (!$sourceDeptId || !$targetDeptId) { header('Location: ?tab=access&error=invalid'); exit; }
        if ($sourceDeptId === $targetDeptId) { header('Location: ?tab=access&error=self_dept'); exit; }
        try {
            $pdo->prepare("INSERT INTO department_visibility (source_dept_id, target_dept_id, granted_by) VALUES (?, ?, ?)")->execute([$sourceDeptId, $targetDeptId, $_SESSION['user_id']]);
            header('Location: ?tab=access&success=dept_granted');
        } catch (PDOException $e) {
            $errorCode = $e->getCode() === '23000' ? 'duplicate_dept' : 'invalid';
            error_log('grant_dept error: ' . $e->getMessage());
            header("Location: ?tab=access&error={$errorCode}");
        }
        exit;
    }
    if ($_POST['access_action'] === 'revoke_dept') {
        $visibilityId = filter_input(INPUT_POST, 'visibility_id', FILTER_VALIDATE_INT);
        if (!$visibilityId) { header('Location: ?tab=access&error=invalid'); exit; }
        try {
            $s = $pdo->prepare("SELECT source_dept_id FROM department_visibility WHERE id = ?");
            $s->execute([$visibilityId]); $sourceDeptId = (int)$s->fetchColumn();
            $pdo->prepare("DELETE FROM department_visibility WHERE id = ?")->execute([$visibilityId]);
            if ($sourceDeptId) {
                $pdo->prepare("UPDATE users SET view_scope = 'own' WHERE department_id = ? AND view_scope = 'granted' AND NOT EXISTS (SELECT 1 FROM department_access WHERE user_id = users.id) AND NOT EXISTS (SELECT 1 FROM department_visibility WHERE source_dept_id = ?)")->execute([$sourceDeptId, $sourceDeptId]);
            }
            header('Location: ?tab=access&success=dept_revoked');
        } catch (PDOException $e) {
            error_log('revoke_dept error: ' . $e->getMessage());
            header('Location: ?tab=access&error=invalid');
        }
        exit;
    }
    header('Location: ?tab=access&error=invalid'); exit;
}

// ── Visibility scope ──────────────────────────────────────────────────────────
$visibleDeptIds = getVisibleDepartmentIds($pdo, $_SESSION);
[$dw_n, $dp_n] = buildDeptWhere($visibleDeptIds, 'n.department_id');
[$dw_u, $dp_u] = buildDeptWhere($visibleDeptIds, 'u.department_id');
[$dw_d, $dp_d] = buildDeptWhere($visibleDeptIds, 'd.id');

// ── Pagination ────────────────────────────────────────────────────────────────
$perPage = 10;
$page    = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$offset  = ($page - 1) * $perPage;

// ── Active tab ────────────────────────────────────────────────────────────────
$activeTab = $_GET['tab'] ?? 'users';

// ── Default variable initialization ───────────────────────────────────────────
$users = []; $departments = []; $categories = []; $allDepartments = [];
$totalPages = 1; $allUsers = []; $allDeptsForAccess = [];
$accessGrants = []; $deptVisibility = [];

if ($activeTab === 'access' && !$isSuperAdmin) { header('Location: ?tab=users'); exit; }

function e($string) { return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8'); }

try {
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE {$dw_n}"); $s->execute($dp_n); $totalArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE u.is_active = 1 AND {$dw_u}"); $s->execute($dp_u); $activeUsers = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM departments d WHERE {$dw_d}"); $s->execute($dp_d); $totalDepartments = $s->fetchColumn();
    $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();

    if ($activeTab === 'users') {
        $s = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE {$dw_u}"); $s->execute($dp_u); $totalUsers = $s->fetchColumn();
        $totalPages = max(1, ceil($totalUsers / $perPage));
        $stmt = $pdo->prepare("SELECT u.id, u.username, u.role, u.created_at, u.is_active, u.view_scope, d.name AS department, u.department_id FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE {$dw_u} ORDER BY u.created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([...$dp_u, $perPage, $offset]); $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($activeTab === 'departments') {
        $s = $pdo->prepare("SELECT COUNT(*) FROM departments d WHERE {$dw_d}"); $s->execute($dp_d); $totalDepts = $s->fetchColumn();
        $totalPages = max(1, ceil($totalDepts / $perPage));
        $stmt = $pdo->prepare("SELECT d.*, COUNT(DISTINCT u.id) AS user_count, COUNT(DISTINCT n.id) AS article_count FROM departments d LEFT JOIN users u ON d.id = u.department_id LEFT JOIN news n ON d.id = n.department_id WHERE {$dw_d} GROUP BY d.id ORDER BY d.name ASC LIMIT ? OFFSET ?");
        $stmt->execute([...$dp_d, $perPage, $offset]); $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if ($activeTab === 'categories') {
        $totalCats  = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        $totalPages = max(1, ceil($totalCats / $perPage));
        $stmt = $pdo->prepare("SELECT c.*, COUNT(n.id) AS article_count FROM categories c LEFT JOIN news n ON c.id = n.category_id GROUP BY c.id ORDER BY c.name ASC LIMIT ? OFFSET ?");
        $stmt->execute([$perPage, $offset]); $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $s = $pdo->prepare("SELECT id, name FROM departments d WHERE {$dw_d} ORDER BY name"); $s->execute($dp_d);
    $allDepartments = $s->fetchAll(PDO::FETCH_ASSOC);

    if ($activeTab === 'access') {
        $allUsers = $pdo->query("SELECT u.id, u.username, u.view_scope, d.name AS dept_name FROM users u LEFT JOIN departments d ON u.department_id = d.id ORDER BY u.username ASC")->fetchAll(PDO::FETCH_ASSOC);
        $allDeptsForAccess = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $accessGrants = $pdo->query("SELECT da.id, u.id AS user_id, u.username, u.view_scope, own_d.name AS own_dept_name, d.name AS dept_name, g.username AS granted_by_name, da.granted_at FROM department_access da JOIN users u ON da.user_id = u.id JOIN departments d ON da.department_id = d.id JOIN users g ON da.granted_by = g.id LEFT JOIN departments own_d ON u.department_id = own_d.id ORDER BY da.granted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        $deptVisibility = $pdo->query("SELECT dv.id, sd.name AS source_dept_name, td.name AS target_dept_name, g.username AS granted_by_name, dv.granted_at, COUNT(u.id) AS affected_users FROM department_visibility dv JOIN departments sd ON dv.source_dept_id = sd.id JOIN departments td ON dv.target_dept_id = td.id JOIN users g ON dv.granted_by = g.id LEFT JOIN users u ON u.department_id = dv.source_dept_id GROUP BY dv.id, sd.name, td.name, g.username, dv.granted_at ORDER BY dv.granted_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

if ($activeTab === 'analytics') {
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE is_pushed = 3 AND {$dw_n}"); $s->execute($dp_n); $publishedArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE is_pushed = 0 AND {$dw_n}"); $s->execute($dp_n); $draftArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE is_pushed = 1 AND {$dw_n}"); $s->execute($dp_n); $editedArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE is_pushed = 2 AND {$dw_n}"); $s->execute($dp_n); $headlineArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE DATE(created_at) = CURDATE() AND {$dw_n}"); $s->execute($dp_n); $todayArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) AND {$dw_n}"); $s->execute($dp_n); $weekArticles = $s->fetchColumn();
    $s = $pdo->prepare("SELECT COUNT(*) FROM news n WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE()) AND {$dw_n}"); $s->execute($dp_n); $monthArticles = $s->fetchColumn();
    $stmt = $pdo->prepare("SELECT d.id, d.name, COUNT(DISTINCT n.id) AS total_articles, COUNT(DISTINCT CASE WHEN n.is_pushed = 3 THEN n.id END) AS published_articles, COUNT(DISTINCT CASE WHEN n.is_pushed = 2 THEN n.id END) AS headline_articles, COUNT(DISTINCT CASE WHEN n.is_pushed = 1 THEN n.id END) AS edited_articles, COUNT(DISTINCT CASE WHEN n.is_pushed = 0 THEN n.id END) AS draft_articles, COUNT(DISTINCT u.id) AS total_users, COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) AS active_users, COUNT(DISTINCT CASE WHEN DATE(n.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN n.id END) AS articles_this_week, COUNT(DISTINCT CASE WHEN DATE(n.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN n.id END) AS articles_this_month, MAX(n.created_at) AS last_article_date FROM departments d LEFT JOIN news n ON d.id = n.department_id LEFT JOIN users u ON d.id = u.department_id WHERE {$dw_d} GROUP BY d.id, d.name ORDER BY total_articles DESC");
    $stmt->execute($dp_d); $deptAnalytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT c.id, c.name, COUNT(n.id) AS article_count, COUNT(CASE WHEN n.is_pushed = 3 THEN 1 END) AS published_count, COUNT(CASE WHEN n.is_pushed = 0 THEN 1 END) AS draft_count, COUNT(CASE WHEN DATE(n.created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) AS recent_count FROM categories c LEFT JOIN news n ON c.id = n.category_id AND {$dw_n} GROUP BY c.id, c.name ORDER BY article_count DESC LIMIT 10");
    $stmt->execute($dp_n); $categoryAnalytics = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, DATE_FORMAT(created_at, '%b %Y') AS month_label, COUNT(*) AS count, COUNT(CASE WHEN is_pushed = 3 THEN 1 END) AS published_count, COUNT(CASE WHEN is_pushed = 0 THEN 1 END) AS draft_count FROM news n WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) AND {$dw_n} GROUP BY DATE_FORMAT(created_at, '%Y-%m'), DATE_FORMAT(created_at, '%b %Y') ORDER BY month ASC");
    $stmt->execute($dp_n); $monthlyTrends = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT CASE WHEN is_pushed = 0 THEN 'Draft' WHEN is_pushed = 1 THEN 'Edited' WHEN is_pushed = 2 THEN 'Headline' WHEN is_pushed = 3 THEN 'Published' ELSE 'Unknown' END AS status_label, is_pushed, COUNT(*) AS count FROM news n WHERE {$dw_n} GROUP BY is_pushed ORDER BY is_pushed ASC");
    $stmt->execute($dp_n); $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT DATE(created_at) AS date, COUNT(*) AS count FROM news n WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND {$dw_n} GROUP BY DATE(created_at) ORDER BY date ASC");
    $stmt->execute($dp_n); $dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT u.id, u.username, d.name AS department, COUNT(n.id) AS article_count, COUNT(CASE WHEN n.is_pushed = 3 THEN 1 END) AS published_count, COUNT(CASE WHEN n.is_pushed = 0 THEN 1 END) AS draft_count, COUNT(CASE WHEN DATE(n.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS recent_count, MAX(n.created_at) AS last_article FROM users u LEFT JOIN news n ON u.id = n.created_by AND {$dw_n} LEFT JOIN departments d ON u.department_id = d.id WHERE {$dw_u} GROUP BY u.id, u.username, d.name HAVING article_count > 0 ORDER BY article_count DESC LIMIT 10");
    $stmt->execute([...$dp_n, ...$dp_u]); $topAuthors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT n.id, n.title, n.is_pushed, CASE WHEN n.is_pushed = 0 THEN 'Draft' WHEN n.is_pushed = 1 THEN 'Edited' WHEN n.is_pushed = 2 THEN 'Headline' WHEN n.is_pushed = 3 THEN 'Published' ELSE 'Unknown' END AS status_label, n.created_at, u.username AS author, d.name AS department, c.name AS category FROM news n LEFT JOIN users u ON n.created_by = u.id LEFT JOIN departments d ON n.department_id = d.id LEFT JOIN categories c ON n.category_id = c.id WHERE {$dw_n} ORDER BY n.created_at DESC LIMIT 15");
    $stmt->execute($dp_n); $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT COUNT(*) AS total_users, COUNT(CASE WHEN is_active = 1 THEN 1 END) AS active_users, COUNT(CASE WHEN role = 'admin' THEN 1 END) AS admin_count, COUNT(CASE WHEN role = 'superadmin' THEN 1 END) AS superadmin_count, COUNT(CASE WHEN role = 'user' THEN 1 END) AS user_count, COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) AS new_users_month FROM users u WHERE {$dw_u}");
    $stmt->execute($dp_u); $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $deptComparison = ['labels' => [], 'articles' => [], 'users' => [], 'published' => []];
    foreach ($deptAnalytics as $dept) {
        $deptComparison['labels'][]    = $dept['name'];
        $deptComparison['articles'][]  = $dept['total_articles'];
        $deptComparison['users'][]     = $dept['total_users'];
        $deptComparison['published'][] = $dept['published_articles'];
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>"/>
<title>News Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
/* ════════════════════════════════════════════
   DESIGN TOKENS — matches purple editorial suite
════════════════════════════════════════════ */
:root {
    --purple:       #7C3AED;
    --purple-md:    #6D28D9;
    --purple-dark:  #4C1D95;
    --purple-light: #EDE9FE;
    --purple-pale:  #F5F3FF;
    --purple-glow:  rgba(124,58,237,.18);

    --ink:          #13111A;
    --ink-muted:    #4A4560;
    --ink-faint:    #8E89A8;

    --canvas:       #F3F1FA;
    --surface:      #FFFFFF;
    --surface-2:    #EEEAF8;

    --border:       #E2DDEF;
    --border-md:    #C9C2E0;

    /* sidebar deep violet */
    --sb:           #130F23;
    --sb2:          #1A1535;
    --sb3:          #211A42;
    --sb-txt:       #D4CFE8;
    --sb-muted:     #6B6485;
    --sb-act:       rgba(124,58,237,.22);
    --sb-hover:     rgba(255,255,255,.07);
    --sb-bd:        rgba(255,255,255,.07);

    --r:    14px;
    --r-sm:  9px;
    --r-xs:  5px;
    --sh:    0 1px 3px rgba(60,20,120,.07), 0 1px 2px rgba(60,20,120,.04);
    --sh-md: 0 4px 18px rgba(60,20,120,.11), 0 2px 6px rgba(60,20,120,.06);
    --sh-lg: 0 12px 40px rgba(60,20,120,.16);
    --sh-xl: 0 24px 64px rgba(60,20,120,.22);

    --success: #059669;
    --warn:    #D97706;
    --danger:  #DC2626;
    --info:    #2563EB;
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
    --sb:           #0A0815;
    --sb2:          #110D22;
    --sh:    0 1px 4px rgba(0,0,0,.4);
    --sh-md: 0 4px 18px rgba(0,0,0,.5);
    --sh-lg: 0 12px 40px rgba(0,0,0,.6);
    --sh-xl: 0 24px 64px rgba(0,0,0,.7);
}

/* ── BASE ── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;scroll-behavior:smooth}
body{font-family:'Sora',sans-serif;background:var(--canvas);color:var(--ink);height:100vh;overflow:hidden;display:flex;flex-direction:column;transition:background .2s,color .2s}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--purple)}

/* ── APP SHELL ── */
.app-shell{display:flex;flex:1;overflow:hidden;position:relative}

/* ════════════════════════════════════════════
   SIDEBAR
════════════════════════════════════════════ */
.sidebar{
    width:260px;flex-shrink:0;
    background:var(--sb);
    display:flex;flex-direction:column;
    border-right:1px solid var(--sb-bd);
    height:100%;
    transition:transform .28s cubic-bezier(.4,0,.2,1);
    position:relative;z-index:30;
    overflow:hidden;
}
@media(max-width:1023px){
    .sidebar{position:fixed;top:0;left:0;height:100%;transform:translateX(-100%)}
    .sidebar.open{transform:none}
}
.sb-top{
    padding:20px 18px 16px;
    border-bottom:1px solid var(--sb-bd);
    flex-shrink:0;
    display:flex;align-items:center;gap:12px;
}
.sb-logo{
    width:38px;height:38px;border-radius:10px;
    background:var(--purple);display:flex;align-items:center;justify-content:center;
    flex-shrink:0;box-shadow:0 4px 12px var(--purple-glow);
}
.sb-logo .material-icons-round{font-size:20px!important;color:white}
.sb-brand{font-family:'Playfair Display',serif;font-size:16px;color:#EAE6F8;font-weight:700;white-space:nowrap}
.sb-close{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;background:rgba(255,255,255,.08);color:var(--sb-muted);display:flex;align-items:center;justify-content:center;margin-left:auto;transition:background .15s;flex-shrink:0}
.sb-close:hover{background:rgba(255,255,255,.14)}
.sb-close .material-icons-round{font-size:16px!important}
.lg-hide{display:none}
@media(max-width:1023px){.lg-hide{display:flex}}

/* Scope badge */
.scope-badge{
    margin:10px 12px;padding:10px 14px;
    background:rgba(255,255,255,.05);border:1px solid var(--sb-bd);
    border-radius:var(--r-sm);
    display:flex;align-items:center;gap:9px;flex-shrink:0;
}
.scope-icon{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.scope-label{font-size:10px;color:var(--sb-muted);text-transform:uppercase;letter-spacing:.08em;font-weight:700;font-family:'Fira Code',monospace;margin-bottom:2px}
.scope-val{font-size:12px;color:var(--sb-txt);font-weight:600}

/* Nav */
.sb-nav{flex:1;overflow-y:auto;padding:6px 10px;display:flex;flex-direction:column;gap:14px}
.nav-group-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:var(--sb-muted);padding:0 8px;margin-bottom:4px;font-family:'Fira Code',monospace}
.nav-group ul{list-style:none;display:flex;flex-direction:column;gap:2px}
.nav-link{
    display:flex;align-items:center;gap:10px;
    padding:9px 10px;border-radius:9px;
    color:var(--sb-muted);font-size:13px;font-weight:500;
    text-decoration:none;cursor:pointer;border:none;background:none;
    width:100%;transition:all .15s;
    font-family:'Sora',sans-serif;
}
.nav-link:hover{background:var(--sb-hover);color:var(--sb-txt)}
.nav-link.active{background:var(--sb-act);color:#C4B5FD;font-weight:600}
.nav-link.active .nl-icon{color:var(--purple)}
.nl-icon{width:16px;text-align:center;flex-shrink:0}
.nl-icon .material-icons-round{font-size:17px!important}
.nl-count{
    margin-left:auto;
    padding:2px 8px;border-radius:99px;
    font-size:10px;font-weight:700;font-family:'Fira Code',monospace;
    background:rgba(255,255,255,.08);color:var(--sb-muted);
}
.nav-link.active .nl-count{background:rgba(124,58,237,.3);color:#C4B5FD}
.nl-ext{margin-left:auto;opacity:.4}
.nl-ext .material-icons-round{font-size:13px!important}

/* Sidebar footer */
.sb-foot{padding:10px 10px 16px;border-top:1px solid var(--sb-bd);flex-shrink:0}
.sb-foot a{color:#F87171}
.sb-foot .nav-link:hover{background:rgba(220,38,38,.15);color:#F87171}

/* User chip in sidebar */
.sb-user{
    padding:10px 12px;margin:0 10px 2px;
    background:rgba(255,255,255,.04);border:1px solid var(--sb-bd);
    border-radius:var(--r-sm);
    display:flex;align-items:center;gap:9px;flex-shrink:0;
}
.sb-avatar{
    width:30px;height:30px;border-radius:8px;
    background:var(--purple);color:white;
    display:flex;align-items:center;justify-content:center;
    font-size:13px;font-weight:700;flex-shrink:0;
}
.sb-user-name{font-size:12px;font-weight:600;color:var(--sb-txt);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-user-role{font-size:10px;color:var(--sb-muted);font-family:'Fira Code',monospace}

/* ════════════════════════════════════════════
   MAIN
════════════════════════════════════════════ */
.main{flex:1;display:flex;flex-direction:column;overflow:hidden;min-width:0}

/* Topbar */
.topbar{
    padding:0 28px;height:60px;flex-shrink:0;
    background:var(--surface);border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:14px;
    box-shadow:var(--sh);
}
.mob-menu-btn{width:36px;height:36px;border-radius:var(--r-sm);border:1px solid var(--border);background:transparent;cursor:pointer;color:var(--ink-muted);display:none;align-items:center;justify-content:center;transition:all .15s}
.mob-menu-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.mob-menu-btn .material-icons-round{font-size:18px!important}
@media(max-width:1023px){.mob-menu-btn{display:flex}}
.topbar-brand{display:flex;align-items:center;gap:10px}
.tb-icon{width:38px;height:38px;border-radius:10px;background:var(--purple-light);display:flex;align-items:center;justify-content:center}
.tb-icon .material-icons-round{font-size:20px!important;color:var(--purple)}
.tb-title{font-family:'Playfair Display',serif;font-size:18px;color:var(--ink)}
.tb-sub{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:1px}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:8px}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:var(--r-sm);border:1px solid var(--border);background:transparent;color:var(--ink-muted);cursor:pointer;font-family:'Sora',sans-serif;font-size:12px;font-weight:500;text-decoration:none;transition:all .15s;white-space:nowrap}
.tb-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.tb-btn .material-icons-round{font-size:15px!important}
.tb-btn-icon{width:34px;height:34px;padding:0;justify-content:center}
.tb-user{display:flex;align-items:center;gap:9px;padding:7px 12px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--canvas);cursor:default}
.tb-avatar{width:28px;height:28px;border-radius:7px;background:var(--purple);color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0}
.tb-uname{font-size:12px;font-weight:600;color:var(--ink)}
.tb-urole{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace}
@media(max-width:600px){.tb-user .tb-uname,.tb-user .tb-urole,.tb-sub{display:none}}

/* ════════════════════════════════════════════
   SCROLLABLE CONTENT
════════════════════════════════════════════ */
.main-scroll{flex:1;overflow-y:auto;padding:24px 28px 60px}
@media(max-width:600px){.main-scroll{padding:16px 14px 60px}}

/* Notifications (from URL params) */
.notif{
    display:flex;align-items:center;gap:10px;
    padding:12px 16px;border-radius:var(--r-sm);margin-bottom:18px;
    font-size:13px;font-weight:500;border:1px solid;
    animation:notifIn .25s ease;
}
@keyframes notifIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:none}}
.notif-success{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.notif-error{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.notif .material-icons-round{font-size:17px!important;flex-shrink:0}

/* ════════════════════════════════════════════
   STAT CARDS
════════════════════════════════════════════ */
.stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
@media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:480px){.stat-grid{grid-template-columns:1fr 1fr}}
.stat-card{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--r);box-shadow:var(--sh);
    padding:18px 20px;
    display:flex;align-items:center;gap:16px;
    transition:all .2s cubic-bezier(.4,0,.2,1);
    position:relative;overflow:hidden;
}
.stat-card::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    border-radius:var(--r) var(--r) 0 0;
    opacity:0;transition:opacity .2s;
}
.stat-card:hover{transform:translateY(-3px);box-shadow:var(--sh-lg);border-color:var(--border-md)}
.stat-card:hover::before{opacity:1}
.stat-card.c-purple::before{background:var(--purple)}
.stat-card.c-green::before{background:#10B981}
.stat-card.c-orange::before{background:#F97316}
.stat-card.c-blue::before{background:#3B82F6}
.stat-card.c-red::before{background:#EF4444}
.stat-icon{
    width:48px;height:48px;border-radius:12px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
}
.stat-icon .material-icons-round{font-size:24px!important}
.stat-icon.bg-purple{background:var(--purple-light)}.stat-icon.bg-purple .material-icons-round{color:var(--purple)}
.stat-icon.bg-green{background:#ECFDF5}.stat-icon.bg-green .material-icons-round{color:#059669}
.stat-icon.bg-orange{background:#FFF7ED}.stat-icon.bg-orange .material-icons-round{color:#EA580C}
.stat-icon.bg-blue{background:#EFF6FF}.stat-icon.bg-blue .material-icons-round{color:#2563EB}
.stat-icon.bg-red{background:#FFF1F2}.stat-icon.bg-red .material-icons-round{color:#DC2626}
.stat-val{font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--ink);line-height:1}
.stat-lbl{font-size:12px;color:var(--ink-faint);margin-top:3px;font-weight:500}
@media(max-width:480px){.stat-val{font-size:22px}.stat-icon{width:38px;height:38px}.stat-icon .material-icons-round{font-size:19px!important}}

/* ════════════════════════════════════════════
   TAB BAR
════════════════════════════════════════════ */
.tab-bar{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--r);box-shadow:var(--sh);
    margin-bottom:22px;overflow:hidden;
}
.tab-bar-inner{display:flex;border-bottom:1px solid var(--border);overflow-x:auto;scrollbar-width:none;padding:0 4px}
.tab-bar-inner::-webkit-scrollbar{display:none}
.tab-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:14px 18px;
    border:none;background:none;cursor:pointer;
    font-family:'Sora',sans-serif;font-size:13px;font-weight:500;
    color:var(--ink-faint);white-space:nowrap;
    position:relative;transition:color .15s;
    flex-shrink:0;
}
.tab-btn .material-icons-round{font-size:16px!important}
.tab-btn:hover{color:var(--ink)}
.tab-btn.active{color:var(--purple);font-weight:600}
.tab-btn.active::after{
    content:'';position:absolute;bottom:-1px;left:0;right:0;height:2px;
    background:linear-gradient(90deg,var(--purple),#A78BFA);
    border-radius:2px 2px 0 0;
}
/* Tab content wrapper */
.tab-content{padding:0}

/* ════════════════════════════════════════════
   MOBILE OVERLAY
════════════════════════════════════════════ */
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:25;backdrop-filter:blur(4px)}
.mob-overlay.show{display:block}

/* ════════════════════════════════════════════
   NOTIFICATIONS TOAST
════════════════════════════════════════════ */
.toast-stack{position:fixed;top:70px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:6px}
.toast{
    display:flex;align-items:center;gap:9px;
    padding:10px 14px;border-radius:var(--r-sm);
    font-family:'Sora',sans-serif;font-size:12px;font-weight:500;
    box-shadow:var(--sh-lg);animation:toastIn .22s ease;border:1px solid;
    min-width:220px;max-width:300px;pointer-events:all;
}
@keyframes toastIn{from{transform:translateX(10px);opacity:0}to{transform:none;opacity:1}}
.toast.success{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.toast.error{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.toast.warning{background:#FFFBEB;color:#92400E;border-color:#FDE68A}
.toast.info{background:var(--purple-pale);color:var(--purple-md);border-color:#C4B5FD}
.toast .material-icons-round{font-size:16px!important;flex-shrink:0}

/* ════════════════════════════════════════════
   MODAL BASE (for tab sub-modals)
════════════════════════════════════════════ */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(6px);z-index:50;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex;animation:fadeBg .2s ease}
@keyframes fadeBg{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--surface);border-radius:16px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sh-xl);animation:modalIn .25s cubic-bezier(.4,0,.2,1)}
@keyframes modalIn{from{transform:translateY(10px) scale(.98);opacity:0}to{transform:none;opacity:1}}
.m-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.m-hi{display:flex;align-items:center;gap:10px}
.m-hi-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.m-hi-title{font-family:'Playfair Display',serif;font-size:17px;color:var(--ink)}
.m-hi-sub{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.m-close{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:transparent;cursor:pointer;color:var(--ink-faint);display:flex;align-items:center;justify-content:center;transition:all .15s}
.m-close:hover{background:var(--canvas);color:var(--ink);border-color:var(--purple)}
.m-close .material-icons-round{font-size:16px!important}
.m-scroll{overflow-y:auto;flex:1}
.m-body{padding:20px 24px}
.m-foot{padding:14px 22px;border-top:1px solid var(--border);background:var(--canvas);flex-shrink:0;display:flex;gap:8px;justify-content:flex-end}

/* ════════════════════════════════════════════
   SHARED FORM ELEMENTS (for tab includes)
════════════════════════════════════════════ */
.form-group{margin-bottom:16px}
.form-label{display:flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:6px}
.form-label .material-icons-round{font-size:14px!important;color:var(--purple)}
.form-input,.form-select{
    width:100%;padding:9px 13px;border:1px solid var(--border);border-radius:var(--r-sm);
    background:var(--canvas);color:var(--ink);font-family:'Sora',sans-serif;font-size:13px;
    outline:none;transition:border-color .15s,box-shadow .15s;
}
.form-select{
    appearance:none;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;
    padding-right:32px;
}
.form-input:focus,.form-select:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}

/* ── SHARED BUTTON COMPONENTS ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:var(--r-sm);border:none;cursor:pointer;font-family:'Sora',sans-serif;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;white-space:nowrap}
.btn .material-icons-round{font-size:15px!important}
.btn-sm{padding:6px 11px;font-size:11px}
.btn-sm .material-icons-round{font-size:13px!important}
.btn-purple{background:var(--purple);color:white}.btn-purple:hover{background:var(--purple-md);box-shadow:0 4px 14px var(--purple-glow)}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--ink-muted)}.btn-outline:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.btn-red{background:#FFF1F2;border:1px solid #FECDD3;color:#9F1239}.btn-red:hover{background:#FFE4E6;border-color:#FDA4AF}
.btn-green{background:#ECFDF5;border:1px solid #A7F3D0;color:#065F46}.btn-green:hover{background:#D1FAE5}
.btn-icon{width:32px;height:32px;padding:0;justify-content:center}

/* ── SHARED TABLE STYLES ── */
.data-table-wrap{border-radius:var(--r);border:1px solid var(--border);overflow:hidden;background:var(--surface)}
.data-table{width:100%;border-collapse:collapse;font-size:13px}
.data-table th{background:var(--canvas);padding:11px 16px;text-align:left;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;border-bottom:1px solid var(--border);white-space:nowrap}
.data-table td{padding:13px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:var(--canvas)}
.data-table tr td:first-child{font-weight:600}

/* ── SHARED BADGE STYLES ── */
.badge{display:inline-flex;align-items:center;gap:3px;padding:3px 9px;border-radius:99px;font-size:10px;font-weight:600;letter-spacing:.03em;border:1px solid transparent}
.badge .material-icons-round{font-size:11px!important}
.badge-purple{background:var(--purple-light);color:var(--purple-md);border-color:#C4B5FD}
.badge-green{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.badge-blue{background:#EFF6FF;color:#1D4ED8;border-color:#BFDBFE}
.badge-orange{background:#FFF7ED;color:#92400E;border-color:#FED7AA}
.badge-red{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.badge-gray{background:var(--canvas);color:var(--ink-faint);border-color:var(--border)}

/* ── SECTION HEADER ── */
.section-hd{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:16px 20px;border-bottom:1px solid var(--border);background:var(--surface)}
.section-hd-l{display:flex;align-items:center;gap:10px}
.section-hd-icon{width:36px;height:36px;border-radius:9px;background:var(--purple-light);display:flex;align-items:center;justify-content:center}
.section-hd-icon .material-icons-round{font-size:18px!important;color:var(--purple)}
.section-hd-title{font-family:'Playfair Display',serif;font-size:16px;color:var(--ink)}
.section-hd-sub{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:1px}
.section-hd-r{display:flex;align-items:center;gap:7px}

/* ── PAGINATION ── */
.pg-bar{padding:14px 18px;border-top:1px solid var(--border);background:var(--canvas);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.pg-info{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.pg-info strong{color:var(--purple-md)}
.pg-btns{display:flex;gap:4px}
.pg-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--surface);color:var(--ink-muted);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;text-decoration:none;cursor:pointer;transition:all .15s}
.pg-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.pg-btn.active{background:var(--purple);border-color:var(--purple);color:white;font-weight:700}
.pg-btn .material-icons-round{font-size:15px!important}

/* ── EMPTY STATE ── */
.empty-state{padding:60px 24px;text-align:center}
.empty-icon{width:64px;height:64px;border-radius:50%;background:var(--purple-light);display:flex;align-items:center;justify-content:center;margin:0 auto 16px}
.empty-icon .material-icons-round{font-size:28px!important;color:var(--purple)}
.empty-title{font-family:'Playfair Display',serif;font-size:18px;margin-bottom:6px}
.empty-sub{font-size:12px;color:var(--ink-faint);max-width:280px;margin:0 auto 18px;line-height:1.6}
</style>
</head>
<body>

<!-- Mobile overlay -->
<div class="mob-overlay" id="mobOverlay"></div>

<!-- Sidebar overlay toggle button (mobile) -->
<div class="app-shell">

<!-- ════════════════════════════════════════════
     SIDEBAR
════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

    <!-- Top: brand + close -->
    <div class="sb-top">
        <div class="sb-logo"><span class="material-icons-round">admin_panel_settings</span></div>
        <span class="sb-brand">Admin CMS</span>
        <button class="sb-close lg-hide" id="sbClose" title="Close menu">
            <span class="material-icons-round">close</span>
        </button>
    </div>

    <!-- Scope badge -->
    <div class="scope-badge" style="margin-top:12px">
        <?php if ($isSuperAdmin || ($_SESSION['view_scope'] ?? '') === 'all'): ?>
        <div class="scope-icon" style="background:rgba(16,185,129,.15)">
            <span class="material-icons-round" style="font-size:15px!important;color:#34D399">public</span>
        </div>
        <div>
            <div class="scope-label">Viewing scope</div>
            <div class="scope-val">All departments</div>
        </div>
        <?php elseif (($_SESSION['view_scope'] ?? '') === 'granted'): ?>
        <div class="scope-icon" style="background:rgba(251,191,36,.12)">
            <span class="material-icons-round" style="font-size:15px!important;color:#FBBF24">lock_open</span>
        </div>
        <div>
            <div class="scope-label">Viewing scope</div>
            <div class="scope-val"><?= e($_SESSION['dept_name'] ?? '') ?> + granted</div>
        </div>
        <?php else: ?>
        <div class="scope-icon" style="background:rgba(96,165,250,.12)">
            <span class="material-icons-round" style="font-size:15px!important;color:#60A5FA">business</span>
        </div>
        <div>
            <div class="scope-label">Viewing scope</div>
            <div class="scope-val"><?= e($_SESSION['dept_name'] ?? 'Own dept') ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- User chip -->
    <div class="sb-user" style="margin-top:8px">
        <div class="sb-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
        <div style="min-width:0">
            <div class="sb-user-name"><?= e($_SESSION['username'] ?? 'User') ?></div>
            <div class="sb-user-role"><?= ucfirst($_SESSION['role'] ?? 'user') ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sb-nav">
        <div class="nav-group">
            <div class="nav-group-label">Management</div>
            <ul>
                <li>
                    <a href="?tab=users" class="nav-link <?= $activeTab==='users'?'active':'' ?>">
                        <span class="nl-icon"><span class="material-icons-round">people</span></span>
                        Users
                        <span class="nl-count"><?= $activeUsers ?></span>
                    </a>
                </li>
                <li>
                    <a href="?tab=departments" class="nav-link <?= $activeTab==='departments'?'active':'' ?>">
                        <span class="nl-icon"><span class="material-icons-round">business</span></span>
                        Departments
                        <span class="nl-count"><?= $totalDepartments ?></span>
                    </a>
                </li>
                <li>
                    <a href="?tab=categories" class="nav-link <?= $activeTab==='categories'?'active':'' ?>">
                        <span class="nl-icon"><span class="material-icons-round">label</span></span>
                        Categories
                        <span class="nl-count"><?= $totalCategories ?></span>
                    </a>
                </li>
                <li>
                    <a href="?tab=analytics" class="nav-link <?= $activeTab==='analytics'?'active':'' ?>">
                        <span class="nl-icon"><span class="material-icons-round">analytics</span></span>
                        Analytics
                    </a>
                </li>
                <li>
                    <a href="../user/user_dashboard.php" class="nav-link">
                        <span class="nl-icon"><span class="material-icons-round">article</span></span>
                        Articles
                        <span class="nl-count"><?= $totalArticles ?></span>
                        <span class="nl-ext"><span class="material-icons-round">open_in_new</span></span>
                    </a>
                </li>
            </ul>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="nav-group">
            <div class="nav-group-label">Superadmin</div>
            <ul>
                <li>
                    <a href="?tab=access" class="nav-link <?= $activeTab==='access'?'active':'' ?>">
                        <span class="nl-icon"><span class="material-icons-round">lock_open</span></span>
                        Access Control
                    </a>
                </li>
            </ul>
        </div>
        <?php endif; ?>

        <div class="nav-group">
            <div class="nav-group-label">Community</div>
            <ul>
                <li>
                    <a href="https://project.mbcradio.net/saas/chat.php" target="_blank" class="nav-link">
                        <span class="nl-icon"><span class="material-icons-round">forum</span></span>
                        Chat Community
                        <span class="nl-ext"><span class="material-icons-round">open_in_new</span></span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Footer: logout -->
    <div class="sb-foot">
        <ul>
            <li>
                <a href="../logout.php" class="nav-link" style="color:#F87171">
                    <span class="nl-icon"><span class="material-icons-round">logout</span></span>
                    Logout
                </a>
            </li>
        </ul>
    </div>
</aside>

<!-- ════════════════════════════════════════════
     MAIN PANEL
════════════════════════════════════════════ -->
<div class="main">

    <!-- Top bar -->
    <div class="topbar">
        <button class="mob-menu-btn" id="mobMenuBtn" title="Open menu">
            <span class="material-icons-round">menu</span>
        </button>

        <div class="topbar-brand">
            <div class="tb-icon">
                <span class="material-icons-round">admin_panel_settings</span>
            </div>
            <div>
                <div class="tb-title">Admin Dashboard</div>
                <div class="tb-sub"><?= date('l, F j, Y') ?></div>
            </div>
        </div>

        <div class="topbar-right">
            <a href="../user/user_dashboard.php" class="tb-btn" title="Go to news dashboard">
                <span class="material-icons-round">open_in_new</span>
                <span class="hide-xs">News CMS</span>
            </a>
            <button onclick="toggleDark()" id="darkBtn" class="tb-btn tb-btn-icon" title="Toggle dark mode">
                <span class="material-icons-round">dark_mode</span>
            </button>
            <div class="tb-user">
                <div class="tb-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                <div>
                    <div class="tb-uname"><?= e($_SESSION['username'] ?? '') ?></div>
                    <div class="tb-urole"><?= ucfirst($_SESSION['role'] ?? '') ?><?php if (!$isSuperAdmin && !empty($_SESSION['dept_name'])): ?> · <?= e($_SESSION['dept_name']) ?><?php endif; ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scrollable area -->
    <div class="main-scroll">

        <!-- URL-param notifications -->
        <?php
        $notifMessages = [
            'granted'      => ['Superadmin access granted successfully.', 'success'],
            'revoked'      => ['Access revoked.', 'success'],
            'dept_granted' => ['Department visibility granted.', 'success'],
            'dept_revoked' => ['Department visibility revoked.', 'success'],
            'duplicate'    => ['This access grant already exists.', 'error'],
            'duplicate_dept' => ['This department visibility already exists.', 'error'],
            'self'         => ['Cannot grant access to own department.', 'error'],
            'self_dept'    => ['Source and target department cannot be the same.', 'error'],
            'unauthorized' => ['Unauthorized action.', 'error'],
            'invalid'      => ['Invalid request. Please try again.', 'error'],
        ];
        $notifSuccess = $_GET['success'] ?? '';
        $notifError   = $_GET['error'] ?? '';
        $notifKey     = $notifSuccess ?: $notifError;
        if ($notifKey && isset($notifMessages[$notifKey])):
            [$msg, $type] = $notifMessages[$notifKey];
            $ico = $type === 'success' ? 'check_circle' : 'error';
        ?>
        <div class="notif notif-<?= $type ?>" id="topNotif">
            <span class="material-icons-round"><?= $ico ?></span>
            <?= e($msg) ?>
            <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;cursor:pointer;color:inherit;opacity:.6;display:flex;align-items:center">
                <span class="material-icons-round" style="font-size:15px!important">close</span>
            </button>
        </div>
        <?php endif; ?>

        <!-- Stat Cards -->
        <div class="stat-grid">
            <div class="stat-card c-purple">
                <div class="stat-icon bg-purple">
                    <span class="material-icons-round">article</span>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalArticles) ?></div>
                    <div class="stat-lbl">Total Articles</div>
                </div>
            </div>
            <div class="stat-card c-green">
                <div class="stat-icon bg-green">
                    <span class="material-icons-round">group</span>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($activeUsers) ?></div>
                    <div class="stat-lbl">Active Users</div>
                </div>
            </div>
            <div class="stat-card c-orange">
                <div class="stat-icon bg-orange">
                    <span class="material-icons-round">label</span>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalCategories) ?></div>
                    <div class="stat-lbl">Categories</div>
                </div>
            </div>
            <div class="stat-card c-blue">
                <div class="stat-icon bg-blue">
                    <span class="material-icons-round">business</span>
                </div>
                <div>
                    <div class="stat-val"><?= number_format($totalDepartments) ?></div>
                    <div class="stat-lbl">Departments</div>
                </div>
            </div>
        </div>

        <!-- Tab Bar -->
        <div class="tab-bar">
            <div class="tab-bar-inner">
                <button onclick="location.href='?tab=users'" class="tab-btn <?= $activeTab==='users'?'active':'' ?>">
                    <span class="material-icons-round">people</span>Users
                </button>
                <button onclick="location.href='?tab=departments'" class="tab-btn <?= $activeTab==='departments'?'active':'' ?>">
                    <span class="material-icons-round">business</span>Departments
                </button>
                <button onclick="location.href='?tab=categories'" class="tab-btn <?= $activeTab==='categories'?'active':'' ?>">
                    <span class="material-icons-round">label</span>Categories
                </button>
                <button onclick="location.href='?tab=analytics'" class="tab-btn <?= $activeTab==='analytics'?'active':'' ?>">
                    <span class="material-icons-round">analytics</span>Analytics
                </button>
                <?php if ($isSuperAdmin): ?>
                <button onclick="location.href='?tab=access'" class="tab-btn <?= $activeTab==='access'?'active':'' ?>">
                    <span class="material-icons-round">lock_open</span>Access Control
                </button>
                <?php endif; ?>
            </div>

            <!-- Tab content -->
            <div class="tab-content">
                <?php if ($activeTab === 'users'): ?>
                    <?php include 'tabs/users_tab.php'; ?>
                <?php elseif ($activeTab === 'departments'): ?>
                    <?php include 'tabs/departments_tab.php'; ?>
                <?php elseif ($activeTab === 'categories'): ?>
                    <?php include 'tabs/categories_tab.php'; ?>
                <?php elseif ($activeTab === 'analytics'): ?>
                    <?php include 'tabs/analytics_tab.php'; ?>
                <?php elseif ($activeTab === 'access' && $isSuperAdmin): ?>
                    <?php include 'tabs/access_tab.php'; ?>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- .main-scroll -->
</div><!-- .main -->

</div><!-- .app-shell -->

<!-- Toast stack -->
<div class="toast-stack" id="toastStack"></div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
'use strict';

/* ─── Dark mode ─────────────────────────────────────────── */
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

/* ─── Mobile sidebar ─────────────────────────────────────── */
(function () {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('mobOverlay');
    const menuBtn  = document.getElementById('mobMenuBtn');
    const closeBtn = document.getElementById('sbClose');
    if (!sidebar) return;

    function openSb()  { sidebar.classList.add('open'); overlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeSb() { sidebar.classList.remove('open'); overlay.classList.remove('show'); document.body.style.overflow = ''; }

    menuBtn?.addEventListener('click', openSb);
    closeBtn?.addEventListener('click', closeSb);
    overlay?.addEventListener('click', closeSb);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeSb(); });
})();

/* ─── Toast utility (used by tab includes) ───────────────── */
function showToast(msg, type = 'info', duration = 3000) {
    const icons = { success:'check_circle', error:'error', warning:'warning', info:'info' };
    const stack = document.getElementById('toastStack');
    const t = document.createElement('div');
    t.className = 'toast ' + type;
    t.innerHTML = `<span class="material-icons-round">${icons[type]||'info'}</span><span>${escHtml(msg)}</span>`;
    stack?.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 350); }, duration);
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

/* ─── Auto-dismiss URL-param notification ────────────────── */
(function () {
    const n = document.getElementById('topNotif');
    if (n) setTimeout(() => { n.style.transition = 'opacity .5s'; n.style.opacity = '0'; setTimeout(() => n.remove(), 500); }, 5000);
})();

/* ─── Modal helpers (used by tab includes) ──────────────── */
function openModal(id)  { document.getElementById(id)?.classList.add('open'); document.body.style.overflow = 'hidden'; }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); document.body.style.overflow = ''; }

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-bg.open').forEach(m => {
            m.classList.remove('open');
            document.body.style.overflow = '';
        });
    }
});

// Backward-compat shim so existing tab includes keep working
function showNotification(msg, type = 'info') { showToast(msg, type); }
</script>

</body>
</html>