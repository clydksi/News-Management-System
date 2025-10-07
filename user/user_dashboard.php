<?php
require '../auth.php';
require '../db.php';

// Dashboard statistics
$totalArticles = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Pagination settings
$itemsPerPage = isset($_GET['per_page']) ? max(6, min(50, intval($_GET['per_page']))) : 6;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get section filter
$section = isset($_GET['section']) ? $_GET['section'] : 'all';

// Base query
$baseQuery = "SELECT n.*, 
                     u.username, 
                     d.name AS dept_name, 
                     c.name AS category_name
              FROM news n
              JOIN users u ON n.created_by = u.id
              JOIN departments d ON n.department_id = d.id
              LEFT JOIN categories c ON n.category_id = c.id";

$countQuery = "SELECT COUNT(*) as total
               FROM news n
               JOIN users u ON n.created_by = u.id
               JOIN departments d ON n.department_id = d.id";

$params = [];

// Add department filter for non-admin users
if ($_SESSION['role'] !== 'admin') {
    $baseQuery .= " WHERE n.department_id = ?";
    $countQuery .= " WHERE n.department_id = ?";
    $params[] = $_SESSION['department_id'];
}

// Add section filter
switch($section) {
    case 'regular':
        $sectionFilter = ($_SESSION['role'] !== 'admin' ? " AND" : " WHERE") . " n.is_pushed = 0";
        break;
    case 'edited':
        $sectionFilter = ($_SESSION['role'] !== 'admin' ? " AND" : " WHERE") . " n.is_pushed = 1";
        break;
    case 'headline':
        $sectionFilter = ($_SESSION['role'] !== 'admin' ? " AND" : " WHERE") . " n.is_pushed = 2";
        break;
    default:
        $sectionFilter = "";
}

$baseQuery .= $sectionFilter;
$countQuery .= $sectionFilter;

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

// Get paginated results - LIMIT and OFFSET must be integers, not bound parameters in some PDO drivers
// Using direct integer casting for safety since we've already validated these values
$paginatedQuery = $baseQuery . " ORDER BY n.created_at DESC LIMIT " . intval($itemsPerPage) . " OFFSET " . intval($offset);

if (!empty($params)) {
    $stmt = $pdo->prepare($paginatedQuery);
    $stmt->execute($params);
} else {
    $stmt = $pdo->query($paginatedQuery);
}
$paginatedNews = $stmt->fetchAll();

// Get counts for each section using efficient queries
$regularCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 0";
$editedCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 1";
$headlineCountQuery = "SELECT COUNT(*) FROM news n WHERE n.is_pushed = 2";

if ($_SESSION['role'] !== 'admin') {
    $deptFilter = " AND n.department_id = ?";
    $regularCountQuery .= $deptFilter;
    $editedCountQuery .= $deptFilter;
    $headlineCountQuery .= $deptFilter;
    
    $regularCount = $pdo->prepare($regularCountQuery);
    $regularCount->execute([$_SESSION['department_id']]);
    $regularNewsCount = $regularCount->fetchColumn();
    
    $editedCount = $pdo->prepare($editedCountQuery);
    $editedCount->execute([$_SESSION['department_id']]);
    $editedNewsCount = $editedCount->fetchColumn();
    
    $headlineCount = $pdo->prepare($headlineCountQuery);
    $headlineCount->execute([$_SESSION['department_id']]);
    $headlineNewsCount = $headlineCount->fetchColumn();
} else {
    $regularNewsCount = $pdo->query($regularCountQuery)->fetchColumn();
    $editedNewsCount = $pdo->query($editedCountQuery)->fetchColumn();
    $headlineNewsCount = $pdo->query($headlineCountQuery)->fetchColumn();
}

// Calculate pagination range
$maxPaginationLinks = 5;
$paginationStart = max(1, $currentPage - floor($maxPaginationLinks / 2));
$paginationEnd = min($totalPages, $paginationStart + $maxPaginationLinks - 1);

// Adjust start if we're near the end
if ($paginationEnd - $paginationStart < $maxPaginationLinks - 1) {
    $paginationStart = max(1, $paginationEnd - $maxPaginationLinks + 1);
}

