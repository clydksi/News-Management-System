<?php
require 'reuters/config.php';
require 'reuters/functions.php';

// Subscriptions: alias => category name
$subscriptions = [
    "OFw658" => "Business / Top News",
    "iQz707" => "Entertainment",
    "NoV647" => "Sports",
    "Yfr670" => "Technology",
    "Bxe721" => "World",
    "eLL634" => "Lifestyle"
];

// Pagination settings
$itemsPerPage = $_GET['itemsPerPage'] ?? 12;
$currentPage = $_GET['page'] ?? 1;
$activeCategory = $_GET['category'] ?? array_key_first($subscriptions);

// Fetch articles for active category
$searchQuery = 'query {
    search(filter: {channel: "'.$activeCategory.'"}, limit: 100) {
        items {
            headLine
            versionedGuid
            uri
            contentTimestamp
            thumbnailUrl
        }
    }
}';
$data = reutersQuery($searchQuery, $accessToken, $endpoint);
$allArticles = $data['data']['search']['items'] ?? [];

// Pagination logic
$totalItems = count($allArticles);
$offset = ($currentPage - 1) * $itemsPerPage;
$paginatedArticles = array_slice($allArticles, $offset, $itemsPerPage);
$totalPages = $totalItems > 0 ? ceil($totalItems / $itemsPerPage) : 1;

// Helper functions
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function getPaginationUrl($page, $category, $itemsPerPage) {
    return "?page=$page&category=$category&itemsPerPage=$itemsPerPage";
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M d, Y', $time);
}

