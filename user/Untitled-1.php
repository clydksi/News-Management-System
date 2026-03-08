<?php
require '../auth.php';
require '../db.php';

// Dashboard statistics
$totalArticles = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Pagination settings - optimized for 3-column layout
$itemsPerPage = isset($_GET['per_page']) ? max(9, min(60, intval($_GET['per_page']))) : 9;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get section filter
$section = isset($_GET['section']) ? $_GET['section'] : 'all';

// NEW: View mode - 'flat' or 'threaded'
$viewMode = isset($_GET['view']) ? $_GET['view'] : 'flat';

// Advanced Filter Parameters
$filterCategory = isset($_GET['filter_category']) ? $_GET['filter_category'] : '';
$filterDepartment = isset($_GET['filter_department']) ? $_GET['filter_department'] : '';
$filterAuthor = isset($_GET['filter_author']) ? $_GET['filter_author'] : '';
$filterDateFrom = isset($_GET['filter_date_from']) ? $_GET['filter_date_from'] : '';
$filterDateTo = isset($_GET['filter_date_to']) ? $_GET['filter_date_to'] : '';
$filterSearch = isset($_GET['filter_search']) ? $_GET['filter_search'] : '';

// Count active filters
$activeFilters = 0;
if (!empty($filterCategory)) $activeFilters++;
if (!empty($filterDepartment)) $activeFilters++;
if (!empty($filterAuthor)) $activeFilters++;
if (!empty($filterDateFrom)) $activeFilters++;
if (!empty($filterDateTo)) $activeFilters++;
if (!empty($filterSearch)) $activeFilters++;

// Base query with enhanced parent-child info
$baseQuery = "SELECT n.*, 
                     u.username, 
                     d.name AS dept_name, 
                     c.name AS category_name,
                     n.thumbnail,
                     n.parent_article_id,
                     n.is_update,
                     n.update_type,
                     n.update_number,
                     (SELECT COUNT(*) FROM news WHERE parent_article_id = n.id) as update_count,
                     (SELECT MAX(created_at) FROM news WHERE parent_article_id = n.id) as latest_update_time,
                     parent.title as parent_title,
                     parent.id as parent_id
              FROM news n
              JOIN users u ON n.created_by = u.id
              JOIN departments d ON n.department_id = d.id
              LEFT JOIN categories c ON n.category_id = c.id
              LEFT JOIN news parent ON n.parent_article_id = parent.id";

$countQuery = "SELECT COUNT(*) as total
               FROM news n
               JOIN users u ON n.created_by = u.id
               JOIN departments d ON n.department_id = d.id
               LEFT JOIN categories c ON n.category_id = c.id";

$params = [];
$whereClauses = [];

// NEW: In threaded view, show only parent articles
if ($viewMode === 'threaded') {
    $whereClauses[] = "n.is_update = 0";
}

// Add department filter for non-admin users
if ($_SESSION['role'] !== 'admin') {
    $whereClauses[] = "n.department_id = ?";
    $params[] = $_SESSION['department_id'];
}

// Add section filter
switch($section) {
    case 'regular':
        $whereClauses[] = "n.is_pushed = 0";
        break;
    case 'edited':
        $whereClauses[] = "n.is_pushed = 1";
        break;
    case 'headline':
        $whereClauses[] = "n.is_pushed = 2";
        break;
    case 'archive':
        $whereClauses[] = "n.is_pushed = 3";
        break;
}

// Apply Advanced Filters
if (!empty($filterCategory)) {
    $whereClauses[] = "n.category_id = ?";
    $params[] = $filterCategory;
}

if (!empty($filterDepartment) && $_SESSION['role'] === 'admin') {
    $whereClauses[] = "n.department_id = ?";
    $params[] = $filterDepartment;
}

if (!empty($filterAuthor)) {
    $whereClauses[] = "n.created_by = ?";
    $params[] = $filterAuthor;
}

if (!empty($filterDateFrom)) {
    $whereClauses[] = "DATE(n.created_at) >= ?";
    $params[] = $filterDateFrom;
}

if (!empty($filterDateTo)) {
    $whereClauses[] = "DATE(n.created_at) <= ?";
    $params[] = $filterDateTo;
}

