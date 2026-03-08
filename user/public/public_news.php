<?php
/**
 * Public News Page
 * Displays published headline news articles for public viewing
 */

require dirname(__DIR__, 2) . '/db.php';

// Pagination
$itemsPerPage = isset($_GET['per_page']) ? max(6, min(24, intval($_GET['per_page']))) : 12;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Category filter
$categoryId = isset($_GET['category']) ? intval($_GET['category']) : null;

// Search
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query for published headlines
$query = "SELECT n.*, 
                 u.username, 
                 c.name AS category_name,
                 c.id AS category_id
          FROM news n
          JOIN users u ON n.created_by = u.id
          LEFT JOIN categories c ON n.category_id = c.id
          WHERE n.is_pushed = 2 
          AND n.published_at IS NOT NULL";

$countQuery = "SELECT COUNT(*) as total
               FROM news n
               WHERE n.is_pushed = 2 
               AND n.published_at IS NOT NULL";

$params = [];

// Add category filter
if ($categoryId) {
    $query .= " AND n.category_id = ?";
    $countQuery .= " AND n.category_id = ?";
    $params[] = $categoryId;
}

// Add search filter
if (!empty($searchTerm)) {
    $searchFilter = " AND (n.title LIKE ? OR n.content LIKE ?)";
    $query .= $searchFilter;
    $countQuery .= $searchFilter;
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
}

// Get total count
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($params);
$totalItems = $countStmt->fetch()['total'];
$totalPages = ceil($totalItems / $itemsPerPage);

// Get articles
$query .= " ORDER BY n.published_at DESC LIMIT ? OFFSET ?";
$params[] = $itemsPerPage;
$params[] = $offset;

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$articles = $stmt->fetchAll();