// Fetch top story (first article from any category)
$topStory = null;
foreach ($subscriptions as $alias => $categoryName) {
    $searchQuery = 'query {
        search(filter: {channel: "'.$alias.'"}, limit: 1) {
            items {
                headLine
                versionedGuid
                uri
                contentTimestamp
                thumbnailUrl
            }
        }
    }';
    $data = reutersQuery($searchQuery, $accessToken, $endpoint);
    $articles = $data['data']['search']['items'] ?? [];
    if (!empty($articles)) {
        $topStory = $articles[0];
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Reuters Clone - Enhanced Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
    html {scroll-behavior: smooth;}
    body { font-family: 'Poppins', sans-serif; background-color: #F0F2F5; }
    .main-content { background-color: #EDE9FE; }
    .news-card { transition: all 0.3s ease; }
    .news-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .truncate-text { 
        display: -webkit-box; 
        -webkit-line-clamp: 3; 
        -webkit-box-orient: vertical; 
        overflow: hidden; 
    }
    @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
    .animate-spin { animation: spin 1s linear infinite; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    .slide-in { animation: slideIn 0.3s ease-out; }
    .badge { display: inline-flex; align-items: center; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
    .filter-chip { transition: all 0.2s; }
    .filter-chip:hover { transform: scale(1.05); }
</style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

<!-- Mobile Header -->
<header class="lg:hidden bg-purple-600 text-white p-4 flex items-center justify-between shadow-lg">
    <div class="flex items-center">
        <span class="material-icons text-2xl mr-2">article</span>
        <div>
            <h1 class="text-lg font-bold">Reuters Clone</h1>
            <p class="text-xs opacity-90">📅 <?= date('M j, Y') ?></p>
        </div>
    </div>
    <div class="flex items-center space-x-2">
        <button onclick="toggleFilters()" class="text-white">
            <span class="material-icons">filter_list</span>
        </button>
        <div class="w-8 h-8 bg-purple-400 rounded-full flex items-center justify-center text-white font-bold text-sm">
            <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
        </div>
    </div>
</header>

<div class="flex flex-1 relative overflow-hidden">
    <!-- Main content -->
    <main class="main-content flex-1 flex flex-col overflow-hidden">
        <!-- Desktop Header -->
        <header class="hidden lg:flex justify-between items-center p-6 lg:p-8 bg-white shadow-sm">
            <div class="flex items-center">
                <span class="material-icons text-4xl text-purple-600 mr-3">article</span>
                <div>
                    <h2 class="text-3xl font-bold text-gray-800">Reuters</h2>
                    <p class="text-sm text-gray-500">
                        📅 <?= date('F j, Y') ?> • <span class="text-purple-600">Live News Feed</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-4">
                <div class="flex items-center bg-white rounded-full px-4 py-2 border border-gray-300">
                    <span class="material-icons text-gray-400 mr-2">info</span>
                    <span class="text-sm text-gray-600">
                        Showing <strong><?= count($paginatedArticles) ?></strong> of <strong><?= $totalItems ?></strong>
                    </span>
                </div>
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

        <!-- Filters & Actions Bar -->
        <div class="bg-white border-b p-4 lg:p-6">
            <!-- Top Story Banner (Mobile-Optimized) -->
            <?php if ($topStory): ?>
            <div class="mb-4 bg-gradient-to-r from-purple-50 to-amber-50 border border-purple-200 rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center">
                    <span class="material-icons text-purple-600 mr-2">auto_awesome</span>
                    <div>
                        <span class="text-xs text-purple-700 font-medium">TOP STORY</span>
                        <p class="text-sm text-gray-800 font-semibold truncate max-w-md">
                            <?= e($topStory['headLine']) ?>
                        </p>
                    </div>
                </div>
                <a href="reuters/article.php?id=<?= urlencode($topStory['versionedGuid']) ?>" 
                   class="text-purple-600 hover:text-purple-800 text-sm font-medium whitespace-nowrap ml-4">
                    Read →
                </a>
            </div>
            <?php endif; ?>

            <!-- Category Filter Chips -->
            <div class="flex flex-wrap gap-2 justify-center mb-4" id="categoryFilters">
                <?php 
                $categoryIcons = [
                    "OFw658" => "business_center",
                    "iQz707" => "movie",
                    "NoV647" => "sports_soccer",
                    "Yfr670" => "computer",
                    "Bxe721" => "public",
                    "eLL634" => "favorite"
                ];
                foreach ($subscriptions as $alias => $categoryName): 
                    $icon = $categoryIcons[$alias] ?? 'article';
                ?>
                    <a href="?category=<?= $alias ?>&itemsPerPage=<?= $itemsPerPage ?>" 
                       class="filter-chip px-4 py-2 rounded-full text-sm font-medium transition-all <?= $activeCategory === $alias ? 'bg-purple-600 text-white shadow-md' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                        <span class="material-icons text-sm mr-1" style="vertical-align: middle;"><?= $icon ?></span>
                        <?= e($categoryName) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Items per page & Actions -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-gray-600">Show:</label>
                    <select onchange="changeItemsPerPage(this.value)" 
                            class="bg-gray-50 border border-gray-300 text-gray-700 px-3 py-1 rounded-lg text-sm focus:ring-2 focus:ring-purple-500">
                        <option value="6" <?= $itemsPerPage == 6 ? 'selected' : '' ?>>6</option>
                        <option value="12" <?= $itemsPerPage == 12 ? 'selected' : '' ?>>12</option>
                        <option value="24" <?= $itemsPerPage == 24 ? 'selected' : '' ?>>24</option>
                        <option value="48" <?= $itemsPerPage == 48 ? 'selected' : '' ?>>48</option>
                    </select>
                    <span class="text-sm text-gray-600">per page</span>
                </div>

                <div class="flex gap-2">
                    <button type="button" 
                            onclick="saveAllVisibleArticles()" 
                            class="bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-4 py-2 rounded-lg transition-all flex items-center text-sm shadow-md">
                        <span class="material-icons text-sm mr-2">cloud_download</span>
                        Import All
                    </button>
                    <button type="button" 
                            onclick="window.location.href='user_dashboard.php'" 
                            class="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-4 py-2 rounded-lg transition-all flex items-center text-sm shadow-md">
                        <span class="material-icons text-sm mr-2">dashboard</span>
                        Dashboard
                    </button>
                    <button onclick="window.location.reload()" 
                            class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2 rounded-lg transition-all flex items-center text-sm">
                        <span class="material-icons text-sm mr-2">refresh</span>
                        Refresh
                    </button>
                </div>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <!-- News Articles Section -->
            <section class="mb-6 lg:mb-8">
                <?php if (empty($paginatedArticles)): ?>
                    <div class="bg-white rounded-xl lg:rounded-2xl shadow-md p-12 text-center">
                        <span class="material-icons text-8xl text-gray-300 mb-4">sentiment_dissatisfied</span>
                        <h4 class="text-2xl font-semibold text-gray-600 mb-2">No Articles Found</h4>
                        <p class="text-gray-500 mb-4">No articles available in this category yet.</p>
                    </div>
                <?php else: ?>
                    <div id="articlesGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 transition-opacity duration-300">
                        <?php foreach ($paginatedArticles as $index => $article): ?>
                            <?php if(empty($article['versionedGuid'])) continue; ?>
                            <div class="news-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100" 
                                 data-article-id="<?= $index ?>">
                                <!-- Image -->
                                <?php if (!empty($article['thumbnailUrl'])): ?>
                                    <div class="relative h-48 bg-gray-200 overflow-hidden">
                                        <img src="reuters/image.php?url=<?= urlencode($article['thumbnailUrl']) ?>" 
                                             alt="<?= e($article['headLine']) ?>"
                                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                             onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-100 to-purple-200\'><span class=\'material-icons text-6xl text-yellow-400\'>image_not_supported</span></div>'">
                                    </div>
                                <?php else: ?>
                                    <div class="h-48 bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
                                        <span class="material-icons text-6xl text-purple-400">article</span>
                                    </div>
                                <?php endif; ?>

                                <div class="p-5">
                                    <!-- Header -->
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="badge bg-purple-100 text-purple-700">
                                            <?= e($subscriptions[$activeCategory]) ?>
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            <?= timeAgo($article['contentTimestamp']) ?>
                                        </span>
                                    </div>

                                    <!-- Title -->
                                    <h4 class="font-bold text-gray-800 mb-2 text-lg leading-tight hover:text-purple-600 transition-colors cursor-pointer line-clamp-2"
                                        onclick="window.location.href='reuters/article.php?id=<?= urlencode($article['versionedGuid']) ?>'"
                                        title="<?= e($article['headLine']) ?>">
                                        <?= e($article['headLine']) ?>
                                    </h4>

                                    <!-- Metadata -->
                                    <div class="flex items-center text-xs text-gray-500 mb-4 pb-4 border-b">
                                        <span class="material-icons text-xs mr-1">schedule</span>
                                        <span><?= date("F j, Y", strtotime($article['contentTimestamp'])) ?></span>
                                    </div>

                                    <!-- Actions -->
                                    <div class="grid grid-cols-2 gap-2">
                                        <button onclick="window.location.href='reuters/article.php?id=<?= urlencode($article['versionedGuid']) ?>'"
                                            class="bg-purple-50 hover:bg-purple-100 text-purple-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm font-medium">
                                            <span class="material-icons text-sm mr-1">open_in_new</span>
                                            Read
                                        </button>
                                        
                                        <button type="button" 
                                            class="save-article-btn bg-green-50 hover:bg-green-100 text-green-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm font-medium"
                                            data-article='<?= htmlspecialchars(json_encode([
                                                'title' => $article['headLine'],
                                                'content' => $article['headLine'],
                                                'category' => $subscriptions[$activeCategory],
                                                'source' => 'Reuters',
                                                'author' => 'Reuters',
                                                'published_at' => $article['contentTimestamp'],
                                                'url' => 'reuters/article.php?id=' . urlencode($article['versionedGuid'])
                                            ]), ENT_QUOTES, 'UTF-8') ?>'
                                            data-index="<?= $index ?>">
                                            <span class="material-icons text-sm mr-1">cloud_download</span>
                                            Import
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="flex flex-col sm:flex-row justify-between items-center mt-8 space-y-4 sm:space-y-0 bg-white rounded-lg p-4 shadow-sm">
                            <div class="text-sm text-gray-600">
                                Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $itemsPerPage, $totalItems) ?></strong> of <strong><?= number_format($totalItems) ?></strong> articles
                            </div>

                            <div class="flex items-center space-x-1">
                                <!-- Previous -->
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?= getPaginationUrl($currentPage - 1, $activeCategory, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_left</span>
                                    </span>
                                <?php endif; ?>

                                <!-- Page numbers (show max 7 pages) -->
                                <?php
                                $startPage = max(1, $currentPage - 3);
                                $endPage = min($totalPages, $currentPage + 3);
                                
                                if ($startPage > 1): ?>
                                    <a href="<?= getPaginationUrl(1, $activeCategory, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span class="px-2">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span class="bg-purple-600 text-white px-4 py-2 rounded-lg font-medium shadow-md"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="<?= getPaginationUrl($i, $activeCategory, $itemsPerPage) ?>" 
                                           class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span class="px-2">...</span>
                                    <?php endif; ?>
                                    <a href="<?= getPaginationUrl($totalPages, $activeCategory, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-4 py-2 rounded-lg transition-colors"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <!-- Next -->
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= getPaginationUrl($currentPage + 1, $activeCategory, $itemsPerPage) ?>" 
                                       class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </a>
                                <?php else: ?>
                                    <span class="bg-gray-100 border border-gray-200 text-gray-400 px-3 py-2 rounded-lg cursor-not-allowed flex items-center">
                                        <span class="material-icons text-sm">chevron_right</span>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </main>
</div>

<!-- Toast Notification Container -->
<div id="toastContainer" class="fixed top-4 right-4 z-50 space-y-2"></div>

<script>
// Store articles data globally for import all
let articlesData = <?= json_encode(array_values(array_map(function($article) use ($subscriptions, $activeCategory) {
    return [
        'title' => $article['headLine'],
        'description' => $article['headLine'],
        'category' => $subscriptions[$activeCategory],
        'source' => 'Reuters',
        'author' => 'Reuters',
        'published_at' => $article['contentTimestamp'],
        'url' => 'reuters/article.php?id=' . urlencode($article['versionedGuid'])
    ];
}, $paginatedArticles))) ?>;

// Single article import
document.addEventListener('click', function(e) {
    if (e.target.closest('.save-article-btn')) {
        const btn = e.target.closest('.save-article-btn');
        const article = JSON.parse(btn.getAttribute('data-article'));
        const index = btn.getAttribute('data-index');
        saveArticle(article, btn, index);
    }
});

function saveArticle(article, btnElement, index) {
    const originalContent = btnElement.innerHTML;
    
    // Disable button and show loading
    btnElement.disabled = true;
    btnElement.innerHTML = '<span class="material-icons text-sm mr-1 animate-spin">refresh</span>Importing...';
    
    fetch('news_import/save_article.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title: article.title,
            content: article.content || article.description || article.title,
            category: article.category,
            source: article.source,
            author: article.author,
            published_at: article.published_at,
            url: article.url
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            btnElement.innerHTML = '<span class="material-icons text-sm mr-1">check_circle</span>Imported';
            btnElement.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
            btnElement.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
            
            // Add success indicator to card
            const card = document.querySelector(`[data-article-id="${index}"]`);
            if (card) {
                card.classList.add('ring-2', 'ring-green-500');
                setTimeout(() => card.classList.remove('ring-2', 'ring-green-500'), 2000);
            }
        } else {
            showToast(data.message, 'error');
            btnElement.disabled = false;
            btnElement.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to import article', 'error');
        btnElement.disabled = false;
        btnElement.innerHTML = originalContent;
    });
}

// Save all visible articles
function saveAllVisibleArticles() {
    if (articlesData.length === 0) {
        showToast('No articles to import', 'warning');
        return;
    }

    if (!confirm(`Import all ${articlesData.length} articles to your database?`)) {
        return;
    }

    let saved = 0, failed = 0, exists = 0;
    
    showToast(`Importing ${articlesData.length} articles...`, 'info');
    
    const buttons = document.querySelectorAll('.save-article-btn');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<span class="material-icons text-sm mr-1 animate-spin">refresh</span>Wait...';
    });

    articlesData.forEach((article, index) => {
        setTimeout(() => {
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
            .then(response => response.json())
            .then(data => {
                const btn = buttons[index];
                if (data.success) {
                    saved++;
                    btn.innerHTML = '<span class="material-icons text-sm mr-1">check_circle</span>Imported';
                    btn.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
                    btn.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                } else {
                    if (data.message.includes('already exists')) {
                        exists++;
                        btn.innerHTML = '<span class="material-icons text-sm mr-1">info</span>Exists';
                        btn.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
                        btn.classList.add('bg-yellow-50', 'text-yellow-600', 'cursor-not-allowed');
                    } else {
                        failed++;
                        btn.disabled = false;
                        btn.innerHTML = '<span class="material-icons text-sm mr-1">cloud_download</span>Import';
                    }
                }

                if (saved + failed + exists === articlesData.length) {
                    let message = `✓ ${saved} imported`;
                    if (exists > 0) message += `, ${exists} already existed`;
                    if (failed > 0) message += `, ${failed} failed`;
                    showToast(message, failed === 0 ? 'success' : 'warning');
                }
            })
            .catch(() => {
                failed++;
                buttons[index].disabled = false;
                buttons[index].innerHTML = '<span class="material-icons text-sm mr-1">cloud_download</span>Import';
            });
        }, index * 200);
    });
}

// Change items per page
function changeItemsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('itemsPerPage', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

// Toggle mobile filters
function toggleFilters() {
    const filters = document.getElementById('categoryFilters');
    filters.classList.toggle('hidden');
}

// Toast notification system
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        warning: 'bg-yellow-500',
        info: 'bg-blue-500'
    };
    
    const icons = {
        success: 'check_circle',
        error: 'error',
        warning: 'warning',
        info: 'info'
    };
    
    toast.className = `${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 slide-in min-w-[300px]`;
    toast.innerHTML = `
        <span class="material-icons">${icons[type]}</span>
        <span class="flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
            <span class="material-icons text-sm">close</span>
        </button>
    `;
    
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// Smooth scroll to top
window.addEventListener('scroll', function() {
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        if (window.pageYOffset > 300) {
            scrollBtn.classList.remove('hidden');
        } else {
            scrollBtn.classList.add('hidden');
        }
    }
});
</script>

<!-- Scroll to top button -->
<button id="scrollTopBtn" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" 
        class="hidden fixed bottom-8 right-8 bg-purple-600 text-white p-3 rounded-full shadow-lg hover:bg-purple-700 transition-all z-40">
    <span class="material-icons">arrow_upward</span>
</button>

</body>
</html>