if (!empty($filterSearch)) {
    $whereClauses[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%$filterSearch%";
    $params[] = "%$filterSearch%";
}

// Build WHERE clause
if (!empty($whereClauses)) {
    $whereSQL = " WHERE " . implode(" AND ", $whereClauses);
    $baseQuery .= $whereSQL;
    $countQuery .= $whereSQL;
}

// Get total count
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Ensure current page is valid
if ($currentPage > $totalPages && $totalPages > 0) {
    $currentPage = $totalPages;
    $offset = ($currentPage - 1) * $itemsPerPage;
}

// Get paginated results
$paginatedQuery = $baseQuery . " ORDER BY n.created_at DESC LIMIT " . intval($itemsPerPage) . " OFFSET " . intval($offset);

$stmt = $pdo->prepare($paginatedQuery);
$stmt->execute($params);
$paginatedNews = $stmt->fetchAll();

// Get counts for each section
$regularCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 0";
$editedCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 1";
$headlineCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 2";
$archiveCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 3";

if ($_SESSION['role'] !== 'admin') {
    $deptFilter = " AND n.department_id = ?";
    $regularCountQuery .= $deptFilter;
    $editedCountQuery .= $deptFilter;
    $headlineCountQuery .= $deptFilter;
    $archiveCountQuery .= $deptFilter;
    
    $regularCount = $pdo->prepare($regularCountQuery);
    $regularCount->execute([$_SESSION['department_id']]);
    $regularNewsCount = $regularCount->fetchColumn();
    
    $editedCount = $pdo->prepare($editedCountQuery);
    $editedCount->execute([$_SESSION['department_id']]);
    $editedNewsCount = $editedCount->fetchColumn();
    
    $headlineCount = $pdo->prepare($headlineCountQuery);
    $headlineCount->execute([$_SESSION['department_id']]);
    $headlineNewsCount = $headlineCount->fetchColumn();

    $archiveCount = $pdo->prepare($archiveCountQuery);
    $archiveCount->execute([$_SESSION['department_id']]);
    $archiveNewsCount = $archiveCount->fetchColumn();
} else {
    $regularNewsCount = $pdo->query($regularCountQuery)->fetchColumn();
    $editedNewsCount = $pdo->query($editedCountQuery)->fetchColumn();
    $headlineNewsCount = $pdo->query($headlineCountQuery)->fetchColumn();
    $archiveNewsCount = $pdo->query($archiveCountQuery)->fetchColumn();
}

// Get filter options
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT id, name FROM departments ORDER BY name")->fetchAll();
$authors = $pdo->query("SELECT id, username FROM users WHERE is_active = 1 ORDER BY username")->fetchAll();

// Calculate pagination range
$maxPaginationLinks = 5;
$paginationStart = max(1, $currentPage - floor($maxPaginationLinks / 2));
$paginationEnd = min($totalPages, $paginationStart + $maxPaginationLinks - 1);

if ($paginationEnd - $paginationStart < $maxPaginationLinks - 1) {
    $paginationStart = max(1, $paginationEnd - $maxPaginationLinks + 1);
}

// Helper Functions
function getPaginationUrl($page, $section = null, $perPage = null) {
    $params = $_GET;
    $params['page'] = $page;
    if ($section && $section !== 'all') {
        $params['section'] = $section;
    }
    if ($perPage) {
        $params['per_page'] = $perPage;
    }
    return '?' . http_build_query($params);
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function getThumbnailUrl($thumbnailPath) {
    if (!empty($thumbnailPath) && file_exists($thumbnailPath)) {
        return $thumbnailPath;
    }
    return 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="400" height="250"%3E%3Cdefs%3E%3ClinearGradient id="grad" x1="0%25" y1="0%25" x2="100%25" y2="100%25"%3E%3Cstop offset="0%25" style="stop-color:%236D28D9;stop-opacity:1" /%3E%3Cstop offset="100%25" style="stop-color:%239333EA;stop-opacity:1" /%3E%3C/linearGradient%3E%3C/defs%3E%3Crect width="400" height="250" fill="url(%23grad)" /%3E%3Ctext x="50%25" y="50%25" dominant-baseline="middle" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" fill="white"%3ENo Image%3C/text%3E%3C/svg%3E';
}

function getStatusBadge($isPushed) {
    $statusLabels = [
        0 => ['label' => 'Regular', 'class' => 'bg-yellow-100 text-yellow-800'],
        1 => ['label' => 'Edited', 'class' => 'bg-green-100 text-green-800'],
        2 => ['label' => 'Headline', 'class' => 'bg-blue-100 text-blue-800'],
        3 => ['label' => 'Archive', 'class' => 'bg-red-100 text-red-800']
    ];
    return $statusLabels[$isPushed] ?? $statusLabels[0];
}

function getSectionTitle($section) {
    switch($section) {
        case 'regular': return 'Regular News Articles';
        case 'edited': return 'Edited News Articles';
        case 'headline': return 'Headline News Articles';
        case 'archive': return 'Archive News Articles';
        default: return 'Latest News Articles';
    }
}

function getActiveFiltersArray() {
    global $filterCategory, $filterDepartment, $filterAuthor, $filterDateFrom, $filterDateTo, $filterSearch;
    global $categories, $departments, $authors;
    
    $activeFilters = [];
    
    if (!empty($filterCategory)) {
        $cat = array_filter($categories, fn($c) => $c['id'] == $filterCategory);
        $catName = !empty($cat) ? reset($cat)['name'] : 'Unknown';
        $activeFilters[] = ['type' => 'Category', 'value' => $catName, 'param' => 'filter_category'];
    }
    
    if (!empty($filterDepartment)) {
        $dept = array_filter($departments, fn($d) => $d['id'] == $filterDepartment);
        $deptName = !empty($dept) ? reset($dept)['name'] : 'Unknown';
        $activeFilters[] = ['type' => 'Department', 'value' => $deptName, 'param' => 'filter_department'];
    }
    
    if (!empty($filterAuthor)) {
        $auth = array_filter($authors, fn($a) => $a['id'] == $filterAuthor);
        $authName = !empty($auth) ? reset($auth)['username'] : 'Unknown';
        $activeFilters[] = ['type' => 'Author', 'value' => $authName, 'param' => 'filter_author'];
    }
    
    if (!empty($filterDateFrom)) {
        $activeFilters[] = ['type' => 'From Date', 'value' => date('M d, Y', strtotime($filterDateFrom)), 'param' => 'filter_date_from'];
    }
    
    if (!empty($filterDateTo)) {
        $activeFilters[] = ['type' => 'To Date', 'value' => date('M d, Y', strtotime($filterDateTo)), 'param' => 'filter_date_to'];
    }
    
    if (!empty($filterSearch)) {
        $activeFilters[] = ['type' => 'Search', 'value' => $filterSearch, 'param' => 'filter_search'];
    }
    
    return $activeFilters;
}

// NEW: Enhanced article type badge with live indicator
function getArticleTypeBadge($article) {
    if ($article['is_update'] == 1) {
        $updateTypeLabel = $article['update_type'] ?: 'Update';
        return [
            'label' => $updateTypeLabel . ' #' . $article['update_number'],
            'class' => 'bg-gradient-to-r from-orange-100 to-red-100 text-orange-800 border border-orange-300',
            'icon' => 'fiber_manual_record',
            'type' => 'update'
        ];
    } elseif ($article['update_count'] > 0) {
        // Check if latest update was within last 24 hours for "LIVE" indicator
        $isLive = false;
        if ($article['latest_update_time']) {
            $hoursSinceUpdate = (time() - strtotime($article['latest_update_time'])) / 3600;
            $isLive = $hoursSinceUpdate < 24;
        }
        
        return [
            'label' => ($isLive ? '🔴 LIVE • ' : '') . $article['update_count'] . ' update' . ($article['update_count'] > 1 ? 's' : ''),
            'class' => $isLive ? 'bg-gradient-to-r from-red-100 to-pink-100 text-red-800 border border-red-300 animate-pulse' : 'bg-gradient-to-r from-blue-100 to-indigo-100 text-blue-800 border border-blue-300',
            'icon' => 'account_tree',
            'type' => 'parent',
            'isLive' => $isLive
        ];
    }
    return null;
}

// NEW: Function to get all updates for a parent article
function getArticleUpdates($pdo, $parentId) {
    $query = "SELECT n.*, 
                     u.username, 
                     d.name AS dept_name, 
                     c.name AS category_name
              FROM news n
              JOIN users u ON n.created_by = u.id
              JOIN departments d ON n.department_id = d.id
              LEFT JOIN categories c ON n.category_id = c.id
              WHERE n.parent_article_id = ?
              ORDER BY n.update_number ASC, n.created_at ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>News Dashboard - <?= ucfirst($section) ?> Articles</title>
    <meta name="description" content="Professional news dashboard for managing articles and content">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <style>
        /* Keep all existing styles */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
        }
        
        .sidebar { 
            background: linear-gradient(180deg, #6D28D9 0%, #5B21B6 100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .main-content { 
            background-color: #F8FAFC;
        }
        
        .sidebar-hidden { transform: translateX(-100%); }
        
        @media (min-width: 1024px) {
            .sidebar-hidden { transform: translateX(0); }
        }
        
        .modal-backdrop { backdrop-filter: blur(8px); }
        
        .modal-enter { 
            animation: modalEnter 0.3s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        
        @keyframes modalEnter {
            from { 
                opacity: 0; 
                transform: scale(0.95) translateY(-20px); 
            }
            to { 
                opacity: 1; 
                transform: scale(1) translateY(0); 
            }
        }
        
        .news-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .news-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(109, 40, 217, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
        }
        
        .news-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(109, 40, 217, 0.15);
        }
        
        .news-card:hover::before {
            opacity: 1;
        }
        
        .truncate-text { 
            display: -webkit-box; 
            -webkit-line-clamp: 2; 
            -webkit-box-orient: vertical; 
            overflow: hidden;
            line-height: 1.6;
        }
        
        .thumbnail-container {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            overflow: hidden;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .thumbnail-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .news-card:hover .thumbnail-image {
            transform: scale(1.1);
        }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            border: 1px solid rgba(109, 40, 217, 0.1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(109, 40, 217, 0.1);
            border-color: rgba(109, 40, 217, 0.3);
        }
        
        .action-btn {
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .action-btn:active::before {
            width: 300px;
            height: 300px;
        }
        
        .skeleton {
            animation: skeleton-loading 1s linear infinite alternate;
        }
        
        @keyframes skeleton-loading {
            0% { background-color: hsl(200, 20%, 80%); }
            100% { background-color: hsl(200, 20%, 95%); }
        }
        
        .page-transition {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .nav-item {
            position: relative;
            transition: all 0.3s ease;
        }
        
        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .nav-item:hover::before,
        .nav-item.active::before {
            transform: scaleY(1);
        }
        
        .dropdown-menu {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }
        
        .dropdown-menu.show {
            max-height: 500px;
        }
        
        @media (max-width: 1023px) {
            #sidebar { padding-bottom: 5rem; }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, #6D28D9 0%, #9333EA 100%);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, #5B21B6 0%, #7E22CE 100%);
        }
        
        /* Loading State */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        
        .loading-overlay.show {
            opacity: 1;
            pointer-events: all;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #6D28D9;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Filter Badge Animation */
        .filter-badge {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .8; }
        }

        /* Active Filter Chip */
        .filter-chip {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        /* NEW: Update Thread Styles */
        .update-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .update-timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, #f97316, #dc2626);
        }
        
        .update-item {
            position: relative;
            margin-bottom: 1.5rem;
            animation: slideInUp 0.4s ease-out;
        }
        
        .update-item::before {
            content: '';
            position: absolute;
            left: -1.55rem;
            top: 0.5rem;
            width: 12px;
            height: 12px;
            background: white;
            border: 3px solid #f97316;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Live indicator animation */
        @keyframes live-pulse {
            0%, 100% { 
                transform: scale(1);
                opacity: 1;
            }
            50% { 
                transform: scale(1.1);
                opacity: 0.8;
            }
        }
        
        .live-indicator {
            animation: live-pulse 2s ease-in-out infinite;
        }
        
        /* Thread connector lines */
        .thread-connector {
            position: absolute;
            left: -8px;
            top: 50%;
            width: 8px;
            height: 2px;
            background: linear-gradient(to right, transparent, #9333EA);
        }
    </style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay">
    <div class="text-center">
        <div class="spinner mx-auto mb-4"></div>
        <p class="text-gray-600 font-medium">Loading...</p>
    </div>
</div>

<!-- Mobile Header -->
<header class="lg:hidden bg-gradient-to-r from-purple-600 to-purple-700 text-white p-4 flex items-center justify-between shadow-lg">
    <button id="sidebarToggle" class="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors">
        <span class="material-icons text-2xl">menu</span>
    </button>
    <div class="flex items-center">
        <span class="material-icons text-2xl mr-2">feed</span>
        <h1 class="text-lg font-bold">News Dashboard</h1>
    </div>
    <div class="flex items-center space-x-2">
        <button id="advancedFilterBtnMobile" class="relative text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors">
            <span class="material-icons text-2xl">filter_list</span>
            <?php if ($activeFilters > 0): ?>
            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center filter-badge"><?= $activeFilters ?></span>
            <?php endif; ?>
        </button>
        <div class="w-8 h-8 bg-white bg-opacity-30 rounded-full flex items-center justify-center text-white font-bold text-sm backdrop-blur-sm">
            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
        </div>
    </div>
</header>

<div class="flex flex-1 relative overflow-hidden">
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden transition-opacity duration-300"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed lg:relative w-64 h-full text-white flex flex-col p-4 z-30 lg:translate-x-0 -translate-x-full">
        <div class="flex items-center mb-8 lg:mb-10">
            <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center mr-3 backdrop-blur-sm">
                <span class="material-icons text-2xl">feed</span>
            </div>
            <h1 class="text-xl lg:text-2xl font-bold">News Dashboard</h1>
            <button id="sidebarClose" class="ml-auto lg:hidden text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <!-- Scrollable navigation items -->
        <nav class="flex-1 overflow-y-auto min-h-0 space-y-1">
            <div class="mb-4">
                <p class="text-xs font-semibold text-purple-200 uppercase tracking-wider mb-2 px-3">Navigation</p>
                <ul class="space-y-1">
                    <li>
                        <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors <?= $section === 'all' ? 'bg-purple-700' : '' ?>" 
                        href="user_dashboard.php">
                            <span class="material-icons mr-3 text-xl">dashboard</span> All News
                            <span class="ml-auto bg-purple-500 px-2 py-1 rounded-full text-xs"><?= $totalArticles ?></span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $section === 'regular' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?section=regular">
                            <span class="material-icons mr-3 text-xl">add_to_queue</span> 
                            <span class="flex-1">Regular News</span>
                            <span class="bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                                <?= $regularNewsCount ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $section === 'edited' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?section=edited">
                            <span class="material-icons mr-3 text-xl">article</span> 
                            <span class="flex-1">Edited News</span>
                            <span class="bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                                <?= $editedNewsCount ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $section === 'headline' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?section=headline">
                            <span class="material-icons mr-3 text-xl">art_track</span> 
                            <span class="flex-1">Headlines</span>
                            <span class="bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                                <?= $headlineNewsCount ?>
                            </span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $section === 'archive' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?section=archive">
                            <span class="material-icons mr-3 text-xl">archive</span> 
                            <span class="flex-1">Archive</span>
                            <span class="bg-white bg-opacity-20 px-2 py-1 rounded-full text-xs font-medium backdrop-blur-sm">
                                <?= $archiveNewsCount ?>
                            </span>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- NEW: View Mode Toggle
            <div class="mb-4">
                <p class="text-xs font-semibold text-purple-200 uppercase tracking-wider mb-2 px-3">View Mode</p>
                <ul class="space-y-1">
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $viewMode === 'flat' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?<?= http_build_query(array_merge($_GET, ['view' => 'flat'])) ?>">
                            <span class="material-icons mr-3 text-xl">view_list</span> 
                            <span class="flex-1">Flat View</span>
                        </a>
                    </li>
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 <?= $viewMode === 'threaded' ? 'bg-white bg-opacity-10 active' : '' ?>" 
                        href="?<?= http_build_query(array_merge($_GET, ['view' => 'threaded', 'page' => 1])) ?>">
                            <span class="material-icons mr-3 text-xl">account_tree</span> 
                            <span class="flex-1">Threaded View</span>
                        </a>
                    </li>
                </ul>
            </div>-->

            <div class="mb-4">
                <p class="text-xs font-semibold text-purple-200 uppercase tracking-wider mb-2 px-3">Generate</p>
                <div class="dropdown-container">
                    <button class="nav-item dropdown-toggle flex items-center justify-between w-full p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200">
                        <div class="flex items-center">
                            <span class="material-icons mr-3 text-xl">description</span>
                            <span>AI & API News</span>
                        </div>
                        <span class="material-icons text-xl dropdown-arrow transition-transform duration-300">expand_more</span>
                    </button>
                    
                    <ul class="dropdown-menu mt-1 ml-8 space-y-1">
                        <li>
                            <a href="news.php" target="_blank" class="flex items-center p-2 pl-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 text-sm">
                                <span class="material-icons mr-2 text-lg">auto_awesome</span>
                                AI Generated
                            </a>
                        </li>
                        <li>
                            <a href="mediastack.php" class="flex items-center p-2 pl-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 text-sm">
                                <span class="material-icons mr-2 text-lg">link</span>
                                Mediastack
                            </a>
                        </li>
                        <li>
                            <a href="newsapi.php" class="flex items-center p-2 pl-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 text-sm">
                                <span class="material-icons mr-2 text-lg">article</span>
                                NewsAPI
                            </a>
                        </li>
                        <li>
                            <a href="reuters.php" class="flex items-center p-2 pl-4 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200 text-sm">
                                <span class="material-icons mr-2 text-lg">rss_feed</span>
                                Reuters
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div>
                <p class="text-xs font-semibold text-purple-200 uppercase tracking-wider mb-2 px-3">Community</p>
                <ul class="space-y-1">
                    <li>
                        <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" 
                        href="https://project.mbcradio.net/saas/chat.php">
                            <span class="material-icons mr-3 text-xl">forum</span> 
                            <span>Chat Community</span>
                            <span class="material-icons ml-auto text-sm">open_in_new</span>
                        </a>
                    </li>
                </ul>
            </div>
        </nav>
        
        <div class="border-t border-white border-opacity-20 pt-4 mt-4 flex-shrink-0">
            <ul class="space-y-1">
                <li>
                    <a class="nav-item flex items-center p-3 hover:bg-white hover:bg-opacity-10 rounded-lg transition-all duration-200" 
                       href="#" onclick="openSettingsModal(); return false;">
                        <span class="material-icons mr-3 text-xl">settings</span> 
                        <span>Settings</span>
                    </a>
                </li>
                <li>
                    <a class="nav-item flex items-center p-3 hover:bg-red-500 hover:bg-opacity-20 rounded-lg transition-all duration-200" 
                       href="../logout.php">
                        <span class="material-icons mr-3 text-xl">logout</span> 
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content flex-1 flex flex-col overflow-hidden">
        <!-- Desktop Header -->
        <header class="hidden lg:flex justify-between items-center p-6 lg:p-8 bg-white shadow-sm">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-600 to-purple-700 rounded-xl flex items-center justify-center mr-4 shadow-lg">
                    <span class="material-icons text-3xl text-white">article</span>
                </div>
                <div>
                    <h2 class="text-2xl lg:text-3xl font-bold text-gray-800">Articles Dashboard</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $viewMode === 'threaded' ? 'Viewing threaded articles with updates' : 'Viewing all articles' ?>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <button id="advancedFilterBtn" class="relative bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center font-medium shadow-lg hover:shadow-xl">
                    <span class="material-icons text-xl mr-2">filter_list</span>
                    Advanced Filter
                    <?php if ($activeFilters > 0): ?>
                    <span class="ml-2 bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5 filter-badge"><?= $activeFilters ?></span>
                    <?php endif; ?>
                </button>
                <div class="flex items-center bg-gray-50 rounded-xl p-3 border border-gray-100">
                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold mr-3 shadow-md">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800"><?= e($_SESSION['username'] ?? 'Username') ?></p>
                        <p class="text-xs text-gray-500"><?= ucfirst($_SESSION['role'] ?? 'User') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Search Bar -->
        <div class="lg:hidden p-4 bg-white border-b shadow-sm">
            <div class="relative">
                <input class="bg-gray-100 rounded-xl py-3 pl-12 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full" 
                       placeholder="Search articles..." type="text" id="searchInputMobile"/>
                <span class="material-icons absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8 page-transition">
            <!-- Dashboard cards - 4 cards responsive -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
                <div class="stat-card p-5 lg:p-6 rounded-2xl shadow-sm flex flex-col items-center justify-center min-h-[130px] lg:flex-row lg:justify-start">
                    <div class="bg-gradient-to-br from-purple-100 to-purple-200 p-4 rounded-xl mb-3 lg:mb-0 lg:mr-4 shadow-sm">
                        <span class="material-icons text-3xl text-purple-600">article</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-1"><?= $totalItems ?></h3>
                        <p class="text-gray-500 text-sm lg:text-base font-medium">
                            <?= $viewMode === 'threaded' ? 'Parent Articles' : 'Filtered Results' ?>
                        </p>
                    </div>
                </div>
                
                <div class="stat-card p-5 lg:p-6 rounded-2xl shadow-sm flex flex-col items-center justify-center min-h-[130px] lg:flex-row lg:justify-start">
                    <div class="bg-gradient-to-br from-green-100 to-green-200 p-4 rounded-xl mb-3 lg:mb-0 lg:mr-4 shadow-sm">
                        <span class="material-icons text-3xl text-green-600">group</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-1"><?= $activeUsers ?></h3>
                        <p class="text-gray-500 text-sm lg:text-base font-medium">Active Users</p>
                    </div>
                </div>
                
                <div class="stat-card p-5 lg:p-6 rounded-2xl shadow-sm flex flex-col items-center justify-center min-h-[130px] lg:flex-row lg:justify-start">
                    <div class="bg-gradient-to-br from-orange-100 to-orange-200 p-4 rounded-xl mb-3 lg:mb-0 lg:mr-4 shadow-sm">
                        <span class="material-icons text-3xl text-orange-600">category</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-1"><?= $totalCategories ?></h3>
                        <p class="text-gray-500 text-sm lg:text-base font-medium">Categories</p>
                    </div>
                </div>
                
                <div class="stat-card p-5 lg:p-6 rounded-2xl shadow-sm flex flex-col items-center justify-center min-h-[130px] lg:flex-row lg:justify-start">
                    <div class="bg-gradient-to-br from-red-100 to-red-200 p-4 rounded-xl mb-3 lg:mb-0 lg:mr-4 shadow-sm">
                        <span class="material-icons text-3xl text-red-600">business</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-3xl lg:text-4xl font-bold text-gray-800 mb-1"><?= $pendingReviews ?></h3>
                        <p class="text-gray-500 text-sm lg:text-base font-medium">Departments</p>
                    </div>
                </div>
            </section>

            <!-- Active Filters Display -->
            <?php 
            $activeFiltersList = getActiveFiltersArray();
            if (!empty($activeFiltersList)): 
            ?>
            <section class="mb-6">
                <div class="bg-white rounded-2xl shadow-sm p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <span class="material-icons text-purple-600 mr-2">filter_alt</span>
                            Active Filters (<?= count($activeFiltersList) ?>)
                        </h3>
                        <button onclick="clearAllFilters()" class="text-red-600 hover:text-red-700 text-sm font-medium flex items-center">
                            <span class="material-icons text-sm mr-1">clear</span>
                            Clear All
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($activeFiltersList as $filter): ?>
                        <div class="filter-chip bg-gradient-to-r from-purple-50 to-purple-100 border border-purple-200 text-purple-700 px-4 py-2 rounded-full flex items-center gap-2 text-sm font-medium">
                            <span class="font-semibold"><?= $filter['type'] ?>:</span>
                            <span><?= e($filter['value']) ?></span>
                            <button onclick="removeFilter('<?= $filter['param'] ?>')" class="ml-1 hover:bg-purple-200 rounded-full p-1 transition-colors">
                                <span class="material-icons text-sm">close</span>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- News Articles Section -->
            <section class="mb-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-6 bg-white p-5 rounded-2xl shadow-sm">
                    <div class="flex items-center mb-4 lg:mb-0">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center mr-3">
                            <span class="material-icons text-purple-600">newspaper</span>
                        </div>
                        <div>
                            <h3 class="text-xl lg:text-2xl font-bold text-gray-800">
                                <?= getSectionTitle($section) ?>
                            </h3>
                            <p class="text-sm text-gray-500">
                                <?= $viewMode === 'threaded' ? 'Organized by update threads' : 'Browse and manage your articles' ?>
                            </p>
                        </div>
                        <button id="refreshArticles" 
                                class="ml-4 bg-gray-100 hover:bg-purple-50 text-gray-600 hover:text-purple-600 p-2.5 rounded-lg transition-all duration-200 flex items-center justify-center group shadow-sm" 
                                title="Refresh Articles">
                            <span class="material-icons text-xl group-hover:rotate-180 transition-transform duration-500">refresh</span>
                        </button>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3">
                        <button onclick="window.location.href='function/create.php';"
                                class="action-btn bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-5 py-3 rounded-xl transition-all duration-200 flex items-center justify-center text-base font-medium shadow-lg hover:shadow-xl">
                            <span class="material-icons text-xl mr-2">add_circle</span>
                            Add Article
                        </button>

                        <button onclick="window.location.href='others/category.php';"
                                class="action-btn bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-5 py-3 rounded-xl transition-all duration-200 flex items-center justify-center text-base font-medium shadow-lg hover:shadow-xl">
                            <span class="material-icons text-xl mr-2">label</span>
                            Categories
                        </button>
                    </div>
                </div>

                <?php if (empty($paginatedNews)): ?>
                    <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span class="material-icons text-6xl text-purple-600">article</span>
                        </div>
                        <h4 class="text-2xl font-bold text-gray-800 mb-3">No Articles Found</h4>
                        <p class="text-gray-500 mb-6 max-w-md mx-auto">
                            <?php if ($activeFilters > 0): ?>
                                No articles match your current filters. Try adjusting your search criteria.
                            <?php elseif ($section !== 'all'): ?>
                                No articles in this section yet. Create your first article to get started!
                            <?php else: ?>
                                Start creating your first news article to see it here.
                            <?php endif; ?>
                        </p>
                        <?php if ($activeFilters > 0): ?>
                        <button onclick="clearAllFilters()"
                                class="action-btn bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white px-8 py-3 rounded-xl transition-all duration-200 inline-flex items-center font-medium shadow-lg hover:shadow-xl mr-3">
                            <span class="material-icons mr-2">clear</span>
                            Clear Filters
                        </button>
                        <?php endif; ?>
                        <button onclick="window.location.href='function/create.php';"
                                class="action-btn bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-8 py-3 rounded-xl transition-all duration-200 inline-flex items-center font-medium shadow-lg hover:shadow-xl">
                            <span class="material-icons mr-2">add_circle</span>
                            Create Article
                        </button>
                    </div>
                <?php else: ?>
                    <!-- 3 COLUMN GRID LAYOUT -->
                    <div id="articlesGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 transition-opacity duration-300">
                        <?php foreach ($paginatedNews as $article): 
                            $status = getStatusBadge($article['is_pushed']);
                            $articleType = getArticleTypeBadge($article);
                        ?>
                            <div class="news-card bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden flex flex-col">
                                <!-- Thumbnail Image -->
                                <div class="thumbnail-container">
                                    <img src="<?= getThumbnailUrl($article['thumbnail']) ?>" 
                                         alt="<?= e($article['title']) ?>" 
                                         class="thumbnail-image"
                                         loading="lazy"/>
                                    <div class="absolute top-3 right-3">
                                        <span class="<?= $status['class'] ?> px-3 py-1.5 rounded-full text-xs font-semibold backdrop-blur-md bg-opacity-95 shadow-lg">
                                            <?= $status['label'] ?>
                                        </span>
                                    </div>
                                    <?php if ($articleType && isset($articleType['isLive']) && $articleType['isLive']): ?>
                                    <div class="absolute top-3 left-3">
                                        <span class="live-indicator bg-red-500 text-white px-3 py-1.5 rounded-full text-xs font-bold shadow-lg flex items-center">
                                            <span class="w-2 h-2 bg-white rounded-full mr-2 animate-pulse"></span>
                                            LIVE
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="p-5 flex-1 flex flex-col border-l-4 <?= $article['is_update'] == 1 ? 'border-orange-500' : ($article['update_count'] > 0 ? 'border-blue-500' : 'border-purple-500') ?>">
                                    <!-- Category Badge and Article Type Badge -->
                                    <div class="mb-3 flex items-center gap-2 flex-wrap">
                                        <span class="inline-flex items-center bg-gradient-to-r from-purple-50 to-purple-100 text-purple-700 px-3 py-1.5 rounded-full text-xs font-semibold">
                                            <span class="material-icons text-xs mr-1">label</span>
                                            <?= e($article['category_name'] ?: 'Uncategorized') ?>
                                        </span>
                                        
                                        <!-- Article Type Badge -->
                                        <?php if ($articleType): ?>
                                        <span class="inline-flex items-center <?= $articleType['class'] ?> px-3 py-1.5 rounded-full text-xs font-bold shadow-sm">
                                            <span class="material-icons text-xs mr-1"><?= $articleType['icon'] ?></span>
                                            <?= $articleType['label'] ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- If this is an update, show parent article link -->
                                    <?php if ($article['is_update'] == 1 && $article['parent_title']): ?>
                                    <div class="mb-3 p-2 bg-orange-50 border border-orange-200 rounded-lg">
                                        <p class="text-xs text-orange-700 flex items-center">
                                            <span class="material-icons text-xs mr-1">link</span>
                                            <span class="font-semibold mr-1">Parent:</span>
                                            <button onclick="openModal('modal-<?= $article['parent_id'] ?>')" class="truncate hover:underline flex-1 text-left">
                                                <?= e($article['parent_title']) ?>
                                            </button>
                                        </p>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Title -->
                                    <h4 class="font-bold text-gray-800 mb-3 text-lg leading-tight hover:text-purple-600 transition-colors cursor-pointer line-clamp-2"
                                        onclick="openModal('modal-<?= $article['id'] ?>')">
                                        <?= e($article['title']) ?>
                                    </h4>
                                    
                                    <!-- Content Preview -->
                                    <p class="text-gray-600 leading-relaxed truncate-text mb-4 text-sm flex-1">
                                        <?= e(strip_tags($article['content'])) ?>
                                    </p>
                                    
                                    <!-- Metadata -->
                                    <div class="grid grid-cols-2 gap-3 py-3 border-t border-gray-100 mb-4">
                                        <div class="flex items-center text-xs text-gray-500">
                                            <div class="w-8 h-8 bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center mr-2">
                                                <span class="material-icons text-sm text-purple-600">person</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-semibold text-gray-700 truncate"><?= e($article['username'] ?: 'Unknown') ?></p>
                                                <p class="text-xs text-gray-400">Author</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-xs text-gray-500">
                                            <div class="w-8 h-8 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center mr-2">
                                                <span class="material-icons text-sm text-blue-600">business</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="font-semibold text-gray-700 truncate"><?= e($article['dept_name'] ?: 'N/A') ?></p>
                                                <p class="text-xs text-gray-400">Department</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Date -->
                                    <div class="flex items-center text-xs text-gray-500 bg-gradient-to-r from-gray-50 to-gray-100 px-3 py-2 rounded-lg mb-4">
                                        <span class="material-icons text-sm mr-2">schedule</span>
                                        <?= date('M d, Y • g:i A', strtotime($article['created_at'])) ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <button 
                                            class="action-btn bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                            title="View Article"
                                            onclick="openModal('modal-<?= $article['id'] ?>')">
                                            <span class="material-icons text-sm mr-1">visibility</span>
                                            View
                                        </button>

                                        <!-- NEW: View Updates Button (for parent articles with updates) -->
                                        <?php if ($article['is_update'] == 0 && $article['update_count'] > 0): ?>
                                        <button onclick="viewUpdateThread(<?= $article['id'] ?>)"
                                            class="action-btn bg-gradient-to-r from-teal-50 to-cyan-100 hover:from-teal-100 hover:to-cyan-200 text-teal-700 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-semibold shadow-sm hover:shadow-md" 
                                            title="View Update Thread">
                                            <span class="material-icons text-sm mr-1">timeline</span>
                                            View Thread
                                        </button>
                                        <?php else: ?>
                                        <button onclick="window.location.href='function/update.php?id=<?= $article['id'] ?>';"
                                            class="action-btn bg-green-50 hover:bg-green-100 text-green-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                            title="Edit Article">
                                            <span class="material-icons text-sm mr-1">edit</span>
                                            Edit
                                        </button>
                                        <?php endif; ?>

                                        <button onclick="confirmDelete(<?= $article['id'] ?>)"
                                            class="action-btn bg-red-50 hover:bg-red-100 text-red-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                            title="Delete Article">
                                            <span class="material-icons text-sm mr-1">delete</span>
                                            Delete
                                        </button>

                                        <button onclick="window.open('others/print_headline.php?id=<?= $article['id'] ?>', '_blank');"
                                            class="action-btn bg-violet-50 hover:bg-violet-100 text-violet-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                            title="Print Article">
                                            <span class="material-icons text-sm mr-1">print</span>
                                            Print
                                        </button>

                                        <!-- Link to Parent Button - Only for non-updates -->
                                        <?php if ($article['is_update'] == 0): ?>
                                        <button onclick="window.location.href='function/link_to_parent.php?id=<?= $article['id'] ?>';"
                                            class="action-btn col-span-2 bg-gradient-to-r from-teal-50 to-teal-100 hover:from-teal-100 hover:to-teal-200 text-teal-700 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-semibold shadow-sm hover:shadow-md" 
                                            title="Link to Parent Article">
                                            <span class="material-icons text-sm mr-1">link</span>
                                            Link to Parent
                                        </button>
                                        <?php endif; ?>

                                        <!-- Add Update Button - Only for non-updates -->
                                        <?php if ($article['is_update'] == 0): ?>
                                        <button onclick="window.location.href='function/add_update.php?parent_id=<?= $article['id'] ?>';"
                                            class="action-btn col-span-2 bg-gradient-to-r from-orange-50 to-orange-100 hover:from-orange-100 hover:to-orange-200 text-orange-700 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-semibold shadow-sm hover:shadow-md" 
                                            title="Add Update">
                                            <span class="material-icons text-sm mr-1">add_circle</span>
                                            Add Update to Article
                                        </button>
                                        <?php endif; ?>

                                        <!-- Push/Revert Buttons -->
                                        <?php if ($article['is_pushed'] == 0): ?>
                                            <button onclick="window.location.href='function/push.php?id=<?= $article['id'] ?>&to=1';"
                                                class="action-btn col-span-2 bg-gradient-to-r from-amber-50 to-amber-100 hover:from-amber-100 hover:to-amber-200 text-amber-700 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-semibold shadow-sm hover:shadow-md" 
                                                title="Push to Edited">
                                                <span class="material-icons text-sm mr-1">rocket_launch</span>
                                                Push to Edited
                                            </button>
                                        <?php elseif ($article['is_pushed'] == 1): ?>
                                            <button onclick="window.location.href='function/push.php?id=<?= $article['id'] ?>&to=2';"
                                                class="action-btn bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                                title="Push to Headlines">
                                                <span class="material-icons text-sm mr-1">star</span>
                                                Headlines
                                            </button>
                                            <button onclick="confirmRevert(<?= $article['id'] ?>, 0)"
                                                class="action-btn bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                                title="Revert to Regular">
                                                <span class="material-icons text-sm mr-1">undo</span>
                                                Revert
                                            </button>
                                        <?php elseif ($article['is_pushed'] == 2): ?>
                                            <button onclick="
                                                if(confirm('Are you sure you want to publish this article?')) {
                                                    window.location.href='public/simple_publish.php?id=<?= $article['id'] ?>&to=1';
                                                }"
                                                class="action-btn bg-gradient-to-r from-green-50 to-green-100 hover:from-green-100 hover:to-green-200 text-green-700 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-semibold shadow-sm hover:shadow-md" 
                                                title="Publish">
                                                <span class="material-icons text-sm mr-1">publish</span>
                                                Publish
                                            </button>

                                            <button onclick="confirmRevert(<?= $article['id'] ?>, 1)"
                                                class="action-btn bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2.5 rounded-lg transition-all duration-200 flex items-center justify-center text-sm font-medium shadow-sm hover:shadow-md" 
                                                title="Revert to Edited">
                                                <span class="material-icons text-sm mr-1">undo</span>
                                                Revert
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Enhanced Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex flex-col sm:flex-row justify-between items-center mt-8 space-y-4 sm:space-y-0 bg-white p-5 rounded-2xl shadow-sm">
                            <!-- Results Info -->
                            <div class="text-sm text-gray-600 font-medium">
                                Showing <span class="text-purple-600 font-bold"><?= $offset + 1 ?></span> to 
                                <span class="text-purple-600 font-bold"><?= min($offset + $itemsPerPage, $totalItems) ?></span> of 
                                <span class="text-purple-600 font-bold"><?= $totalItems ?></span> articles
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="flex items-center space-x-2">
                                <!-- Previous Button -->
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?= getPaginationUrl($currentPage - 1, $section, $itemsPerPage) ?>" 
                                       class="action-btn bg-white hover:bg-purple-50 border-2 border-gray-200 hover:border-purple-300 text-gray-700 hover:text-purple-600 px-3 py-2 rounded-lg transition-all duration-200 flex items-center font-medium shadow-sm hover:shadow-md">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border-2 border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- First page -->
                                <?php if ($paginationStart > 1): ?>
                                    <a href="<?= getPaginationUrl(1, $section, $itemsPerPage) ?>" 
                                       class="action-btn bg-white hover:bg-purple-50 border-2 border-gray-200 hover:border-purple-300 text-gray-700 hover:text-purple-600 px-4 py-2 rounded-lg transition-all duration-200 font-medium shadow-sm hover:shadow-md">1</a>
                                    <?php if ($paginationStart > 2): ?>
                                        <span class="text-gray-400 px-2 font-bold">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Page numbers -->
                                <?php for ($i = $paginationStart; $i <= $paginationEnd; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="bg-gradient-to-r from-purple-600 to-purple-700 text-white px-4 py-2 rounded-lg font-bold shadow-lg"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= getPaginationUrl($i, $section, $itemsPerPage) ?>" 
                                           class="action-btn bg-white hover:bg-purple-50 border-2 border-gray-200 hover:border-purple-300 text-gray-700 hover:text-purple-600 px-4 py-2 rounded-lg transition-all duration-200 font-medium shadow-sm hover:shadow-md"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <!-- Last page -->
                                <?php if ($paginationEnd < $totalPages): ?>
                                    <?php if ($paginationEnd < $totalPages - 1): ?>
                                        <span class="text-gray-400 px-2 font-bold">...</span>
                                    <?php endif; ?>
                                    <a href="<?= getPaginationUrl($totalPages, $section, $itemsPerPage) ?>" 
                                       class="action-btn bg-white hover:bg-purple-50 border-2 border-gray-200 hover:border-purple-300 text-gray-700 hover:text-purple-600 px-4 py-2 rounded-lg transition-all duration-200 font-medium shadow-sm hover:shadow-md"><?= $totalPages ?></a>
                                <?php endif; ?>
                                
                                <!-- Next Button -->
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= getPaginationUrl($currentPage + 1, $section, $itemsPerPage) ?>" 
                                       class="action-btn bg-white hover:bg-purple-50 border-2 border-gray-200 hover:border-purple-300 text-gray-700 hover:text-purple-600 px-3 py-2 rounded-lg transition-all duration-200 flex items-center font-medium shadow-sm hover:shadow-md">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border-2 border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Items per page selector -->
                            <div class="flex items-center space-x-3 text-sm">
                                <span class="text-gray-600 font-medium">Show:</span>
                                <select onchange="changeItemsPerPage(this.value)" 
                                        class="bg-white border-2 border-gray-200 hover:border-purple-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 font-medium text-gray-700 cursor-pointer transition-all duration-200 shadow-sm">
                                    <option value="9" <?= $itemsPerPage == 9 ? 'selected' : '' ?>>9</option>
                                    <option value="18" <?= $itemsPerPage == 18 ? 'selected' : '' ?>>18</option>
                                    <option value="27" <?= $itemsPerPage == 27 ? 'selected' : '' ?>>27</option>
                                    <option value="60" <?= $itemsPerPage == 60 ? 'selected' : '' ?>>60</option>
                                </select>
                                <span class="text-gray-600 font-medium">per page</span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- NEW: Update Thread Modal -->
<div id="updateThreadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal-backdrop p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto modal-enter">
        <div class="sticky top-0 bg-white z-10 flex items-center justify-between p-6 border-b border-gray-200 rounded-t-2xl">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-orange-100 to-red-200 rounded-xl flex items-center justify-center mr-4">
                    <span class="material-icons text-orange-600 text-2xl">timeline</span>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Update Thread</h2>
                    <p class="text-sm text-gray-500">View all updates for this article</p>
                </div>
            </div>
            <button onclick="closeUpdateThread()" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2.5 rounded-lg transition-all duration-200">
                <span class="material-icons text-2xl">close</span>
            </button>
        </div>
        
        <div id="updateThreadContent" class="p-6">
            <!-- Content will be loaded dynamically -->
        </div>
    </div>
</div>

<!-- Advanced Filter Modal (Keep existing) -->
<div id="advancedFilterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal-backdrop p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto modal-enter">
        <div class="sticky top-0 bg-white z-10 flex items-center justify-between p-6 border-b border-gray-200 rounded-t-2xl">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mr-4">
                    <span class="material-icons text-purple-600 text-2xl">filter_alt</span>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Advanced Filter</h2>
                    <p class="text-sm text-gray-500">Filter articles by multiple criteria</p>
                </div>
            </div>
            <button onclick="closeAdvancedFilter()" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2.5 rounded-lg transition-all duration-200">
                <span class="material-icons text-2xl">close</span>
            </button>
        </div>
        
        <form id="advancedFilterForm" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Search Text -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">search</span>
                        Search Text
                    </label>
                    <input type="text" id="filterSearch" name="filter_search" value="<?= e($filterSearch) ?>"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                        placeholder="Search in title and content...">
                    <p class="text-xs text-gray-500 mt-2">Search for keywords in article title and content</p>
                </div>

                <!-- Category Filter -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">label</span>
                        Category
                    </label>
                    <select id="filterCategory" name="filter_category"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $filterCategory == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Department Filter (Admin Only) -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">business</span>
                        Department
                    </label>
                    <select id="filterDepartment" name="filter_department"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                        <option value="<?= $dept['id'] ?>" <?= $filterDepartment == $dept['id'] ? 'selected' : '' ?>>
                            <?= e($dept['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Author Filter -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">person</span>
                        Author
                    </label>
                    <select id="filterAuthor" name="filter_author"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200">
                        <option value="">All Authors</option>
                        <?php foreach ($authors as $author): ?>
                        <option value="<?= $author['id'] ?>" <?= $filterAuthor == $author['id'] ? 'selected' : '' ?>>
                            <?= e($author['username']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">event</span>
                        Date From
                    </label>
                    <input type="date" id="filterDateFrom" name="filter_date_from" value="<?= e($filterDateFrom) ?>"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200">
                </div>

                <!-- Date To -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                        <span class="material-icons text-purple-600 text-lg mr-2">event</span>
                        Date To
                    </label>
                    <input type="date" id="filterDateTo" name="filter_date_to" value="<?= e($filterDateTo) ?>"
                        class="w-full p-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200">
                </div>
            </div>

            <!-- Filter Actions Info -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 p-4 rounded-xl border border-purple-200">
                <div class="flex items-start">
                    <span class="material-icons text-blue-600 mr-3">info</span>
                    <div class="text-sm text-gray-700">
                        <p class="font-semibold mb-1">Filter Tips:</p>
                        <ul class="list-disc list-inside space-y-1 text-xs">
                            <li>Combine multiple filters to narrow down results</li>
                            <li>Leave a filter empty to include all options for that category</li>
                            <li>Date range filters work independently</li>
                            <li>Search text will look for matches in both title and content</li>
                        </ul>
                    </div>
                </div>
            </div>
        </form>
        
        <div class="flex justify-between gap-3 p-6 border-t border-gray-200 bg-gray-50 rounded-b-2xl">
            <button onclick="resetFilters()" class="action-btn px-6 py-3 text-red-600 bg-red-50 hover:bg-red-100 rounded-xl transition-all duration-200 font-medium flex items-center">
                <span class="material-icons text-lg mr-2">clear</span>
                Reset All
            </button>
            <div class="flex gap-3">
                <button onclick="closeAdvancedFilter()" class="action-btn px-6 py-3 text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-xl transition-all duration-200 font-medium">
                    Cancel
                </button>
                <button onclick="applyFilters()" class="action-btn px-8 py-3 text-white bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 rounded-xl transition-all duration-200 font-medium shadow-lg hover:shadow-xl flex items-center">
                    <span class="material-icons text-lg mr-2">filter_alt</span>
                    Apply Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Settings Modal (Keep existing) -->
<div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 modal-backdrop">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 modal-enter">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-gradient-to-br from-purple-100 to-purple-200 rounded-lg flex items-center justify-center mr-3">
                    <span class="material-icons text-purple-600">lock</span>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Change Password</h2>
            </div>
            <button onclick="closeSettingsModal()" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-lg transition-all duration-200">
                <span class="material-icons">close</span>
            </button>
        </div>
        
        <form id="changePasswordForm" class="p-6 space-y-5">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                <div class="relative">
                    <input type="password" id="currentPassword" required
                        class="w-full p-3 pl-11 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                        placeholder="Enter current password">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">lock_outline</span>
                </div>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                <div class="relative">
                    <input type="password" id="newPassword" required
                        class="w-full p-3 pl-11 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                        placeholder="Enter new password">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">vpn_key</span>
                </div>
                <p class="text-xs text-gray-500 mt-2 flex items-center">
                    <span class="material-icons text-xs mr-1">info</span>
                    Must be at least 8 characters
                </p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                <div class="relative">
                    <input type="password" id="confirmPassword" required
                        class="w-full p-3 pl-11 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all duration-200"
                        placeholder="Re-enter new password">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">check_circle_outline</span>
                </div>
            </div>
            <div id="errorMessage" class="hidden text-red-600 text-sm bg-red-50 p-4 rounded-xl border border-red-200 flex items-start">
                <span class="material-icons text-sm mr-2 mt-0.5">error</span>
                <span id="errorText"></span>
            </div>
            <div id="successMessage" class="hidden text-green-600 text-sm bg-green-50 p-4 rounded-xl border border-green-200 flex items-start">
                <span class="material-icons text-sm mr-2 mt-0.5">check_circle</span>
                <span id="successText"></span>
            </div>
        </form>
        
        <div class="flex justify-end gap-3 p-6 border-t border-gray-100">
            <button onclick="closeSettingsModal()" class="action-btn px-6 py-3 text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-xl transition-all duration-200 font-medium">
                Cancel
            </button>
            <button onclick="changePassword()" class="action-btn px-6 py-3 text-white bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 rounded-xl transition-all duration-200 font-medium shadow-lg hover:shadow-xl">
                Change Password
            </button>
        </div>
    </div>
</div>

<!-- Article Modals -->
<?php foreach ($paginatedNews as $article): 
    $status = getStatusBadge($article['is_pushed']);
?>
<div id="modal-<?= $article['id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto relative modal-enter shadow-2xl">
        <button class="sticky top-4 float-right mr-4 text-gray-500 hover:text-gray-800 bg-white hover:bg-gray-100 rounded-full p-2.5 transition-all duration-200 shadow-lg z-10" 
                onclick="closeModal('modal-<?= $article['id'] ?>')">
            <span class="material-icons">close</span>
        </button>
        
        <div class="p-6 lg:p-8">
            <!-- Thumbnail -->
            <?php if (!empty($article['thumbnail'])): ?>
            <div class="mb-6 rounded-2xl overflow-hidden shadow-lg">
                <img src="<?= getThumbnailUrl($article['thumbnail']) ?>" 
                     alt="<?= e($article['title']) ?>" 
                     class="w-full h-80 object-cover"
                     onerror="this.parentElement.style.display='none'"/>
            </div>
            <?php endif; ?>
            
            <div class="mb-6 pb-6 border-b border-gray-200">
                <div class="flex items-center gap-2 mb-4">
                    <span class="bg-gradient-to-r from-purple-50 to-purple-100 text-purple-700 px-4 py-2 rounded-full text-sm font-semibold">
                        <?= e($article['category_name'] ?: 'Uncategorized') ?>
                    </span>
                    <span class="<?= $status['class'] ?> px-4 py-2 rounded-full text-sm font-semibold">
                        <?= $status['label'] ?>
                    </span>
                </div>
                <h2 class="text-3xl lg:text-4xl font-bold text-gray-800 leading-tight"><?= e($article['title']) ?></h2>
            </div>
            
            <div class="flex flex-wrap gap-6 mb-8 text-sm text-gray-600">
                <div class="flex items-center bg-gray-50 px-4 py-2 rounded-lg">
                    <span class="material-icons text-lg mr-2 text-purple-600">person</span>
                    <div>
                        <p class="font-semibold text-gray-800"><?= e($article['username'] ?: 'Unknown') ?></p>
                        <p class="text-xs text-gray-500">Author</p>
                    </div>
                </div>
                <div class="flex items-center bg-gray-50 px-4 py-2 rounded-lg">
                    <span class="material-icons text-lg mr-2 text-blue-600">business</span>
                    <div>
                        <p class="font-semibold text-gray-800"><?= e($article['dept_name'] ?: 'N/A') ?></p>
                        <p class="text-xs text-gray-500">Department</p>
                    </div>
                </div>
                <div class="flex items-center bg-gray-50 px-4 py-2 rounded-lg">
                    <span class="material-icons text-lg mr-2 text-green-600">schedule</span>
                    <div>
                        <p class="font-semibold text-gray-800"><?= date('M d, Y • g:i A', strtotime($article['created_at'])) ?></p>
                        <p class="text-xs text-gray-500">Published</p>
                    </div>
                </div>
            </div>
            
            <div class="prose max-w-none text-gray-700 leading-relaxed text-base mb-8">
                <?= nl2br(e($article['content'])) ?>
            </div>
            
            <div class="pt-6 border-t border-gray-200 flex flex-col sm:flex-row gap-3 sm:justify-end">
                <?php if ($article['is_update'] == 0 && $article['update_count'] > 0): ?>
                <button onclick="closeModal('modal-<?= $article['id'] ?>'); viewUpdateThread(<?= $article['id'] ?>);"
                        class="action-btn bg-gradient-to-r from-orange-600 to-red-700 hover:from-orange-700 hover:to-red-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center justify-center font-medium shadow-lg hover:shadow-xl">
                    <span class="material-icons text-lg mr-2">timeline</span>
                    View All Updates (<?= $article['update_count'] ?>)
                </button>
                <?php endif; ?>
                
                <button onclick="window.open('others/print_headline.php?id=<?= $article['id'] ?>', '_blank');"
                        class="action-btn bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center justify-center font-medium shadow-lg hover:shadow-xl">
                    <span class="material-icons text-lg mr-2">print</span>
                    Print Article
                </button>

                <button onclick="window.open('attachment/view_attachment.php?id=<?= $article['id'] ?>', '_blank');"
                        class="action-btn bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center justify-center font-medium shadow-lg hover:shadow-xl">
                    <span class="material-icons text-lg mr-2">visibility</span>
                    View Attachments
                </button>

                <button onclick="window.open('attachment/get_attachment.php?id=<?= $article['id'] ?>', '_blank');"
                        class="action-btn bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center justify-center font-medium shadow-lg hover:shadow-xl">
                    <span class="material-icons text-lg mr-2">download</span>
                    Download Files
                </button>

                <button onclick="closeModal('modal-<?= $article['id'] ?>')"
                        class="action-btn bg-gray-100 hover:bg-gray-200 text-gray-800 px-6 py-3 rounded-xl transition-all duration-200 font-medium">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Keep all existing JavaScript functions and add new ones for update threading

// NEW: View Update Thread Function
function viewUpdateThread(parentId) {
    showLoadingOverlay();
    
    // Open modal
    const modal = document.getElementById('updateThreadModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Fetch updates via AJAX
    fetch(`ajax/get_updates.php?parent_id=${parentId}`)
        .then(response => response.json())
        .then(data => {
            hideLoadingOverlay();
            
            if (data.success) {
                renderUpdateThread(data.parent, data.updates);
            } else {
                showNotification('Failed to load updates', 'error');
                closeUpdateThread();
            }
        })
        .catch(error => {
            hideLoadingOverlay();
            console.error('Error:', error);
            showNotification('An error occurred while loading updates', 'error');
            closeUpdateThread();
        });
}

// NEW: Render Update Thread
function renderUpdateThread(parent, updates) {
    const content = document.getElementById('updateThreadContent');
    
    let html = `
        <!-- Parent Article -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-2xl p-6 mb-6 border-2 border-blue-200 shadow-sm">
            <div class="flex items-center mb-4">
                <span class="material-icons text-blue-600 text-3xl mr-3">article</span>
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-gray-800">${escapeHtml(parent.title)}</h3>
                    <p class="text-sm text-gray-600 mt-1">
                        <span class="material-icons text-xs align-middle">person</span> ${escapeHtml(parent.username)} • 
                        <span class="material-icons text-xs align-middle">schedule</span> ${formatDateTime(parent.created_at)}
                    </p>
                </div>
                <button onclick="openModal('modal-${parent.id}')" class="action-btn bg-blue-100 hover:bg-blue-200 text-blue-700 px-4 py-2 rounded-lg transition-all">
                    <span class="material-icons text-sm mr-1">visibility</span>
                    View Full
                </button>
            </div>
            <p class="text-gray-700 leading-relaxed">${escapeHtml(parent.content).substring(0, 300)}${parent.content.length > 300 ? '...' : ''}</p>
        </div>

        <!-- Updates Section -->
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <span class="material-icons text-orange-600 mr-2">timeline</span>
                Updates (${updates.length})
            </h3>
            <span class="text-sm text-gray-500">Showing from earliest to latest</span>
        </div>
    `;
    
    if (updates.length === 0) {
        html += `
            <div class="text-center py-12 bg-gray-50 rounded-xl">
                <span class="material-icons text-6xl text-gray-300 mb-3">update</span>
                <p class="text-gray-500">No updates yet for this article</p>
            </div>
        `;
    } else {
        html += '<div class="update-timeline">';
        
        updates.forEach((update, index) => {
            html += `
                <div class="update-item bg-white rounded-xl p-5 shadow-sm border border-gray-200 hover:shadow-md transition-all">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center">
                            <span class="bg-gradient-to-r from-orange-100 to-red-100 text-orange-700 px-3 py-1 rounded-full text-xs font-bold mr-2">
                                ${escapeHtml(update.update_type || 'Update')} #${update.update_number}
                            </span>
                            <span class="text-xs text-gray-500">
                                <span class="material-icons text-xs align-middle">schedule</span>
                                ${formatDateTime(update.created_at)}
                            </span>
                        </div>
                        <button onclick="openModal('modal-${update.id}')" class="action-btn text-blue-600 hover:text-blue-700 text-sm font-medium">
                            View Details →
                        </button>
                    </div>
                    
                    <h4 class="font-bold text-gray-800 mb-2 text-lg">${escapeHtml(update.title)}</h4>
                    <p class="text-gray-600 leading-relaxed mb-3">${escapeHtml(update.content).substring(0, 200)}${update.content.length > 200 ? '...' : ''}</p>
                    
                    <div class="flex items-center gap-4 text-xs text-gray-500 pt-3 border-t border-gray-100">
                        <span class="flex items-center">
                            <span class="material-icons text-sm mr-1">person</span>
                            ${escapeHtml(update.username)}
                        </span>
                        <span class="flex items-center">
                            <span class="material-icons text-sm mr-1">business</span>
                            ${escapeHtml(update.dept_name)}
                        </span>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
    }
    
    // Add action buttons
    html += `
        <div class="mt-8 flex gap-3 justify-end">
            <button onclick="window.location.href='function/add_update.php?parent_id=${parent.id}'" 
                    class="action-btn bg-gradient-to-r from-orange-600 to-red-700 hover:from-orange-700 hover:to-red-800 text-white px-6 py-3 rounded-xl transition-all duration-200 flex items-center font-medium shadow-lg hover:shadow-xl">
                <span class="material-icons text-lg mr-2">add_circle</span>
                Add New Update
            </button>
            <button onclick="closeUpdateThread()" 
                    class="action-btn bg-gray-100 hover:bg-gray-200 text-gray-800 px-6 py-3 rounded-xl transition-all duration-200 font-medium">
                Close
            </button>
        </div>
    `;
    
    content.innerHTML = html;
}

// NEW: Close Update Thread Modal
function closeUpdateThread() {
    const modal = document.getElementById('updateThreadModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Helper function to format date/time
function formatDateTime(dateString) {
    const date = new Date(dateString);
    const options = { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    };
    return date.toLocaleString('en-US', options);
}

// Enhanced Notification System
function showNotification(message, type = 'info', duration = 3000) {
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    
    const colors = {
        success: 'from-green-500 to-green-600',
        error: 'from-red-500 to-red-600',
        warning: 'from-yellow-500 to-yellow-600',
        info: 'from-blue-500 to-blue-600'
    };
    
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-xl shadow-2xl transform translate-x-full transition-all duration-300 bg-gradient-to-r ${colors[type]} text-white max-w-md`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <span class="material-icons mr-3 text-2xl">${icons[type]}</span>
            <span class="font-medium">${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.remove('translate-x-full'), 100);
    
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => notification.remove(), 300);
    }, duration);
}

// Advanced Filter Functions
function openAdvancedFilter() {
    const modal = document.getElementById('advancedFilterModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAdvancedFilter() {
    const modal = document.getElementById('advancedFilterModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function applyFilters() {
    showLoadingOverlay();
    const form = document.getElementById('advancedFilterForm');
    const params = new URLSearchParams(window.location.search);
    
    const filterSearch = document.getElementById('filterSearch').value;
    const filterCategory = document.getElementById('filterCategory').value;
    const filterDepartment = document.getElementById('filterDepartment')?.value || '';
    const filterAuthor = document.getElementById('filterAuthor').value;
    const filterDateFrom = document.getElementById('filterDateFrom').value;
    const filterDateTo = document.getElementById('filterDateTo').value;
    
    const newParams = new URLSearchParams();
    
    if (params.has('section')) {
        newParams.set('section', params.get('section'));
    }
    if (params.has('view')) {
        newParams.set('view', params.get('view'));
    }
    
    if (filterSearch) newParams.set('filter_search', filterSearch);
    if (filterCategory) newParams.set('filter_category', filterCategory);
    if (filterDepartment) newParams.set('filter_department', filterDepartment);
    if (filterAuthor) newParams.set('filter_author', filterAuthor);
    if (filterDateFrom) newParams.set('filter_date_from', filterDateFrom);
    if (filterDateTo) newParams.set('filter_date_to', filterDateTo);
    
    newParams.set('page', '1');
    
    window.location.search = newParams.toString();
}

function resetFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterCategory').value = '';
    if (document.getElementById('filterDepartment')) {
        document.getElementById('filterDepartment').value = '';
    }
    document.getElementById('filterAuthor').value = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value = '';
    
    showNotification('Filters reset', 'info');
}

function clearAllFilters() {
    showLoadingOverlay();
    const params = new URLSearchParams(window.location.search);
    const newParams = new URLSearchParams();
    
    if (params.has('section')) {
        newParams.set('section', params.get('section'));
    }
    if (params.has('view')) {
        newParams.set('view', params.get('view'));
    }
    
    window.location.search = newParams.toString();
}

function removeFilter(paramName) {
    showLoadingOverlay();
    const params = new URLSearchParams(window.location.search);
    params.delete(paramName);
    params.set('page', '1');
    window.location.search = params.toString();
}

document.getElementById('advancedFilterBtn')?.addEventListener('click', openAdvancedFilter);
document.getElementById('advancedFilterBtnMobile')?.addEventListener('click', openAdvancedFilter);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAdvancedFilter();
        closeUpdateThread();
    }
});

document.getElementById('advancedFilterModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeAdvancedFilter();
    }
});

document.getElementById('updateThreadModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeUpdateThread();
    }
});

// Settings Modal Functions
function openSettingsModal() {
    const modal = document.getElementById('settingsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.getElementById('changePasswordForm').reset();
    document.getElementById('errorMessage').classList.add('hidden');
    document.getElementById('successMessage').classList.add('hidden');
}

function closeSettingsModal() {
    const modal = document.getElementById('settingsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function changePassword() {
    const currentPassword = document.getElementById('currentPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    const errorMsg = document.getElementById('errorMessage');
    const errorText = document.getElementById('errorText');
    const successMsg = document.getElementById('successMessage');
    const successText = document.getElementById('successText');
    
    errorMsg.classList.add('hidden');
    successMsg.classList.add('hidden');
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        errorText.textContent = 'Please fill in all fields';
        errorMsg.classList.remove('hidden');
        return;
    }
    
    if (newPassword.length < 8) {
        errorText.textContent = 'New password must be at least 8 characters';
        errorMsg.classList.remove('hidden');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        errorText.textContent = 'New passwords do not match';
        errorMsg.classList.remove('hidden');
        return;
    }
    
    showLoadingOverlay();
    
    fetch('change_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        hideLoadingOverlay();
        if (data.success) {
            successText.textContent = 'Password changed successfully!';
            successMsg.classList.remove('hidden');
            showNotification('Password changed successfully!', 'success');
            setTimeout(() => closeSettingsModal(), 2000);
        } else {
            errorText.textContent = data.message || 'Failed to change password';
            errorMsg.classList.remove('hidden');
            showNotification(data.message || 'Failed to change password', 'error');
        }
    })
    .catch(error => {
        hideLoadingOverlay();
        console.error('Error:', error);
        errorText.textContent = 'An error occurred. Please try again.';
        errorMsg.classList.remove('hidden');
        showNotification('An error occurred. Please try again.', 'error');
    });
}

document.getElementById('settingsModal')?.addEventListener('click', function(e) {
    if (e.target === this) closeSettingsModal();
});

// Article Modal Functions
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('[id^="modal-"]');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                closeModal(modal.id);
            }
        });
        if (!document.getElementById('settingsModal').classList.contains('hidden')) {
            closeSettingsModal();
        }
        if (!document.getElementById('advancedFilterModal').classList.contains('hidden')) {
            closeAdvancedFilter();
        }
        if (!document.getElementById('updateThreadModal').classList.contains('hidden')) {
            closeUpdateThread();
        }
    }
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeModal(e.target.id);
    }
});

// Sidebar Functions
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    sidebarOverlay.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
    document.body.style.overflow = '';
}

sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

document.addEventListener('click', (e) => {
    if (window.innerWidth < 1024 && 
        !sidebar.contains(e.target) && 
        !sidebarToggle?.contains(e.target) &&
        !sidebar.classList.contains('-translate-x-full')) {
        closeSidebar();
    }
});

// Refresh Articles
function refreshArticles() {
    const refreshBtn = document.getElementById('refreshArticles');
    const articlesGrid = document.getElementById('articlesGrid');
    const refreshIcon = refreshBtn.querySelector('.material-icons');
    
    refreshBtn.disabled = true;
    refreshBtn.classList.add('opacity-50', 'cursor-not-allowed');
    refreshIcon.classList.add('animate-spin');
    
    if (articlesGrid) {
        articlesGrid.style.opacity = '0.5';
        articlesGrid.style.pointerEvents = 'none';
    }
    
    showNotification('Refreshing articles...', 'info', 1000);
    
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

document.getElementById('refreshArticles')?.addEventListener('click', refreshArticles);

// Enhanced Dropdown Menu
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const container = this.closest('.dropdown-container');
            const menu = container.querySelector('.dropdown-menu');
            const arrow = container.querySelector('.dropdown-arrow');
            
            document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                if (otherMenu !== menu && otherMenu.classList.contains('show')) {
                    otherMenu.classList.remove('show');
                    otherMenu.classList.add('hidden');
                }
            });
            
            document.querySelectorAll('.dropdown-arrow').forEach(otherArrow => {
                if (otherArrow !== arrow) {
                    otherArrow.style.transform = 'rotate(0deg)';
                }
            });
            
            if (menu.classList.contains('hidden')) {
                menu.classList.remove('hidden');
                setTimeout(() => menu.classList.add('show'), 10);
                arrow.style.transform = 'rotate(180deg)';
            } else {
                menu.classList.remove('show');
                arrow.style.transform = 'rotate(0deg)';
                setTimeout(() => menu.classList.add('hidden'), 300);
            }
        });
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown-container')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.classList.remove('show');
                setTimeout(() => menu.classList.add('hidden'), 300);
            });
            document.querySelectorAll('.dropdown-arrow').forEach(arrow => {
                arrow.style.transform = 'rotate(0deg)';
            });
        }
    });
});

