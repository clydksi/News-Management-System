<?php
require '../db.php';

$totalArticles = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();

// Pagination settings
$articlesPerPage = isset($_GET['per_page']) ? max(6, min(50, intval($_GET['per_page']))) : 6; // Allow 6-50 items per page
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $articlesPerPage;

// Get total count for pagination
$totalNewsCount = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$totalPages = ceil($totalNewsCount / $articlesPerPage);

// Fetch news articles with pagination and related information
$newsQuery = "SELECT n.*, c.name as category_name, d.name as department_name, u.username as created_by_name 
              FROM news n 
              LEFT JOIN categories c ON n.category_id = c.id 
              LEFT JOIN departments d ON n.department_id = d.id 
              LEFT JOIN users u ON n.created_by = u.id 
              ORDER BY n.created_at DESC 
              LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($newsQuery);
$stmt->bindValue(':limit', $articlesPerPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$newsArticles = $stmt->fetchAll();

// Calculate pagination range
$paginationStart = max(1, $currentPage - 2);
$paginationEnd = min($totalPages, $currentPage + 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>News Admin Dashboard</title>
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
    .modal-exit { animation: modalExit 0.3s ease-in; }
    @keyframes modalEnter {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    @keyframes modalExit {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
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
        <h1 class="text-lg font-bold">News Admin</h1>
    </div>
    <div class="flex items-center space-x-2">
        <button class="text-white">
            <span class="material-icons">notifications</span>
        </button>
        <div class="w-8 h-8 bg-purple-400 rounded-full flex items-center justify-center text-white font-bold text-sm">A</div>
    </div>
</header>

<div class="flex flex-1 relative overflow-hidden">
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed lg:relative w-64 h-full text-white flex flex-col p-4 z-30 lg:translate-x-0 -translate-x-full transition-transform duration-300">
        <div class="flex items-center mb-6 lg:mb-10">
            <span class="material-icons text-2xl lg:text-3xl mr-2">feed</span>
            <h1 class="text-xl lg:text-2xl font-bold">News Admin</h1>
            <button id="sidebarClose" class="ml-auto lg:hidden text-white">
                <span class="material-icons">close</span>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto">
            <ul class="space-y-2">
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="../admin_dashboard.php">
                        <span class="material-icons mr-3 text-xl">dashboard</span> Dashboard
                    </a>
                </li>
                <li>
                    <a  class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors cursor-pointer"  href="https://project.mbcradio.net/saas/chat.php">
                        <span class="material-icons mr-3 text-xl">people</span> Chat Community
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 bg-purple-700 rounded-lg transition-colors" href="articles_admin.php">
                        <span class="material-icons mr-3 text-xl">article</span> Articles
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="categories_admin.php">
                        <span class="material-icons mr-3 text-xl">category</span> Categories
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="#">
                        <span class="material-icons mr-3 text-xl">analytics</span> Analytics
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
                    <input class="bg-white rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-64" placeholder="Search everything..." type="text"/>
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                </div>
                <button class="text-gray-500 hover:text-purple-600">
                    <span class="material-icons">notifications</span>
                </button>
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-2">A</div>
                    <div>
                        <p class="font-semibold text-gray-800">Admin User</p>
                        <p class="text-sm text-gray-500">Super Admin</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Search Bar -->
        <div class="lg:hidden p-4 bg-white border-b">
            <div class="relative">
                <input class="bg-gray-100 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full" placeholder="Search everything..." type="text"/>
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
                        <h3 class="text-xl lg:text-2xl font-bold text-gray-800 mr-4">Latest News Articles</h3>
                        <button id="refreshArticles" class="bg-gray-100 hover:bg-gray-200 text-gray-600 p-2 rounded-lg transition-all duration-200 flex items-center justify-center group" title="Refresh Articles">
                            <span class="material-icons text-lg group-hover:rotate-180 transition-transform duration-500">refresh</span>refresh
                        </button>
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <button class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg transition-colors flex items-center justify-center">
                            <span class="material-icons text-sm mr-2">add</span>
                            Add Article
                        </button>
                        <select class="bg-white border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                            <option value="">Filter by Category</option>
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                            <option value="pending">Pending</option>
                        </select>
                    </div>
                </div>

                <?php if (empty($newsArticles)): ?>
                    <div class="bg-white rounded-xl lg:rounded-2xl shadow-md p-8 text-center">
                        <span class="material-icons text-6xl text-gray-300 mb-4">article</span>
                        <h4 class="text-xl font-semibold text-gray-600 mb-2">No Articles Found</h4>
                        <p class="text-gray-500">Start creating your first news article to see it here.</p>
                        <button class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg mt-4 transition-colors">
                            Create Article
                        </button>
                    </div>
                <?php else: ?>
                    <div id="articlesGrid" class="grid grid-cols-1 xl:grid-cols-2 gap-4 lg:gap-6 transition-opacity duration-300">
                        <?php foreach ($newsArticles as $article): ?>
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
                                                    <?= htmlspecialchars($article['category_name'] ?? 'Uncategorized') ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php if ($article['is_pushed']): ?>
                                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-medium">Published</span>
                                            <?php else: ?>
                                                <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-xs font-medium">Draft</span>
                                            <?php endif; ?>
                                            <button class="text-gray-400 hover:text-gray-600">
                                                <span class="material-icons text-sm">more_vert</span>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Title -->
                                    <h4 class="font-bold text-gray-800 mb-3 text-xl leading-tight hover:text-purple-600 transition-colors cursor-pointer">
                                        <?= htmlspecialchars($article['title']) ?>
                                    </h4>
                                    
                                    <!-- Content Preview -->
                                    <p class="text-gray-600 leading-relaxed truncate-text mb-4 text-sm">
                                        <?= htmlspecialchars(strip_tags($article['content'])) ?>
                                    </p>
                                    
                                    <!-- Metadata -->
                                    <div class="grid grid-cols-2 gap-4 py-3 border-t border-gray-100 mb-4">
                                        <div class="flex items-center text-sm text-gray-500">
                                            <span class="material-icons text-sm mr-2">person</span>
                                            <div>
                                                <p class="font-medium text-gray-700"><?= htmlspecialchars($article['created_by_name'] ?? 'Unknown') ?></p>
                                                <p class="text-xs">Author</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center text-sm text-gray-500">
                                            <span class="material-icons text-sm mr-2">business</span>
                                            <div>
                                                <p class="font-medium text-gray-700"><?= htmlspecialchars($article['department_name'] ?? 'N/A') ?></p>
                                                <p class="text-xs">Department</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Date and Actions -->
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center text-sm text-gray-500 bg-gray-50 px-3 py-1 rounded-lg">
                                            <span class="material-icons text-sm mr-1">schedule</span>
                                            <?= date('M d, Y • g:i A', strtotime($article['created_at'])) ?>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button class="bg-purple-50 hover:bg-purple-100 text-purple-600 p-2 rounded-lg transition-colors" title="Edit Article">
                                                <span class="material-icons text-sm">edit</span>
                                            </button>
                                            <button class="bg-blue-50 hover:bg-blue-100 text-blue-600 p-2 rounded-lg transition-colors" title="View Article">
                                                <span class="material-icons text-sm">visibility</span>
                                            </button>
                                            <button class="bg-red-50 hover:bg-red-100 text-red-600 p-2 rounded-lg transition-colors" title="Delete Article">
                                                <span class="material-icons text-sm">delete</span>
                                            </button>
                                        </div>
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
                                Showing <?= $offset + 1 ?> to <?= min($offset + $articlesPerPage, $totalNewsCount) ?> of <?= $totalNewsCount ?> articles
                            </div>
                            
                            <!-- Pagination Controls -->
                            <div class="flex items-center space-x-1">
                                <!-- Previous Button -->
                                <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?= $currentPage - 1 ?>" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </span>
                                <?php endif; ?>
                                
                                <!-- First page -->
                                <?php if ($paginationStart > 1): ?>
                                    <a href="?page=1" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors">1</a>
                                    <?php if ($paginationStart > 2): ?>
                                        <span class="text-gray-400 px-2">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <!-- Page numbers -->
                                <?php for ($i = $paginationStart; $i <= $paginationEnd; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="bg-purple-600 text-white px-4 py-2 rounded-lg"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <!-- Last page -->
                                <?php if ($paginationEnd < $totalPages): ?>
                                    <?php if ($paginationEnd < $totalPages - 1): ?>
                                        <span class="text-gray-400 px-2">...</span>
                                    <?php endif; ?>
                                    <a href="?page=<?= $totalPages ?>" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $totalPages ?></a>
                                <?php endif; ?>
                                
                                <!-- Next Button -->
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?= $currentPage + 1 ?>" class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
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
                                <select onchange="changeItemsPerPage(this.value)" class="bg-white border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-2 focus:ring-purple-500">
                                    <option value="6" <?= $articlesPerPage == 6 ? 'selected' : '' ?>>6</option>
                                    <option value="12" <?= $articlesPerPage == 12 ? 'selected' : '' ?>>12</option>
                                    <option value="24" <?= $articlesPerPage == 24 ? 'selected' : '' ?>>24</option>
                                    <option value="50" <?= $articlesPerPage == 50 ? 'selected' : '' ?>>50</option>
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

<script>
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
    if (window.innerWidth < 1024 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
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
    articlesGrid.style.opacity = '0.5';
    
    // Show loading message
    showNotification('Refreshing articles...', 'info');
    
    // Simulate refresh (in real implementation, this would be an AJAX call)
    setTimeout(() => {
        // Reload the current page to get fresh data
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
    
    // Auto-refresh every 5 minutes (optional)
    // setInterval(refreshArticles, 5 * 60 * 1000);
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
            e.preventDefault();
            const href = this.getAttribute('href');
            
            // Add loading state
            const articlesContainer = document.querySelector('.grid');
            if (articlesContainer) {
                articlesContainer.style.opacity = '0.5';
                articlesContainer.style.transition = 'opacity 0.3s ease';
            }
            
            // Navigate after a brief delay for smooth transition
            setTimeout(() => {
                window.location.href = href;
            }, 100);
        });
    });
});
</script>

</body>
</html>