// Function to generate pagination URL
function getPaginationUrl($page, $section = null, $perPage = null) {
    $params = ['page' => $page];
    if ($section && $section !== 'all') {
        $params['section'] = $section;
    }
    if ($perPage) {
        $params['per_page'] = $perPage;
    }
    return '?' . http_build_query($params);
}

// Function to safely output HTML
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>News User Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
    html {scroll-behavior: smooth;}
    body { font-family: 'Poppins', sans-serif; background-color: #F0F2F5; }
    .sidebar { background-color: #6D28D9; transition: transform 0.3s ease-in-out; }
    .main-content { background-color: #EDE9FE; }
    .sidebar-hidden { transform: translateX(-100%); }
    @media (min-width: 1024px) {
        .sidebar-hidden { transform: translateX(0); }
    }
    .modal-backdrop { backdrop-filter: blur(4px); }
    .modal-enter { animation: modalEnter 0.3s ease-out; }
    @keyframes modalEnter {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    .news-card:hover { transform: translateY(-2px); }
    .truncate-text { 
        display: -webkit-box; 
        -webkit-line-clamp: 3; 
        -webkit-box-orient: vertical; 
        overflow: hidden; 
    }
</style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

<!-- Mobile Header -->
<header class="lg:hidden bg-purple-600 text-white p-4 flex items-center justify-between">
    <button id="sidebarToggle" class="text-white">
        <span class="material-icons text-2xl">menu</span>
    </button>
    <div class="flex items-center">
        <span class="material-icons text-2xl mr-2">feed</span>
        <h1 class="text-lg font-bold">News User</h1>
    </div>
    <div class="flex items-center space-x-2">
        <button class="text-white">
            <span class="material-icons">notifications</span>
        </button>
        <div class="w-8 h-8 bg-purple-400 rounded-full flex items-center justify-center text-white font-bold text-sm">
            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
        </div>
    </div>
</header>

<div class="flex flex-1 relative overflow-hidden">
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed lg:relative w-64 h-full text-white flex flex-col p-4 z-30 lg:translate-x-0 -translate-x-full transition-transform duration-300">
        <div class="flex items-center mb-6 lg:mb-10">
            <span class="material-icons text-2xl lg:text-3xl mr-2">feed</span>
            <h1 class="text-xl lg:text-2xl font-bold">News User</h1>
            <button id="sidebarClose" class="ml-auto lg:hidden text-white">
                <span class="material-icons">close</span>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto">
            <ul class="space-y-2">
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors <?= $section === 'all' ? 'bg-purple-700' : '' ?>" 
                       href="user_dashboard.php">
                        <span class="material-icons mr-3 text-xl">dashboard</span> 
                        All News
                        <span class="ml-auto bg-purple-500 px-2 py-1 rounded-full text-xs"><?= $totalArticles ?></span>
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors <?= $section === 'regular' ? 'bg-purple-700' : '' ?>" 
                       href="?section=regular">
                        <span class="material-icons mr-3 text-xl">add_to_queue</span> 
                        Regular News
                        <span class="ml-auto bg-purple-500 px-2 py-1 rounded-full text-xs"><?= $regularNewsCount ?></span>
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors <?= $section === 'edited' ? 'bg-purple-700' : '' ?>" 
                       href="?section=edited">
                        <span class="material-icons mr-3 text-xl">article</span> 
                        Edited News
                        <span class="ml-auto bg-purple-500 px-2 py-1 rounded-full text-xs"><?= $editedNewsCount ?></span>
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors <?= $section === 'headline' ? 'bg-purple-700' : '' ?>" 
                       href="?section=headline">
                        <span class="material-icons mr-3 text-xl">art_track</span> 
                        Headlines News
                        <span class="ml-auto bg-purple-500 px-2 py-1 rounded-full text-xs"><?= $headlineNewsCount ?></span>
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" 
                       href="https://project.mbcradio.net/saas/chat.php">
                        <span class="material-icons mr-3 text-xl">people</span> Chat Community
                    </a>
                </li>
            </ul>
        </nav>
        <div class="border-t border-purple-500 pt-4 mt-4">
            <ul class="space-y-2">
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="#">
                        <span class="material-icons mr-3 text-xl">settings</span> Settings
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="../logout.php">
                        <span class="material-icons mr-3 text-xl">logout</span> Logout
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content flex-1 flex flex-col overflow-hidden">
        <!-- Desktop Header -->
        <header class="hidden lg:flex justify-between items-center p-6 lg:p-8">
            <div class="flex items-center">
                <span class="material-icons text-3xl lg:text-4xl text-purple-600 mr-3">article</span>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-800">Articles</h2>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <input class="bg-white rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-64" 
                           placeholder="Search everything..." type="text" id="searchInput"/>
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                </div>
                <button class="text-gray-500 hover:text-purple-600">
                    <span class="material-icons">notifications</span>
                </button>
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-2">
                        <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800"><?= e($_SESSION['username'] ?? 'Username') ?></p>
                        <p class="text-sm text-gray-500"><?= ucfirst($_SESSION['role'] ?? 'User') ?></p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Search Bar -->
        <div class="lg:hidden p-4 bg-white border-b">
            <div class="relative">
                <input class="bg-gray-100 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full" 
                       placeholder="Search everything..." type="text"/>
                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <!-- Dashboard cards -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-purple-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-purple-600">article</span>
                    </div>
                    <div class="text-center lg:text-left">
                         <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $totalArticles ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Articles</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-green-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-green-600">group</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $activeUsers ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Active Users</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-orange-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-orange-600">comment</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-lg lg:text-3xl font-bold text-gray-800"><?= $totalCategories ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Categories</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-red-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-red-600">pending_actions</span>
                    </div>
                    <div class="text-center lg:text-left">
                         <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $pendingReviews ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Dept.</p>
                    </div>
                </div>
            </section>

            <!-- News Articles Section -->
            <section class="mb-6 lg:mb-8">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-4 lg:mb-6">
                    <div class="flex items-center mb-3 lg:mb-0">
                        <h3 class="text-xl lg:text-2xl font-bold text-gray-800 mr-4">
                            <?php
                            switch($section) {
                                case 'regular': echo 'Regular News Articles'; break;
                                case 'edited': echo 'Edited News Articles'; break;
                                case 'headline': echo 'Headline News Articles'; break;
                                default: echo 'Latest News Articles';
                            }
                            ?>
                        </h3>
                        <button id="refreshArticles" class="bg-gray-100 hover:bg-gray-200 text-gray-600 p-2 rounded-lg transition-all duration-200 flex items-center justify-center group" title="Refresh Articles">
                            <span class="material-icons text-lg group-hover:rotate-180 transition-transform duration-500">refresh</span>
                        </button>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <button onclick="window.location.href='create.php';"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                            <span class="material-icons text-sm mr-2">add</span>
                            Add Article
                        </button>

                        <button onclick="window.location.href='category.php';"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                            <span class="material-icons text-sm mr-2">add</span>
                            Add Category
                        </button>
                    </div>
                </div>

                <?php if (empty($paginatedNews)): ?>
                    <div class="bg-white rounded-xl lg:rounded-2xl shadow-md p-8 text-center">
                        <span class="material-icons text-6xl text-gray-300 mb-4">article</span>
                        <h4 class="text-xl font-semibold text-gray-600 mb-2">No Articles Found</h4>
                        <p class="text-gray-500">
                            <?php if ($section !== 'all'): ?>
                                No articles in this section yet.
                            <?php else: ?>
                                Start creating your first news article to see it here.
                            <?php endif; ?>
                        </p>
                        <button onclick="window.location.href='create.php';"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg mt-4 transition-colors">
                            Create Article
                        </button>
                    </div>
                <?php else: ?>
                    <div id="articlesGrid" class="grid grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-6 transition-opacity duration-300">
                        <?php foreach ($paginatedNews as $article): ?>
                            <div class="news-card bg-white rounded-xl lg:rounded-2xl shadow-md hover:shadow-lg transition-all duration-300 overflow-hidden border-l-4 border-purple-500">
                                <div class="p-4 lg:p-6">
                                    <!-- Header Section -->
                                    <div class="flex items-start justify-between mb-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-purple-100 p-2 rounded-lg">
                                                <span class="material-icons text-purple-600">article</span>
                                            </div>
                                            <div>
                                                <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-full text-xs font-medium">
                                                    <?= e($article['category_name'] ?: 'Uncategorized') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php 
                                            $statusLabels = [
                                                0 => ['label' => 'Regular', 'class' => 'bg-yellow-100 text-yellow-800'],
                                                1 => ['label' => 'Edited', 'class' => 'bg-green-100 text-green-800'],
                                                2 => ['label' => 'Headline', 'class' => 'bg-blue-100 text-blue-800']
                                            ];
                                            $status = $statusLabels[$article['is_pushed']] ?? $statusLabels[0];
                                            ?>
                                            <span class="<?= $status['class'] ?> px-3 py-1 rounded-full text-xs font-medium">
                                                <?= $status['label'] ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Title -->
                                    <h4 class="font-bold text-gray-800 mb-3 text-xl leading-tight hover:text-purple-600 transition-colors cursor-pointer"
                                        onclick="openModal('modal-<?= $article['id'] ?>')">
                                        <?= e($article['title']) ?>
                                    </h4>
                                    
                                    <!-- Content Preview -->
                                    <p class="text-gray-600 leading-relaxed truncate-text mb-4 text-sm">
                                        <?= e(strip_tags($article['content'])) ?>
                                    </p>
                                    
                                    <!-- Metadata -->
                                    <div class="grid grid-cols-2 gap-4 py-3 border-t border-gray-100 mb-4">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <span class="material-icons text-sm mr-2">person</span>
                                            <div>
                                                <p class="font-medium text-gray-700"><?= e($article['username'] ?: 'Unknown') ?></p>
                                                <p class="text-xs">Author</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <span class="material-icons text-sm mr-2">business</span>
                                            <div>
                                                <p class="font-medium text-gray-700"><?= e($article['dept_name'] ?: 'N/A') ?></p>
                                                <p class="text-xs">Department</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Date -->
                                    <div class="flex items-center text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-lg mb-4">
                                        <span class="material-icons text-sm mr-1">schedule</span>
                                        <?= date('M d, Y • g:i A', strtotime($article['created_at'])) ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-2">
                                        <button 
                                            class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                            title="View Article"
                                            onclick="openModal('modal-<?= $article['id'] ?>')">
                                            <span class="material-icons text-sm mr-1">visibility</span>
                                            View
                                        </button>

                                        <button onclick="window.location.href='update.php?id=<?= $article['id'] ?>';"
                                            class="bg-green-50 hover:bg-green-100 text-green-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                            title="Edit Article">
                                            <span class="material-icons text-sm mr-1">edit</span>
                                            Edit
                                        </button>

                                        <button onclick="if(confirm('Are you sure you want to delete this article?')) window.location.href='delete.php?id=<?= $article['id'] ?>';"
                                            class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                            title="Delete Article">
                                            <span class="material-icons text-sm mr-1">delete</span>
                                            Delete
                                        </button>

                                        <button onclick="window.open('print_headline.php?id=<?= $article['id'] ?>', '_blank');"
                                            class="bg-violet-50 hover:bg-violet-100 text-violet-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                            title="Print Article">
                                            <span class="material-icons text-sm mr-1">print</span>
                                            Print
                                        </button>

                                        <?php if ($article['is_pushed'] == 0): ?>
                                            <button onclick="if(confirm('Push this article to Edited News?')) window.location.href='push.php?id=<?= $article['id'] ?>&to=1';"
                                                class="bg-amber-50 hover:bg-amber-100 text-amber-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm col-span-2" 
                                                title="Push to Edited">
                                                <span class="material-icons text-sm mr-1">rocket_launch</span>
                                                Push to Edited
                                            </button>
                                        <?php elseif ($article['is_pushed'] == 1): ?>
                                            <button onclick="if(confirm('Push this article to Headlines?')) window.location.href='push.php?id=<?= $article['id'] ?>&to=2';"
                                                class="bg-indigo-50 hover:bg-indigo-100 text-indigo-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                                title="Push to Headlines">
                                                <span class="material-icons text-sm mr-1">star</span>
                                                Push to Headlines
                                            </button>
                                            <button onclick="if(confirm('Revert this article to Regular News?')) window.location.href='revert.php?id=<?= $article['id'] ?>&to=0';"
                                                class="bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm" 
                                                title="Revert to Regular">
                                                <span class="material-icons text-sm mr-1">undo</span>
                                                Revert to Regular
                                            </button>
                                        <?php elseif ($article['is_pushed'] == 2): ?>
                                            <button onclick="if(confirm('Revert this article to Edited News?')) window.location.href='revert.php?id=<?= $article['id'] ?>&to=1';"
                                                class="bg-gray-50 hover:bg-gray-100 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm col-span-2" 
                                                title="Revert to Edited">
                                                <span class="material-icons text-sm mr-1">undo</span>
                                                Revert to Edited
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex flex-col sm:flex-row justify-between items-center mt-6 lg:mt-8 space-y-4 sm:space-y-0">
                            <!-- Results Info -->
                            <div class="text-sm text-gray-600">
                                Showing <?= $offset + 1 ?> to <?= min($offset + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> articles
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="flex items-center space-x-1">
                                <!-- Previous Button -->
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?= getPaginationUrl($currentPage - 1, $section, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- First page -->
                                <?php if ($paginationStart > 1): ?>
                                    <a href="<?= getPaginationUrl(1, $section, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors">1</a>
                                    <?php if ($paginationStart > 2): ?>
                                        <span class="text-gray-400 px-2">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Page numbers -->
                                <?php for ($i = $paginationStart; $i <= $paginationEnd; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="bg-purple-600 text-white px-4 py-2 rounded-lg"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= getPaginationUrl($i, $section, $itemsPerPage) ?>" 
                                           class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <!-- Last page -->
                                <?php if ($paginationEnd < $totalPages): ?>
                                    <?php if ($paginationEnd < $totalPages - 1): ?>
                                        <span class="text-gray-400 px-2">...</span>
                                    <?php endif; ?>
                                    <a href="<?= getPaginationUrl($totalPages, $section, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $totalPages ?></a>
                                <?php endif; ?>
                                
                                <!-- Next Button -->
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= getPaginationUrl($currentPage + 1, $section, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Items per page selector -->
                            <div class="flex items-center space-x-2 text-sm">
                                <span class="text-gray-600">Show:</span>
                                <select onchange="changeItemsPerPage(this.value)" 
                                        class="bg-white border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="6" <?= $itemsPerPage == 6 ? 'selected' : '' ?>>6</option>
                                    <option value="12" <?= $itemsPerPage == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="24" <?= $itemsPerPage == 24 ? 'selected' : '' ?>>24</option>
                                    <option value="50" <?= $itemsPerPage == 50 ? 'selected' : '' ?>>50</option>
                                </select>
                                <span class="text-gray-600">items</span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Modals - Outside the loop -->
<?php foreach ($paginatedNews as $article): ?>
<div id="modal-<?= $article['id'] ?>" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop flex items-center justify-center hidden z-50">
    <div class="bg-white rounded-lg w-11/12 md:w-3/4 lg:w-2/3 max-h-[90vh] overflow-y-auto p-6 relative modal-enter">
        <button class="absolute top-4 right-4 text-gray-500 hover:text-gray-800 bg-gray-100 hover:bg-gray-200 rounded-full p-2 transition-colors" 
                onclick="closeModal('modal-<?= $article['id'] ?>')">
            <span class="material-icons">close</span>
        </button>
        
        <div class="pr-8">
            <!-- Modal Header -->
            <div class="mb-4 pb-4 border-b">
                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-medium">
                    <?= e($article['category_name'] ?: 'Uncategorized') ?>
                </span>
                <h2 class="text-2xl lg:text-3xl font-bold mt-3 text-gray-800"><?= e($article['title']) ?></h2>
            </div>
            
            <!-- Article Metadata -->
            <div class="flex flex-wrap gap-4 mb-6 text-sm text-gray-600">
                <div class="flex items-center">
                    <span class="material-icons text-sm mr-1">person</span>
                    <span><?= e($article['username'] ?: 'Unknown') ?></span>
                </div>
                <div class="flex items-center">
                    <span class="material-icons text-sm mr-1">business</span>
                    <span><?= e($article['dept_name'] ?: 'N/A') ?></span>
                </div>
                <div class="flex items-center">
                    <span class="material-icons text-sm mr-1">schedule</span>
                    <span><?= date('M d, Y • g:i A', strtotime($article['created_at'])) ?></span>
                </div>
                <?php 
                $statusLabels = [
                    0 => ['label' => 'Regular', 'class' => 'bg-yellow-100 text-yellow-800'],
                    1 => ['label' => 'Edited', 'class' => 'bg-green-100 text-green-800'],
                    2 => ['label' => 'Headline', 'class' => 'bg-blue-100 text-blue-800']
                ];
                $status = $statusLabels[$article['is_pushed']] ?? $statusLabels[0];
                ?>
                <span class="<?= $status['class'] ?> px-3 py-1 rounded-full text-xs font-medium">
                    <?= $status['label'] ?>
                </span>
            </div>
            
            <!-- Article Content -->
            <div class="prose max-w-none text-gray-700 leading-relaxed">
                <?= nl2br(e($article['content'])) ?>
            </div>
            
            <!-- Modal Actions -->
            <div class="mt-6 pt-4 border-t flex gap-3 justify-end">
                <button onclick="window.open('print_headline.php?id=<?= $article['id'] ?>', '_blank');"
                        class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center">
                    <span class="material-icons text-sm mr-2">print</span>
                    Print Article
                </button>
                <button onclick="closeModal('modal-<?= $article['id'] ?>')"
                        class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-lg transition-colors">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<script>
// Modal Functions
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

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('[id^="modal-"]');
        modals.forEach(modal => {
            if (!modal.classList.contains('hidden')) {
                closeModal(modal.id);
            }
        });
    }
});

// Close modal when clicking outside
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeModal(e.target.id);
    }
});

// Sidebar toggle functionality
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    sidebarOverlay.classList.remove('hidden');
}

function closeSidebar() {
    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
}

sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth < 1024 && 
        !sidebar.contains(e.target) && 
        !sidebarToggle?.contains(e.target)) {
        closeSidebar();
    }
});