// Pagination Functions
function changeItemsPerPage(value) {
    showLoadingOverlay();
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', value);
    urlParams.set('page', '1');
    window.location.search = urlParams.toString();
}

// Loading Overlay
function showLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.add('show');
}

function hideLoadingOverlay() {
    const overlay = document.getElementById('loadingOverlay');
    overlay.classList.remove('show');
}

document.addEventListener('DOMContentLoaded', function() {
    const paginationLinks = document.querySelectorAll('a[href*="page="]');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            showLoadingOverlay();
        });
    });
});

// Enhanced Search Functionality
function setupSearch(inputId) {
    const searchInput = document.getElementById(inputId);
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        
        const searchTerm = e.target.value.toLowerCase().trim();
        const newsCards = document.querySelectorAll('.news-card');
        
        searchTimeout = setTimeout(() => {
            let visibleCount = 0;
            
            newsCards.forEach(card => {
                const title = card.querySelector('h4').textContent.toLowerCase();
                const content = card.querySelector('.truncate-text').textContent.toLowerCase();
                const category = card.querySelector('.line-clamp-2')?.textContent.toLowerCase() || '';
                
                const isMatch = !searchTerm || 
                                title.includes(searchTerm) || 
                                content.includes(searchTerm) ||
                                category.includes(searchTerm);
                
                if (isMatch) {
                    card.style.display = '';
                    card.style.animation = 'fadeIn 0.3s ease-in-out';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            const articlesGrid = document.getElementById('articlesGrid');
            let noResultsMsg = document.getElementById('noSearchResults');
            
            if (visibleCount === 0 && searchTerm) {
                if (!noResultsMsg) {
                    noResultsMsg = document.createElement('div');
                    noResultsMsg.id = 'noSearchResults';
                    noResultsMsg.className = 'col-span-full text-center py-12';
                    noResultsMsg.innerHTML = `
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="material-icons text-6xl text-gray-400">search_off</span>
                        </div>
                        <h4 class="text-xl font-bold text-gray-700 mb-2">No Results Found</h4>
                        <p class="text-gray-500">Try adjusting your search terms</p>
                    `;
                    articlesGrid.appendChild(noResultsMsg);
                }
            } else if (noResultsMsg) {
                noResultsMsg.remove();
            }
        }, 300);
    });
}

setupSearch('searchInputMobile');

// Confirm Delete
function confirmDelete(id) {
    if (confirm('Are you sure you want to delete this article? This action cannot be undone.')) {
        showLoadingOverlay();
        window.location.href = `function/delete.php?id=${id}`;
    }
}

// Confirm Revert
function confirmRevert(id, to) {
    const messages = {
        0: 'Revert this article to Regular News?',
        1: 'Revert this article to Edited News?'
    };
    
    if (confirm(messages[to])) {
        showLoadingOverlay();
        window.location.href = `function/revert.php?id=${id}&to=${to}`;
    }
}

window.addEventListener('load', function() {
    hideLoadingOverlay();
});

// Lazy loading images
document.addEventListener('DOMContentLoaded', function() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src || img.src;
                    img.classList.add('loaded');
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('.thumbnail-image').forEach(img => {
            imageObserver.observe(img);
        });
    }
});

// Smooth scroll to top button
const scrollToTopBtn = document.createElement('button');
scrollToTopBtn.innerHTML = '<span class="material-icons">arrow_upward</span>';
scrollToTopBtn.className = 'fixed bottom-6 right-6 bg-gradient-to-r from-purple-600 to-purple-700 text-white w-12 h-12 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center justify-center z-40 opacity-0 pointer-events-none';
scrollToTopBtn.id = 'scrollToTop';
document.body.appendChild(scrollToTopBtn);

const mainContent = document.querySelector('.main-content > div');
mainContent?.addEventListener('scroll', function() {
    if (this.scrollTop > 300) {
        scrollToTopBtn.style.opacity = '1';
        scrollToTopBtn.style.pointerEvents = 'all';
    } else {
        scrollToTopBtn.style.opacity = '0';
        scrollToTopBtn.style.pointerEvents = 'none';
    }
});

scrollToTopBtn.addEventListener('click', function() {
    mainContent?.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
});
</script>

</body>
</html>