// Get categories for filter
$categoriesStmt = $pdo->query("
    SELECT DISTINCT c.id, c.name, COUNT(n.id) as article_count
    FROM categories c
    JOIN news n ON c.id = n.category_id
    WHERE n.is_pushed = 2 AND n.published_at IS NOT NULL
    GROUP BY c.id, c.name
    ORDER BY c.name ASC
");
$categories = $categoriesStmt->fetchAll();

// Helper function
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) return 'Just now';
    if ($difference < 3600) return floor($difference / 60) . ' minutes ago';
    if ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    if ($difference < 604800) return floor($difference / 86400) . ' days ago';
    
    return date('M d, Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Latest News - Headlines</title>
<meta name="description" content="Read the latest news and headlines from our newsroom">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
    html {scroll-behavior: smooth;}
    body { font-family: 'Inter', sans-serif; background-color: #F9FAFB; }
    .hero-gradient { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    .article-card { transition: all 0.3s ease; }
    .article-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); }
    .category-badge { transition: all 0.2s ease; }
    .category-badge:hover { transform: scale(1.05); }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>
</head>
<body>

<!-- Header -->
<header class="bg-white shadow-sm sticky top-0 z-50">
    <nav class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <span class="material-icons text-purple-600 text-3xl mr-2">newspaper</span>
                <h1 class="text-2xl font-bold text-gray-900">News Portal</h1>
            </div>
            
            <!-- Search -->
            <form method="GET" class="hidden md:block">
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?= e($searchTerm) ?>"
                           placeholder="Search news..." 
                           class="w-64 px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                </div>
            </form>
            
            <!-- Mobile Menu -->
            <button class="md:hidden text-gray-600" onclick="toggleMobileSearch()">
                <span class="material-icons">search</span>
            </button>
        </div>
        
        <!-- Mobile Search -->
        <div id="mobileSearch" class="md:hidden mt-4 hidden">
            <form method="GET">
                <div class="relative">
                    <input type="text" 
                           name="search" 
                           value="<?= e($searchTerm) ?>"
                           placeholder="Search news..." 
                           class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">search</span>
                </div>
            </form>
        </div>
    </nav>
</header>

<!-- Hero Section -->
<section class="hero-gradient text-white py-12 md:py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-4xl md:text-5xl font-bold mb-4">Latest Headlines</h2>
        <p class="text-xl text-purple-100 mb-8">Stay informed with our latest news and updates</p>
        
        <!-- Category Filter -->
        <div class="flex flex-wrap justify-center gap-2 md:gap-3">
            <a href="?" 
               class="category-badge px-4 py-2 rounded-full text-sm font-medium transition <?= !$categoryId ? 'bg-white text-purple-600' : 'bg-purple-500 bg-opacity-30 text-white hover:bg-opacity-40' ?>">
                All News
            </a>
            <?php foreach ($categories as $cat): ?>
                <a href="?category=<?= $cat['id'] ?>" 
                   class="category-badge px-4 py-2 rounded-full text-sm font-medium transition <?= $categoryId == $cat['id'] ? 'bg-white text-purple-600' : 'bg-purple-500 bg-opacity-30 text-white hover:bg-opacity-40' ?>">
                    <?= e($cat['name']) ?> (<?= $cat['article_count'] ?>)
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Main Content -->
<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 md:py-12">
    
    <?php if (!empty($searchTerm) || $categoryId): ?>
    <!-- Active Filters -->
    <div class="mb-6 flex items-center gap-4">
        <span class="text-gray-600">Filters:</span>
        <?php if (!empty($searchTerm)): ?>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                Search: "<?= e($searchTerm) ?>"
                <a href="?<?= $categoryId ? 'category=' . $categoryId : '' ?>" class="ml-2 text-purple-600 hover:text-purple-800">
                    <span class="material-icons text-sm">close</span>
                </a>
            </span>
        <?php endif; ?>
        <?php if ($categoryId): ?>
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm bg-purple-100 text-purple-800">
                Category
                <a href="?<?= !empty($searchTerm) ? 'search=' . urlencode($searchTerm) : '' ?>" class="ml-2 text-purple-600 hover:text-purple-800">
                    <span class="material-icons text-sm">close</span>
                </a>
            </span>
        <?php endif; ?>
        <a href="?" class="text-purple-600 hover:text-purple-800 text-sm font-medium">Clear all</a>
    </div>
    <?php endif; ?>
    
    <?php if (empty($articles)): ?>
        <!-- No Results -->
        <div class="text-center py-16">
            <span class="material-icons text-gray-300 text-6xl mb-4">article</span>
            <h3 class="text-2xl font-semibold text-gray-700 mb-2">No Articles Found</h3>
            <p class="text-gray-500 mb-6">
                <?php if (!empty($searchTerm)): ?>
                    No articles match your search. Try different keywords.
                <?php elseif ($categoryId): ?>
                    No articles in this category yet.
                <?php else: ?>
                    No published articles available at the moment.
                <?php endif; ?>
            </p>
            <?php if (!empty($searchTerm) || $categoryId): ?>
                <a href="?" class="inline-flex items-center px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                    <span class="material-icons text-sm mr-2">refresh</span>
                    View All Articles
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Articles Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
            <?php foreach ($articles as $article): ?>
                <article class="article-card bg-white rounded-xl shadow-md overflow-hidden cursor-pointer" 
                         onclick="openArticle(<?= $article['id'] ?>)">
                    <!-- Article Header -->
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-3">
                            <span class="inline-block px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-xs font-medium">
                                <?= e($article['category_name'] ?: 'Uncategorized') ?>
                            </span>
                            <span class="text-xs text-gray-500">
                                <?= timeAgo($article['published_at']) ?>
                            </span>
                        </div>
                        
                        <h3 class="text-xl font-bold text-gray-900 mb-3 hover:text-purple-600 transition">
                            <?= e($article['title']) ?>
                        </h3>
                        
                        <p class="text-gray-600 text-sm leading-relaxed line-clamp-3 mb-4">
                            <?= e(substr(strip_tags($article['content']), 0, 150)) ?>...
                        </p>
                        
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center text-gray-500">
                                <span class="material-icons text-sm mr-1">person</span>
                                <span><?= e($article['username']) ?></span>
                            </div>
                            <button onclick="openArticle(<?= $article['id'] ?>); event.stopPropagation();" 
                                    class="text-purple-600 hover:text-purple-800 font-medium flex items-center">
                                Read More
                                <span class="material-icons text-sm ml-1">arrow_forward</span>
                            </button>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex justify-center">
                <nav class="flex items-center space-x-2">
                    <!-- Previous -->
                    <?php if ($currentPage > 1): ?>
                        <a href="?page=<?= $currentPage - 1 ?><?= $categoryId ? '&category=' . $categoryId : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <span class="material-icons text-sm">chevron_left</span>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start = max(1, $currentPage - 2);
                    $end = min($totalPages, $currentPage + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?page=<?= $i ?><?= $categoryId ? '&category=' . $categoryId : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                           class="px-4 py-2 rounded-lg transition <?= $i == $currentPage ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50 text-gray-700' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <!-- Next -->
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?= $currentPage + 1 ?><?= $categoryId ? '&category=' . $categoryId : '' ?><?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>" 
                           class="px-3 py-2 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            <span class="material-icons text-sm">chevron_right</span>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Article Modal -->
<div id="articleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" onclick="closeArticleIfBackdrop(event)">
    <div class="bg-white rounded-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto" onclick="event.stopPropagation()">
        <div id="articleContent">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="bg-gray-800 text-white mt-16 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <p class="text-gray-400">&copy; <?= date('Y') ?> News Portal. All rights reserved.</p>
            <p class="text-gray-500 text-sm mt-2">
                Showing <?= $offset + 1 ?> to <?= min($offset + $itemsPerPage, $totalItems) ?> of <?= $totalItems ?> articles
            </p>
        </div>
    </div>
</footer>

<script>
function toggleMobileSearch() {
    const mobileSearch = document.getElementById('mobileSearch');
    mobileSearch.classList.toggle('hidden');
}

async function openArticle(articleId) {
    const modal = document.getElementById('articleModal');
    const content = document.getElementById('articleContent');
    
    // Show loading
    content.innerHTML = `
        <div class="p-8 text-center">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
            <p class="mt-4 text-gray-600">Loading article...</p>
        </div>
    `;
    
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden';
    
    try {
        const response = await fetch(`view_article.php?id=${articleId}`);
        const data = await response.json();
        
        if (data.success) {
            content.innerHTML = data.html;
        } else {
            content.innerHTML = `
                <div class="p-8 text-center">
                    <span class="material-icons text-red-500 text-6xl mb-4">error</span>
                    <p class="text-gray-700">${data.message || 'Failed to load article'}</p>
                    <button onclick="closeArticle()" class="mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        Close
                    </button>
                </div>
            `;
        }
    } catch (error) {
        content.innerHTML = `
            <div class="p-8 text-center">
                <span class="material-icons text-red-500 text-6xl mb-4">error</span>
                <p class="text-gray-700">An error occurred while loading the article</p>
                <button onclick="closeArticle()" class="mt-4 px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Close
                </button>
            </div>
        `;
    }
}

function closeArticle() {
    const modal = document.getElementById('articleModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = '';
}

function closeArticleIfBackdrop(event) {
    if (event.target.id === 'articleModal') {
        closeArticle();
    }
}

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeArticle();
    }
});

// Print article function
function printArticle() {
    window.print();
}

// Share article function
function shareArticle(title, url) {
    if (navigator.share) {
        navigator.share({
            title: title,
            url: url
        }).catch(err => console.log('Error sharing:', err));
    } else {
        // Fallback - copy to clipboard
        navigator.clipboard.writeText(url).then(() => {
            alert('Link copied to clipboard!');
        });
    }
}
</script>

</body>
</html>