// Refresh Articles Functionality
function refreshArticles() {
    const refreshBtn = document.getElementById('refreshArticles');
    const articlesGrid = document.getElementById('articlesGrid');
    const refreshIcon = refreshBtn.querySelector('.material-icons');
    
    // Add loading state
    refreshBtn.disabled = true;
    refreshBtn.classList.add('opacity-50', 'cursor-not-allowed');
    refreshIcon.classList.add('animate-spin');
    
    // Fade out articles
    if (articlesGrid) {
        articlesGrid.style.opacity = '0.5';
    }
    
    // Show loading message
    showNotification('Refreshing articles...', 'info');
    
    // Reload the current page
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

// Notification system
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transform translate-x-full transition-transform duration-300 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    
    notification.innerHTML = `
        <div class="flex items-center">
            <span class="material-icons mr-2">${
                type === 'success' ? 'check_circle' :
                type === 'error' ? 'error' :
                type === 'warning' ? 'warning' :
                'info'
            }</span>
            ${message}
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Slide in
    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);
    
    // Slide out and remove
    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Refresh button event listener
document.addEventListener('DOMContentLoaded', function() {
    const refreshBtn = document.getElementById('refreshArticles');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', refreshArticles);
    }
});

// Pagination functionality
function changeItemsPerPage(value) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('per_page', value);
    urlParams.set('page', '1'); // Reset to first page
    window.location.search = urlParams.toString();
}

// Smooth pagination transitions
document.addEventListener('DOMContentLoaded', function() {
    const paginationLinks = document.querySelectorAll('a[href*="page="]');
    paginationLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const articlesContainer = document.getElementById('articlesGrid');
            if (articlesContainer) {
                articlesContainer.style.opacity = '0.5';
                articlesContainer.style.transition = 'opacity 0.3s ease';
            }
        });
    });
});

// Search functionality (client-side filter)
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const newsCards = document.querySelectorAll('.news-card');
        
        newsCards.forEach(card => {
            const title = card.querySelector('h4').textContent.toLowerCase();
            const content = card.querySelector('.truncate-text').textContent.toLowerCase();
            
            if (title.includes(searchTerm) || content.includes(searchTerm)) {
                card.style.display = '';
            } else {
                card.style.display = 'none';
            }
        });
    });
}
</script>

</body>
</html>