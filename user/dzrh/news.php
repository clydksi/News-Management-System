<?php
// Correct path to your database connection
require dirname(__DIR__, 2) . '/db.php';

// ⭐ AJAX ENDPOINT FOR CATEGORY FILTERING ⭐
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');
    
    $category = $_GET['category'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 12;
    $offset = ($page - 1) * $limit;
    
    try {
        if ($category === 'all') {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name
                FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY 
                    FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
                    p.published_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $countStmt = $pdo->query("SELECT COUNT(*) FROM published_news");
            $totalArticles = $countStmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name
                FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE LOWER(c.name) = LOWER(:category)
                ORDER BY 
                    FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
                    p.published_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE LOWER(c.name) = LOWER(:category)
            ");
            $countStmt->execute([':category' => $category]);
            $totalArticles = $countStmt->fetchColumn();
        }
        
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'total' => (int)$totalArticles,
            'page' => $page,
            'totalPages' => ceil($totalArticles / $limit),
            'category' => $category
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}
// ⭐ END AJAX ENDPOINT ⭐

// Fetch latest published articles (priority-sorted)
$stmt = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    ORDER BY 
        FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
        p.published_at DESC
    LIMIT 20
");
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// ⭐ Get FEATURED articles (is_featured=1, ordered by featured_order)
$featuredStmt = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_featured = 1
    ORDER BY p.featured_order ASC, p.published_at DESC
    LIMIT 5
");
$featuredArticles = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: if no featured articles, use top 5 most recent
if (empty($featuredArticles)) {
    $featuredStmt = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.published_at DESC
        LIMIT 5
    ");
    $featuredArticles = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ⭐ Get BREAKING NEWS articles for ticker
$breakingStmt = $pdo->query("
    SELECT title FROM published_news
    WHERE is_breaking = 1
      AND (breaking_until IS NULL OR breaking_until > NOW())
    ORDER BY published_at DESC
    LIMIT 5
");
$breakingArticles = $breakingStmt->fetchAll(PDO::FETCH_ASSOC);

// ⭐ Get TRENDING articles (is_trending=1 or highest engagement_score)
$trendingStmt = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_trending = 1
    ORDER BY p.engagement_score DESC, p.views DESC
    LIMIT 5
");
$trendingArticles = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);

// Fallback: top by engagement_score
if (empty($trendingArticles)) {
    $trendingStmt = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.engagement_score DESC, p.views DESC
        LIMIT 5
    ");
    $trendingArticles = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);
}

// ⭐ Get FLASH REPORTS (is_flash=1, not expired)
$flashStmt = $pdo->query("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_flash = 1
      AND (p.flash_until IS NULL OR p.flash_until > NOW())
    ORDER BY p.published_at DESC
    LIMIT 3
");
$flashArticles = $flashStmt->fetchAll(PDO::FETCH_ASSOC);

// First article as headline
$headline = $articles[0] ?? null;

// Helper function to format time ago
function timeAgo($timestamp) {
    if (!$timestamp) return 'Just now';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    elseif ($diff < 3600) { $m = floor($diff/60); return $m.' minute'.($m>1?'s':'').' ago'; }
    elseif ($diff < 86400) { $h = floor($diff/3600); return $h.' hour'.($h>1?'s':'').' ago'; }
    else return date('M d, Y g:i A', $time);
}

// Helper function to get excerpt
function getExcerpt($content, $length = 150) {
    $text = strip_tags($content);
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

// Helper: priority badge config
function priorityBadge($priority) {
    switch ($priority) {
        case 'urgent': return ['bg-red-600', 'URGENT'];
        case 'high':   return ['bg-orange-500', 'HIGH'];
        default:       return [null, null];
    }
}

// Helper: format view count
function formatViews($views) {
    if ($views >= 1000000) return round($views/1000000, 1).'M';
    if ($views >= 1000)    return round($views/1000, 1).'K';
    return $views;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DZRH News - Your Trusted Source for Breaking News</title>
    <meta name="description" content="DZRH News - Breaking news, live radio, and in-depth coverage of current events in the Philippines.">
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style type="text/tailwindcss">
        @layer utilities {
            .text-shadow { text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
            .text-shadow-lg { text-shadow: 0 4px 8px rgba(0,0,0,0.5); }
            .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
            .card-hover:hover { transform: translateY(-4px); }
            .image-overlay { position: relative; overflow: hidden; }
            .image-overlay::after {
                content: ''; position: absolute; inset: 0;
                background: linear-gradient(0deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0) 60%);
                transition: opacity 0.3s ease;
            }
            .image-overlay:hover::after { opacity: 0.8; }
            html { scroll-behavior: smooth; }

            @keyframes shimmer {
                0% { background-position: -1000px 0; }
                100% { background-position: 1000px 0; }
            }
            .skeleton {
                animation: shimmer 2s infinite linear;
                background: linear-gradient(to right, #f0f0f0 4%, #e0e0e0 25%, #f0f0f0 36%);
                background-size: 1000px 100%;
            }

            /* ⭐ Flash report pulse */
            @keyframes flashPulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }
            .flash-pulse { animation: flashPulse 1s ease-in-out infinite; }

            @keyframes ticker {
                0% { transform: translateX(100%); }
                100% { transform: translateX(-100%); }
            }
            .ticker-content { animation: ticker 30s linear infinite; }

            @keyframes pulse-ring {
                0% { transform: scale(0.95); opacity: 1; }
                50% { transform: scale(1.05); opacity: 0.7; }
                100% { transform: scale(0.95); opacity: 1; }
            }
            .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .fade-in { animation: fadeIn 0.6s ease-out forwards; }
            .stagger-1 { animation-delay: 0.1s; }
            .stagger-2 { animation-delay: 0.2s; }
            .stagger-3 { animation-delay: 0.3s; }
            .stagger-4 { animation-delay: 0.4s; }

            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-8px); }
            }
            @keyframes float-delayed {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
            }
            .animate-float { animation: float 5s ease-in-out infinite; }
            .animate-float-delayed { animation: float-delayed 6s ease-in-out infinite; animation-delay: 0.8s; }

            @keyframes spin-slow {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .animate-spin-slow { animation: spin-slow 30s linear infinite; }

            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

            #categoryDropdown { transition: all 0.2s ease; }
            #categoryDropdown:hover { border-color: #2563eb; }

            @keyframes slideDown {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .animate-slideDown { animation: slideDown 0.3s ease-out; }

            .category-filter { position: relative; overflow: hidden; }
            .category-filter::before {
                content: ''; position: absolute; top: 50%; left: 50%;
                width: 0; height: 0; border-radius: 50%;
                background: rgba(37,99,235,0.1);
                transform: translate(-50%, -50%);
                transition: width 0.3s, height 0.3s;
            }
            .category-filter:hover::before { width: 300px; height: 300px; }
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2563eb", "primary-dark": "#1e40af",
                        "accent-yellow": "#eab308", "accent-yellow-dark": "#ca8a04",
                        "accent-red": "#dc2626", "accent-red-dark": "#b91c1c",
                        "background-light": "#fafafa", "background-dark": "#1a1a1a",
                        "surface-light": "#f5f5f5", "surface-dark": "#2a2a2a",
                        "text-light": "#1a1a1a", "text-dark": "#e0e0e0",
                        "text-muted-light": "#666666", "text-muted-dark": "#a0a0a0",
                    },
                    fontFamily: { "display": ["Work Sans", "sans-serif"] },
                    borderRadius: { "DEFAULT": "0.375rem", "lg": "0.625rem", "xl": "0.875rem", "2xl": "1.25rem", "full": "9999px" },
                    boxShadow: {
                        'card': '0 2px 8px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05)',
                        'card-hover': '0 12px 32px rgba(0,0,0,0.18), 0 4px 8px rgba(0,0,0,0.12)',
                        'button': '0 4px 12px rgba(37,99,235,0.3)',
                        'button-hover': '0 6px 20px rgba(37,99,235,0.4)',
                        'button-yellow': '0 4px 12px rgba(234,179,8,0.3)',
                        'button-red': '0 4px 12px rgba(220,38,38,0.3)',
                        'feature': '0 4px 16px rgba(0,0,0,0.12)',
                        'sidebar': '0 2px 12px rgba(0,0,0,0.08)',
                    },
                },
            },
        }
    </script>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-text-light dark:text-text-dark antialiased">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<div class="flex flex-1 justify-center">
<div class="layout-content-container flex flex-col w-full">

    <!-- ⭐ Breaking News Ticker — uses real is_breaking articles -->
    <div class="bg-blue-800 text-black py-2.5 overflow-hidden">
        <div class="max-w-[1600px] mx-auto px-8 flex items-center gap-4">
            <span class="font-bold text-sm uppercase flex items-center gap-2 flex-shrink-0 bg-yellow-400 text-red-600 px-3 py-1 rounded-full shadow-md">
                <span class="material-symbols-outlined text-lg pulse-ring text-red-600 p-1">campaign</span>
                Breaking News
            </span>
            <div class="overflow-hidden flex-1">
                <div class="ticker-content whitespace-nowrap">
                    <?php if (!empty($breakingArticles)): ?>
                        <?php foreach ($breakingArticles as $i => $ba): ?>
                            <span class="text-sm font-semibold text-white"><?= htmlspecialchars($ba['title']) ?></span>
                            <?php if ($i < count($breakingArticles)-1): ?>
                                <span class="mx-4 text-yellow-300">●</span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ($headline): ?>
                        <span class="text-sm font-semibold text-white"><?= htmlspecialchars($headline['title']) ?></span>
                        <span class="mx-4 text-white">|</span>
                        <span class="text-sm font-medium text-white">Stay tuned for more updates</span>
                    <?php else: ?>
                        <span class="text-sm font-semibold text-white">Welcome to DZRH News - Your trusted source for breaking news</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 bg-white/90 px-3 py-1 rounded-sm">
                <span class="material-symbols-outlined text-base">schedule</span>
                <span id="phTime" class="text-sm font-bold whitespace-nowrap"></span>
            </div>
        </div>
    </div>

    <!-- ⭐ Flash Reports Bar (shown only when active flash articles exist) -->
    <?php if (!empty($flashArticles)): ?>
    <div class="bg-amber-500 text-black py-1.5 overflow-hidden">
        <div class="max-w-[1600px] mx-auto px-8 flex items-center gap-3">
            <span class="flex items-center gap-1.5 font-bold text-xs uppercase flex-shrink-0 bg-black text-amber-400 px-2.5 py-1 rounded-full">
                <span class="material-symbols-outlined text-sm flash-pulse">bolt</span>
                Flash Report
            </span>
            <div class="overflow-hidden flex-1">
                <div class="ticker-content whitespace-nowrap" style="animation-duration: 20s;">
                    <?php foreach ($flashArticles as $i => $fa): ?>
                        <a href="article.php?id=<?= $fa['id'] ?>" class="text-xs font-bold text-black hover:underline">
                            <?= htmlspecialchars($fa['title']) ?>
                        </a>
                        <?php if ($i < count($flashArticles)-1): echo '<span class="mx-3 text-black/50">|</span>'; endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Enhanced TopNavBar -->
    <header class="sticky top-0 z-40 border-b border-gray-200 dark:border-surface-dark bg-white/95 dark:bg-surface-dark/95 backdrop-blur-sm shadow-sm">
        <div class="max-w-[1600px] mx-auto px-8 py-3 flex items-center justify-between whitespace-nowrap">
            <div class="flex items-center gap-8">
                <div class="flex items-center gap-3 text-text-light dark:text-text-dark">
                    <a href="dzrh.php" class="inline-block">
                        <img src="https://www.dzrh.com.ph/dzrh-logo.svg" alt="DZRH News"
                            class="h-8 w-auto drop-shadow-md transition-transform hover:scale-110 cursor-pointer">
                    </a>
                </div>
                <nav class="hidden lg:flex items-center gap-6">
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="news.php">
                        News <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="programs.php">
                        Programs <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="schedule.php">
                        On-Air Schedule <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="about_us.php">
                        About Us <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="contact.php">
                        Contact <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                </nav>
            </div>
            <div class="flex gap-4 items-center">
                <label class="hidden md:flex flex-col min-w-40 !h-10 max-w-64">
                    <div class="flex w-full flex-1 items-stretch rounded-xl h-full shadow-sm hover:shadow-md transition-shadow">
                        <div class="text-text-muted-light dark:text-text-muted-dark flex bg-white dark:bg-surface-dark items-center justify-center pl-3 rounded-l-xl border border-r-0 border-gray-200">
                            <span class="material-symbols-outlined text-xl">search</span>
                        </div>
                        <input id="searchInput" class="form-input flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-xl text-text-light dark:text-text-dark focus:outline-0 focus:ring-2 focus:ring-primary border border-l-0 border-gray-200 bg-white dark:bg-surface-dark focus:border-primary h-full placeholder:text-text-muted-light placeholder:dark:text-text-muted-dark px-4 rounded-l-none pl-2 text-sm font-normal leading-normal" placeholder="Search news...">
                    </div>
                </label>
                <button id="notificationBtn" class="relative flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                    <span class="material-symbols-outlined">notifications</span>
                    <?php if (!empty($breakingArticles)): ?>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-accent-red rounded-full pulse-ring"></span>
                    <?php endif; ?>
                </button>
                <button id="darkModeToggle" class="flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                    <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                    <span class="material-symbols-outlined hidden dark:inline">light_mode</span>
                </button>
                <button id="mobileMenuToggle" class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                    <span class="material-symbols-outlined">menu</span>
                </button>
            </div>
        </div>
        <!-- Mobile Menu -->
        <div id="mobileMenu" class="hidden lg:hidden border-t border-gray-200 dark:border-surface-dark bg-white dark:bg-surface-dark">
            <nav class="max-w-[1600px] mx-auto px-8 py-4 flex flex-col gap-3">
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="index.php">News</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">Programs</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">On-Air Schedule</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">About Us</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">Contact</a>
            </nav>
        </div>
    </header>

    <main class="px-8 py-6 grid grid-cols-1 lg:grid-cols-4 gap-6 max-w-[1600px] mx-auto w-full">
        <!-- Main Content -->
        <div class="lg:col-span-3 flex flex-col gap-6">

            <!-- ⭐ Hero Carousel — uses is_featured articles, ordered by featured_order -->
            <?php if (!empty($featuredArticles)): ?>
            <div class="relative rounded-2xl overflow-hidden shadow-feature fade-in">
                <div id="heroCarousel" class="relative">
                    <?php foreach ($featuredArticles as $index => $article):
                        $thumb = $article['thumbnail']
                            ? '../' . htmlspecialchars($article['thumbnail'])
                            : 'https://via.placeholder.com/800x400?text=No+Image';
                        $excerpt = getExcerpt($article['content'], 180);
                        [$pBg, $pLabel] = priorityBadge($article['priority'] ?? 'normal');
                    ?>
                    <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?> bg-cover bg-center flex flex-col items-stretch justify-end pt-80 transition-opacity duration-500 <?= $index !== 0 ? 'opacity-0 absolute inset-0' : '' ?>"
                         style="background-image: linear-gradient(0deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0.4) 50%, rgba(0,0,0,0) 100%), url('<?= $thumb ?>');"
                         data-index="<?= $index ?>">
                        <div class="absolute top-4 left-4 flex items-center gap-2 flex-wrap">
                            <!-- Featured badge -->
                            <div class="bg-primary text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">star</span>
                                Featured
                            </div>
                            <!-- ⭐ Breaking badge -->
                            <?php if (!empty($article['is_breaking'])): ?>
                            <div class="bg-red-600 text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1 pulse-ring">
                                <span class="material-symbols-outlined text-sm">campaign</span>
                                Breaking
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Priority badge (urgent/high only) -->
                            <?php if ($pBg): ?>
                            <div class="<?= $pBg ?> text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg">
                                <?= $pLabel ?>
                            </div>
                            <?php endif; ?>
                            <div class="bg-black/60 backdrop-blur-sm text-white text-xs font-medium px-3 py-1.5 rounded-lg flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                <?= timeAgo($article['published_at']) ?>
                            </div>
                        </div>
                        <?php if (!empty($article['category_name'])): ?>
                        <div class="absolute top-4 right-4 bg-white/90 dark:bg-surface-dark/90 backdrop-blur-sm text-primary text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg">
                            <?= htmlspecialchars($article['category_name']) ?>
                        </div>
                        <?php endif; ?>
                        <div class="flex w-full flex-col md:flex-row items-end justify-between gap-4 p-6 relative z-10">
                            <div class="flex max-w-3xl flex-1 flex-col gap-3">
                                <p class="text-white text-3xl md:text-4xl font-bold leading-tight text-shadow-lg"><?= htmlspecialchars($article['title']) ?></p>
                                <p class="text-gray-100 text-base font-medium leading-normal text-shadow"><?= $excerpt ?></p>
                                <!-- ⭐ Author + views meta -->
                                <div class="flex items-center gap-4 text-xs text-gray-300">
                                    <?php if (!empty($article['author'])): ?>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">person</span>
                                        <?= htmlspecialchars($article['author']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($article['views'])): ?>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">visibility</span>
                                        <?= formatViews($article['views']) ?> views
                                    </span>
                                    <?php endif; ?>
                                    <?php if (!empty($article['source'])): ?>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">link</span>
                                        <?= htmlspecialchars($article['source']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="article.php?id=<?= $article['id'] ?>" class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-8 bg-primary hover:bg-primary-dark text-white text-sm font-bold leading-normal tracking-[0.015em] shrink-0 shadow-button hover:shadow-button-hover transition-all duration-300 transform hover:scale-105 group">
                                <span class="truncate">Read More</span>
                                <span class="material-symbols-outlined ml-2 transition-transform group-hover:translate-x-1">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button id="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-3 rounded-full transition-all duration-300 hover:scale-110 z-20 shadow-lg">
                    <span class="material-symbols-outlined text-2xl">chevron_left</span>
                </button>
                <button id="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-3 rounded-full transition-all duration-300 hover:scale-110 z-20 shadow-lg">
                    <span class="material-symbols-outlined text-2xl">chevron_right</span>
                </button>
                <div class="absolute bottom-20 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20">
                    <?php foreach ($featuredArticles as $index => $item): ?>
                    <button class="carousel-indicator w-2 h-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-primary w-8' : 'bg-white/50 hover:bg-white/80' ?>" data-index="<?= $index ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Category Filter -->
            <div class="bg-white dark:bg-surface-dark rounded-2xl p-4 shadow-card fade-in stagger-1">
                <div class="flex flex-col lg:flex-row items-start lg:items-center gap-4">
                    <div class="flex items-center gap-3 flex-shrink-0 w-full lg:w-auto">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-xl">tune</span>
                            <span class="text-sm font-semibold text-text-muted-light dark:text-text-muted-dark whitespace-nowrap">Filter:</span>
                        </div>
                        <select id="categoryDropdown"
                                class="flex-1 lg:flex-initial lg:min-w-[200px] px-4 py-2 rounded-lg bg-white dark:bg-surface-dark border-2 border-gray-200 dark:border-gray-700 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-all cursor-pointer hover:border-primary">
                            <option value="all">📰 All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= strtolower($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hidden lg:block w-px h-8 bg-gray-200 dark:bg-gray-700"></div>
                    <div class="flex items-center gap-2 overflow-x-auto pb-2 lg:pb-0 scrollbar-hide flex-1 w-full">
                        <span class="text-xs font-medium text-text-muted-light dark:text-text-muted-dark whitespace-nowrap flex-shrink-0">Quick:</span>
                        <button class="category-filter active px-4 py-2 rounded-full bg-primary text-white text-xs font-semibold whitespace-nowrap transition-all hover:shadow-button flex items-center gap-1.5 flex-shrink-0" data-category="all">
                            <span class="material-symbols-outlined text-sm">grid_view</span>All
                        </button>
                        <?php $popularCategories = array_slice($categories, 0, 6); foreach ($popularCategories as $cat): ?>
                        <button class="category-filter px-4 py-2 rounded-full bg-gray-100 dark:bg-gray-700 text-text-light dark:text-text-dark text-xs font-semibold whitespace-nowrap transition-all hover:bg-primary hover:text-white flex-shrink-0"
                                data-category="<?= strtolower($cat['name']) ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                        <?php endforeach; ?>
                        <?php if (count($categories) > 6): ?>
                        <button id="showAllCategoriesBtn" class="px-3 py-2 rounded-full bg-gray-200 dark:bg-gray-600 text-text-light dark:text-text-dark text-xs font-semibold whitespace-nowrap transition-all hover:bg-gray-300 dark:hover:bg-gray-500 flex items-center gap-1 flex-shrink-0">
                            <span>+<?= count($categories) - 6 ?> more</span>
                            <span class="material-symbols-outlined text-sm">expand_more</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (count($categories) > 6): ?>
                <div id="allCategoriesPanel" class="hidden mt-4 pt-4 border-t border-gray-200 dark:border-gray-700 animate-slideDown">
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-2">
                        <?php $remainingCategories = array_slice($categories, 6); foreach ($remainingCategories as $cat): ?>
                        <button class="category-filter px-3 py-2 rounded-lg bg-gray-50 dark:bg-gray-800 hover:bg-primary hover:text-white text-text-light dark:text-text-dark text-xs font-semibold transition-all text-center"
                                data-category="<?= strtolower($cat['name']) ?>">
                            <?= htmlspecialchars($cat['name']) ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div id="articlesSection" class="flex flex-col gap-6"></div>

            <!-- Category Title & Count -->
            <div class="flex items-center justify-between">
                <h2 id="categoryTitle" class="text-2xl font-bold text-text-light dark:text-text-dark">Latest News</h2>
                <span id="articleCount" class="text-sm text-text-muted-light dark:text-text-muted-dark"><?= count($articles) ?> articles</span>
            </div>

            <!-- Loading Skeleton -->
            <div id="loadingIndicator" class="hidden grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php for($i = 0; $i < 6; $i++): ?>
                <div class="bg-white dark:bg-surface-dark rounded-2xl overflow-hidden shadow-card">
                    <div class="skeleton h-48 w-full"></div>
                    <div class="p-4 space-y-3">
                        <div class="skeleton h-4 w-3/4 rounded"></div>
                        <div class="skeleton h-4 w-full rounded"></div>
                        <div class="skeleton h-4 w-2/3 rounded"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <!-- ⭐ Articles Grid — with breaking/flash/trending/priority badges + author + views -->
            <div id="articlesGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($articles as $article):
                    $thumb = $article['thumbnail'] ? '../' . htmlspecialchars($article['thumbnail']) : 'https://via.placeholder.com/400x300?text=No+Image';
                    $excerpt = getExcerpt($article['content'], 120);
                    [$pBg, $pLabel] = priorityBadge($article['priority'] ?? 'normal');
                ?>
                <article class="article-card bg-white dark:bg-surface-dark rounded-2xl overflow-hidden shadow-card hover:shadow-card-hover card-hover transition-all duration-300">
                    <a href="article.php?id=<?= $article['id'] ?>" class="block">
                        <div class="relative aspect-video image-overlay">
                            <img src="<?= $thumb ?>" alt="<?= htmlspecialchars($article['title']) ?>" class="w-full h-full object-cover">
                            <!-- Category badge -->
                            <?php if (!empty($article['category_name'])): ?>
                            <div class="absolute top-3 left-3 bg-primary text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg z-10">
                                <?= htmlspecialchars($article['category_name']) ?>
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Status badges (top-right) -->
                            <div class="absolute top-3 right-3 flex flex-col gap-1 z-10">
                                <?php if (!empty($article['is_breaking'])): ?>
                                <span class="bg-red-600 text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-md pulse-ring">🔴 Breaking</span>
                                <?php endif; ?>
                                <?php if (!empty($article['is_flash'])): ?>
                                <span class="bg-amber-500 text-black text-[10px] font-bold uppercase px-2 py-0.5 rounded-md flash-pulse">⚡ Flash</span>
                                <?php endif; ?>
                                <?php if (!empty($article['is_trending'])): ?>
                                <span class="bg-orange-500 text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-md">🔥 Trending</span>
                                <?php endif; ?>
                                <?php if ($pBg): ?>
                                <span class="<?= $pBg ?> text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded-md"><?= $pLabel ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="p-5">
                            <!-- Time + views row -->
                            <div class="flex items-center justify-between text-xs text-text-muted-light dark:text-text-muted-dark mb-3">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    <?= timeAgo($article['published_at']) ?>
                                </span>
                                <?php if (!empty($article['views'])): ?>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                    <?= formatViews($article['views']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-2 leading-tight line-clamp-2 hover:text-primary transition-colors">
                                <?= htmlspecialchars($article['title']) ?>
                            </h3>
                            <p class="text-sm text-text-muted-light dark:text-text-muted-dark line-clamp-3">
                                <?= $excerpt ?>
                            </p>
                            <!-- ⭐ Author + shares footer -->
                            <div class="mt-4 flex items-center justify-between border-t border-gray-100 dark:border-gray-700 pt-3">
                                <span class="text-xs text-text-muted-light dark:text-text-muted-dark flex items-center gap-1 truncate max-w-[60%]">
                                    <?php if (!empty($article['author'])): ?>
                                    <span class="material-symbols-outlined text-sm">person</span>
                                    <span class="truncate"><?= htmlspecialchars($article['author']) ?></span>
                                    <?php else: ?>
                                    <span class="material-symbols-outlined text-sm">newspaper</span>
                                    <span>DZRH News</span>
                                    <?php endif; ?>
                                </span>
                                <div class="flex items-center gap-3">
                                    <?php if (!empty($article['shares'])): ?>
                                    <span class="text-xs text-text-muted-light dark:text-text-muted-dark flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">share</span>
                                        <?= formatViews($article['shares']) ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="text-sm font-semibold text-primary hover:underline">Read more →</span>
                                </div>
                            </div>
                        </div>
                    </a>
                </article>
                <?php endforeach; ?>
            </div>

            <!-- Empty State -->
            <div id="emptyState" class="hidden text-center py-12">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-100 dark:bg-surface-dark mb-4">
                    <span class="material-symbols-outlined text-4xl text-text-muted-light dark:text-text-muted-dark">article</span>
                </div>
                <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">No Articles Found</h3>
                <p class="text-text-muted-light dark:text-text-muted-dark">No articles available in this category yet.</p>
            </div>

            <!-- Pagination -->
            <div id="paginationContainer" class="flex items-center justify-center gap-2 mt-6"></div>
        </div>

        <!-- Sidebar -->
        <aside class="lg:col-span-1 flex flex-col gap-6">

            <!-- Traffic & Weather Widgets -->
            <div class="grid grid-cols-1 gap-3">
                <!-- Traffic Widget -->
                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 shadow-md group cursor-pointer transition-all duration-300 hover:shadow-lg hover:scale-[1.01]">
                    <a href="https://www.waze.com/live-map" target="_blank" class="block p-3">
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute inset-0" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 8px, rgba(255,255,255,.1) 8px, rgba(255,255,255,.1) 16px);"></div>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-7 h-7 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-white font-bold text-xs leading-tight">Traffic</h3>
                                        <p class="text-blue-100 text-[10px] leading-tight">Waze Live</p>
                                    </div>
                                </div>
                                <div class="px-1.5 py-0.5 rounded-full bg-green-400 text-green-900 text-[10px] font-bold flex items-center gap-0.5">
                                    <span class="w-1 h-1 bg-green-900 rounded-full animate-pulse"></span>LIVE
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-white text-lg font-bold" id="trafficStatus">Moderate</p>
                                    <p class="text-blue-100 text-[10px]">Manila Metro</p>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center gap-0.5 mb-0.5">
                                        <div class="w-1.5 h-5 rounded-full bg-green-400"></div>
                                        <div class="w-1.5 h-5 rounded-full bg-yellow-400"></div>
                                        <div class="w-1.5 h-5 rounded-full bg-red-400 opacity-50"></div>
                                    </div>
                                    <p class="text-blue-100 text-[9px]">Flow</p>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-white/20 flex items-center justify-between text-[10px] text-white font-semibold">
                                <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-xs">update</span>2 min ago</span>
                                <span class="flex items-center gap-0.5 group-hover:gap-1 transition-all">View Map<span class="material-symbols-outlined text-xs">arrow_forward</span></span>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Weather Widget -->
                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-cyan-400 via-sky-500 to-blue-600 shadow-md group cursor-pointer transition-all duration-300 hover:shadow-lg hover:scale-[1.01]" id="weatherWidget">
                    <div class="block p-3">
                        <div class="absolute inset-0 opacity-15">
                            <div class="absolute top-5 left-5 w-12 h-12 bg-white rounded-full animate-float"></div>
                            <div class="absolute top-10 right-5 w-10 h-10 bg-white rounded-full animate-float-delayed"></div>
                            <div class="absolute bottom-5 left-1/3 w-8 h-8 bg-white rounded-full animate-float"></div>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1.5">
                                    <div class="w-7 h-7 rounded-lg bg-white/20 backdrop-blur-sm flex items-center justify-center">
                                        <span class="material-symbols-outlined text-white text-base">wb_sunny</span>
                                    </div>
                                    <div>
                                        <h3 class="text-white font-bold text-xs leading-tight">Weather</h3>
                                        <p class="text-sky-100 text-[10px] leading-tight">Manila, PH</p>
                                    </div>
                                </div>
                                <button class="p-1 rounded-lg hover:bg-white/20 transition-colors" onclick="refreshWeather()">
                                    <span class="material-symbols-outlined text-white text-base">refresh</span>
                                </button>
                            </div>
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="flex items-start">
                                        <span class="text-white text-3xl font-bold leading-none" id="temperature">32</span>
                                        <span class="text-white text-lg font-light">°C</span>
                                    </div>
                                    <p class="text-sky-100 text-xs mt-0.5 font-medium" id="weatherCondition">Partly Cloudy</p>
                                    <div class="flex items-center gap-2 mt-1 text-[10px] text-sky-100">
                                        <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-xs">water_drop</span><span id="humidity">65%</span></span>
                                        <span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-xs">air</span><span id="windSpeed">12km/h</span></span>
                                    </div>
                                </div>
                                <div class="relative">
                                    <div class="w-14 h-14 flex items-center justify-center" id="weatherIcon">
                                        <svg class="w-12 h-12 text-yellow-300 animate-spin-slow" fill="currentColor" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="5"/>
                                            <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                                        </svg>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 pt-2 border-t border-white/20">
                                <div class="grid grid-cols-4 gap-1.5">
                                    <div class="text-center"><p class="text-[9px] text-sky-100 mb-0.5">6AM</p><span class="material-symbols-outlined text-white text-sm">wb_twilight</span><p class="text-[10px] text-white font-semibold mt-0.5">28°</p></div>
                                    <div class="text-center"><p class="text-[9px] text-sky-100 mb-0.5">12PM</p><span class="material-symbols-outlined text-white text-sm">wb_sunny</span><p class="text-[10px] text-white font-semibold mt-0.5">32°</p></div>
                                    <div class="text-center"><p class="text-[9px] text-sky-100 mb-0.5">6PM</p><span class="material-symbols-outlined text-white text-sm">partly_cloudy_day</span><p class="text-[10px] text-white font-semibold mt-0.5">30°</p></div>
                                    <div class="text-center"><p class="text-[9px] text-sky-100 mb-0.5">12AM</p><span class="material-symbols-outlined text-white text-sm">nightlight</span><p class="text-[10px] text-white font-semibold mt-0.5">26°</p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Radio Player -->
            <div class="flex flex-col gap-3 rounded-2xl bg-gradient-to-br from-gray-900 to-black text-white p-5 shadow-sidebar fade-in stagger-3">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 bg-accent-red rounded-full pulse-ring"></div>
                        <span class="text-xs font-bold uppercase">Live Now</span>
                    </div>
                    <button class="p-1 hover:bg-white/10 rounded transition-colors">
                        <span class="material-symbols-outlined text-lg">more_vert</span>
                    </button>
                </div>
                <div class="flex items-center gap-4 overflow-hidden">
                    <div class="bg-center bg-no-repeat aspect-square bg-cover rounded-xl size-16 shrink-0 shadow-lg ring-2 ring-white/20"
                        style="background-image: url('http://newsnetwork.mbcradio.net/crud/images/logo_Dzrh.jpg');"></div>
                    <div class="flex-1">
                        <p class="text-base font-bold leading-tight truncate">DZRH Manila Radio</p>
                        <p class="text-gray-300 text-sm">Streaming Now</p>
                    </div>
                    <button id="playBtn" class="flex shrink-0 items-center justify-center rounded-full size-12 bg-accent-red hover:bg-accent-red-dark text-white shadow-button transition-all duration-300 transform hover:scale-110">
                        <span class="material-symbols-outlined text-3xl" id="playIcon">play_arrow</span>
                    </button>
                </div>
                <div class="pt-1.5">
                    <input id="progressBar" type="range" min="0" max="100" value="0" class="w-full accent-primary-dark">
                </div>
                <div class="flex items-center justify-between mt-2">
                    <p id="currentTime" class="text-gray-300 text-xs">0:00</p>
                    <p class="text-gray-300 text-xs">LIVE</p>
                </div>
                <div class="flex items-center justify-between pt-2 border-t border-gray-700">
                    <button id="muteBtn" class="p-2 hover:bg-white/10 rounded transition-colors">
                        <span class="material-symbols-outlined" id="volumeIcon">volume_up</span>
                    </button>
                    <div class="flex items-center gap-1">
                        <button class="p-2 hover:bg-white/10 rounded transition-colors"><span class="material-symbols-outlined">skip_previous</span></button>
                        <button class="p-2 hover:bg-white/10 rounded transition-colors"><span class="material-symbols-outlined">skip_next</span></button>
                    </div>
                    <button class="p-2 hover:bg-white/10 rounded transition-colors"><span class="material-symbols-outlined">share</span></button>
                </div>
            </div>
            <audio id="radioPlayer" src="https://dzrh-azura.mmg.com.ph/listen/dzrh_manila/radio.mp3"></audio>

            <!-- ⭐ Trending Stories Sidebar — uses is_trending + engagement_score -->
            <div class="flex flex-col gap-4 bg-white dark:bg-surface-dark rounded-2xl p-5 shadow-card fade-in stagger-4">
                <div class="flex items-center justify-between border-b-2 border-primary pb-3">
                    <h3 class="text-xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-red">trending_up</span>
                        Top Stories
                    </h3>
                </div>
                <ul class="space-y-4">
                    <?php
                    $topStories = !empty($trendingArticles) ? $trendingArticles : array_slice($articles, 0, 5);
                    $rank = 1;
                    foreach ($topStories as $story): ?>
                    <li class="flex items-start gap-3 group pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg transition-colors">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-primary to-primary-dark text-white font-extrabold text-sm flex-shrink-0 shadow-md">
                            <?= $rank++ ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a class="text-sm font-semibold leading-tight group-hover:text-primary transition-colors block truncate-2"
                               href="article.php?id=<?= $story['id'] ?>">
                                <?= htmlspecialchars($story['title']) ?>
                            </a>
                            <!-- ⭐ Views + engagement score -->
                            <div class="flex items-center gap-3 mt-1 text-[10px] text-text-muted-light dark:text-text-muted-dark">
                                <?php if (!empty($story['views'])): ?>
                                <span class="flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-xs">visibility</span>
                                    <?= formatViews($story['views']) ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($story['engagement_score'])): ?>
                                <span class="flex items-center gap-0.5 text-orange-500">
                                    <span class="material-symbols-outlined text-xs">whatshot</span>
                                    <?= $story['engagement_score'] ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 shadow-card">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">article</span>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-primary"><?= count($articles) ?>+</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Articles</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-xl p-4 shadow-card">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-accent-yellow rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-900 text-lg">visibility</span>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-accent-yellow-dark">1.2M</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Views</p>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl p-4 shadow-card">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-accent-red rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">radio</span>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-accent-red">24/7</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Live Radio</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 shadow-card">
                    <div class="flex items-center gap-2 mb-2">
                        <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-white text-lg">verified</span>
                        </div>
                    </div>
                    <p class="text-2xl font-bold text-green-600">75+</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Years</p>
                </div>
            </div>

            <!-- Newsletter Signup -->
            <div class="flex flex-col gap-4 bg-gradient-to-br from-accent-yellow to-accent-yellow-dark text-gray-900 rounded-2xl p-5 shadow-card">
                <h3 class="text-lg font-bold">Stay Updated</h3>
                <p class="text-sm opacity-90">Get the latest news delivered to your inbox</p>
                <form id="newsletterForm" class="flex gap-2">
                    <input type="email" id="newsletterEmail" placeholder="Enter your email" class="flex-1 px-4 py-2 rounded-lg text-text-light bg-white focus:outline-none focus:ring-2 focus:ring-gray-900" required>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors">
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </form>
            </div>

            <!-- Ad Banner -->
            <div class="bg-gradient-to-br from-gray-100 to-gray-200 dark:from-surface-dark dark:to-surface-dark rounded-2xl flex items-center justify-center h-64 shadow-card relative overflow-hidden">
                <div class="absolute inset-0 opacity-10"><div class="absolute inset-0 bg-gradient-to-br from-primary to-transparent"></div></div>
                <div class="relative z-10 text-center">
                    <span class="material-symbols-outlined text-5xl text-text-muted-light dark:text-text-muted-dark mb-2">ad_units</span>
                    <p class="text-text-muted-light dark:text-text-muted-dark text-lg font-semibold">Advertisement</p>
                    <p class="text-text-muted-light dark:text-text-muted-dark text-xs mt-1">300 x 250</p>
                </div>
            </div>

            <!-- Program Schedule -->
            <div class="flex flex-col gap-4 bg-white dark:bg-surface-dark rounded-2xl p-5 shadow-card">
                <div class="flex items-center justify-between border-b-2 border-primary pb-3">
                    <h3 class="text-xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">schedule</span>
                        Today's Schedule
                    </h3>
                </div>
                <ul class="space-y-3">
                    <li class="border-l-4 border-accent-red pl-4 py-3 bg-red-50 dark:bg-red-900/10 rounded-r-lg shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <p class="font-bold text-accent-red text-sm">6:00 AM - 8:00 AM</p>
                                <p class="text-text-light dark:text-text-dark font-semibold text-sm mt-1">Morning Balita</p>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-xs">with Joe Taruc</p>
                            </div>
                            <span class="material-symbols-outlined text-accent-red">radio</span>
                        </div>
                    </li>
                    <li class="pl-5 py-3 hover:bg-gray-50 dark:hover:bg-surface-dark rounded-lg transition-colors border-l-4 border-transparent hover:border-gray-300">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <p class="font-semibold text-sm">8:00 AM - 9:00 AM</p>
                                <p class="text-text-light dark:text-text-dark font-semibold text-sm mt-1">Damdaming Bayan</p>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-xs">with Deo Macalma</p>
                            </div>
                            <span class="material-symbols-outlined text-gray-400">mic</span>
                        </div>
                    </li>
                    <li class="pl-5 py-3 hover:bg-gray-50 dark:hover:bg-surface-dark rounded-lg transition-colors border-l-4 border-transparent hover:border-gray-300">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1">
                                <p class="font-semibold text-sm">9:00 AM - 10:30 AM</p>
                                <p class="text-text-light dark:text-text-dark font-semibold text-sm mt-1">Operation Tulong</p>
                                <p class="text-text-muted-light dark:text-text-muted-dark text-xs">Public Service</p>
                            </div>
                            <span class="material-symbols-outlined text-gray-400">volunteer_activism</span>
                        </div>
                    </li>
                </ul>
                <a href="#" class="text-center text-primary font-semibold text-sm hover:underline flex items-center justify-center gap-1">
                    View Full Schedule <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </a>
            </div>
        </aside>
    </main>

    <!-- Footer -->
    <footer class="bg-gradient-to-b from-gray-900 to-black mt-8 py-10 px-8 shadow-2xl">
        <div class="max-w-[1600px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
            <div class="md:col-span-1">
                <div class="flex items-center gap-3 text-white mb-4">
                    <div class="size-10 text-primary drop-shadow-md"><img src="https://www.dzrh.com.ph/dzrh-logo.svg" alt="DZRH" /></div>
                    <h2 class="text-xl font-bold">DZRH News</h2>
                </div>
                <p class="text-sm text-gray-300 leading-relaxed mb-4">Your trusted source for breaking news, in-depth analysis, and live radio broadcasts since 1950.</p>
                <div class="flex space-x-3">
                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22.675 0h-21.35C.59 0 0 .59 0 1.325v21.35C0 23.41.59 24 1.325 24H12.82v-9.29H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116c.735 0 1.325-.59 1.325-1.325V1.325C24 .59 23.409 0 22.675 0z"></path></svg>
                    </a>
                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.223.085c.645 1.956 2.52 3.379 4.738 3.419-1.914 1.493-4.32 2.387-6.94 2.387-.452 0-.898-.027-1.336-.079a13.97 13.97 0 007.548 2.212c9.058 0 14.01-7.502 14.01-14.01 0-.213 0-.425-.015-.636A10.016 10.016 0 0024 4.59z"></path></svg>
                    </a>
                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-accent-red hover:text-white transition-all transform hover:scale-110" href="#">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm4.441 16.892c-2.102.144-6.784.144-8.883 0C5.282 16.736 5.017 15.622 5 12c.017-3.629.285-4.736 2.558-4.892 2.099-.144 6.782-.144 8.883 0C18.718 7.264 18.982 8.378 19 12c-.018 3.629-.285 4.736-2.559 4.892zM10 9.658l4.917 2.338L10 14.342z"/></svg>
                    </a>
                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                </div>
            </div>
            <div>
                <h4 class="text-lg font-bold mb-4 text-white">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>About Us</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Our Team</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Careers</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Advertise</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-lg font-bold mb-4 text-white">Categories</h4>
                <ul class="space-y-2 text-sm">
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Politics</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Business</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Sports</a></li>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#"><span class="material-symbols-outlined text-sm">chevron_right</span>Entertainment</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-lg font-bold mb-4 text-white">Contact</h4>
                <ul class="space-y-3 text-sm text-gray-300">
                    <li class="flex items-start gap-2"><span class="material-symbols-outlined text-primary text-lg">location_on</span><span>MBC Building, Star City, CCP Complex, Roxas Boulevard, Manila</span></li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">call</span><span>(632) 8527-8515</span></li>
                    <li class="flex items-center gap-2"><span class="material-symbols-outlined text-primary text-lg">email</span><span>info@dzrhnews.com.ph</span></li>
                </ul>
            </div>
        </div>
        <div class="mt-8 pt-6 border-t border-gray-800">
            <div class="max-w-[1600px] mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-400">
                <p>© 2024 DZRH News. All Rights Reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                    <span>•</span>
                    <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
                    <span>•</span>
                    <a href="#" class="hover:text-primary transition-colors">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

</div></div></div></div>

<!-- Scroll to Top -->
<button id="scrollToTop" class="fixed bottom-20 right-5 z-50 hidden items-center justify-center w-12 h-12 rounded-full bg-primary text-white shadow-button hover:shadow-button-hover transition-all duration-300 hover:scale-110">
    <span class="material-symbols-outlined">arrow_upward</span>
</button>

<!-- Floating YouTube Player -->
<div id="ytFloatingContainer" class="fixed bottom-4 right-4 sm:bottom-5 sm:right-5 z-50">
    <button id="ytToggle" class="flex items-center gap-2 sm:gap-3 px-3 py-2.5 sm:px-4 sm:py-3 rounded-full bg-gradient-to-r from-accent-red to-red-700 shadow-2xl hover:shadow-accent-red/50 transition-all duration-300 hover:scale-105 active:scale-95 group backdrop-blur-sm border-2 border-red-500/30">
        <div class="relative">
            <svg viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5 sm:w-6 sm:h-6 text-white group-hover:scale-110 transition-transform">
                <path d="M19.615 3.184A3.01 3.01 0 0 0 17.5 2.5C15.09 2.5 12 2.5 12 2.5s-3.09 0-5.5.127c-.823.04-1.598.337-2.115.957A4.28 4.28 0 0 0 3.5 5.5 46.8 46.8 0 0 0 3.373 12c0 1.98.127 3.96.127 3.96.04.823.337 1.598.957 2.115.517.62 1.292.917 2.115.957C8.91 19.5 12 19.5 12 19.5s3.09 0 5.5-.127c.823-.04 1.598-.337 2.115-.957.62-.517.917-1.292.957-2.115.127-1.98.127-3.96.127-3.96 0-1.98-.127-3.96-.127-3.96-.04-.823-.337-1.598-.957-2.115zM10 15.5V8.5l6 3.5-6 3.5z"/>
            </svg>
            <div class="absolute -top-1 -right-1 w-2.5 h-2.5 sm:w-3 sm:h-3 bg-white rounded-full pulse-ring"></div>
        </div>
        <span class="text-white font-semibold text-xs sm:text-sm hidden xs:inline-block">Watch Live</span>
    </button>
    <div id="ytMiniPlayer" class="hidden fixed sm:absolute bottom-0 right-0 left-0 sm:left-auto w-full sm:w-80 md:w-96 bg-black rounded-t-2xl sm:rounded-2xl shadow-2xl overflow-hidden border-t-2 sm:border-2 border-gray-700 max-h-[60vh] sm:max-h-none">
        <div class="flex items-center justify-between p-2.5 sm:p-3 bg-gradient-to-r from-accent-red to-red-700 border-b border-red-500">
            <div class="flex items-center gap-2">
                <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-white rounded-full pulse-ring"></div>
                <span class="text-white text-xs sm:text-sm font-semibold">DZRH Live</span>
            </div>
            <div class="flex items-center gap-1">
                <button id="ytExpand" class="text-white hover:bg-white/20 active:bg-white/30 rounded p-1 sm:p-1.5 transition-colors"><span class="material-symbols-outlined text-base sm:text-lg">open_in_full</span></button>
                <button id="ytMinimize" class="text-white hover:bg-white/20 active:bg-white/30 rounded p-1 sm:p-1.5 transition-colors"><span class="material-symbols-outlined text-base sm:text-lg">close</span></button>
            </div>
        </div>
        <div class="relative aspect-video bg-black">
            <iframe id="ytMiniFrame" class="w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <div class="p-2.5 sm:p-3 bg-gray-900 border-t border-gray-700">
            <p class="text-white text-xs font-medium mb-0.5 sm:mb-1">DZRH News Network</p>
            <p class="text-gray-400 text-[10px] sm:text-xs">Live broadcast • 24/7 coverage</p>
        </div>
    </div>
</div>

<!-- Full-Screen Modal -->
<div id="ytModal" class="fixed inset-0 z-[100] hidden">
    <div id="ytBackdrop" class="absolute inset-0 bg-black/95 backdrop-blur-sm transition-opacity"></div>
    <div class="relative h-full flex flex-col">
        <div class="flex items-center justify-between p-3 sm:p-4 md:p-6 bg-gradient-to-b from-black/90 to-transparent relative z-10 border-b border-white/10 sm:border-0">
            <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-gradient-to-br from-accent-red to-red-700 flex items-center justify-center shadow-lg flex-shrink-0">
                    <svg viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 sm:w-5 sm:h-5 text-white"><path d="M19.615 3.184A3.01 3.01 0 0 0 17.5 2.5C15.09 2.5 12 2.5 12 2.5s-3.09 0-5.5.127c-.823.04-1.598.337-2.115.957A4.28 4.28 0 0 0 3.5 5.5 46.8 46.8 0 0 0 3.373 12c0 1.98.127 3.96.127 3.96.04.823.337 1.598.957 2.115.517.62 1.292.917 2.115.957C8.91 19.5 12 19.5 12 19.5s3.09 0 5.5-.127c.823-.04 1.598-.337 2.115-.957.62-.517.917-1.292.957-2.115.127-1.98.127-3.96.127-3.96 0-1.98-.127-3.96-.127-3.96-.04-.823-.337-1.598-.957-2.115zM10 15.5V8.5l6 3.5-6 3.5z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 sm:gap-2">
                        <div class="w-1.5 h-1.5 sm:w-2 sm:h-2 bg-accent-red rounded-full pulse-ring flex-shrink-0"></div>
                        <h2 class="text-white text-sm sm:text-lg md:text-xl font-bold truncate">DZRH Live Stream</h2>
                    </div>
                    <p class="text-gray-400 text-[10px] sm:text-xs md:text-sm truncate">Broadcasting 24/7 • Manila, Philippines</p>
                </div>
            </div>
            <div class="flex items-center gap-1 sm:gap-2 flex-shrink-0">
                <button id="ytPip" class="hidden md:flex items-center gap-2 px-3 py-1.5 sm:px-4 sm:py-2 bg-white/10 hover:bg-white/20 active:bg-white/30 text-white rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-base sm:text-lg">picture_in_picture</span>
                    <span class="text-xs sm:text-sm font-medium hidden lg:inline">Mini Player</span>
                </button>
                <button id="ytModalClose" class="text-white hover:bg-white/20 active:bg-white/30 rounded-lg p-1.5 sm:p-2 transition-colors">
                    <span class="material-symbols-outlined text-xl sm:text-2xl">close</span>
                </button>
            </div>
        </div>
        <div class="flex-1 flex items-center justify-center p-2 sm:p-4 md:p-6 lg:p-8 relative z-10">
            <div class="w-full max-w-7xl aspect-video bg-black rounded-lg sm:rounded-xl md:rounded-2xl overflow-hidden shadow-2xl">
                <iframe id="ytMainFrame" class="w-full h-full" src="" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
            </div>
        </div>
        <div class="p-3 sm:p-4 md:p-6 bg-gradient-to-t from-black/90 to-transparent relative z-10 border-t border-white/10 sm:border-0">
            <div class="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 md:gap-4">
                <div class="bg-white/5 backdrop-blur-sm rounded-lg sm:rounded-xl p-3 sm:p-4 border border-white/10">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-accent-red/20 flex items-center justify-center flex-shrink-0"><span class="material-symbols-outlined text-accent-red text-lg sm:text-xl">radio</span></div>
                        <div class="min-w-0 flex-1"><p class="text-gray-400 text-[10px] sm:text-xs truncate">Current Program</p><p class="text-white text-xs sm:text-sm font-semibold truncate">Morning Balita</p></div>
                    </div>
                </div>
                <div class="bg-white/5 backdrop-blur-sm rounded-lg sm:rounded-xl p-3 sm:p-4 border border-white/10">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-primary/20 flex items-center justify-center flex-shrink-0"><span class="material-symbols-outlined text-primary text-lg sm:text-xl">schedule</span></div>
                        <div class="min-w-0 flex-1"><p class="text-gray-400 text-[10px] sm:text-xs truncate">Broadcasting</p><p class="text-white text-xs sm:text-sm font-semibold truncate">6:00 AM - 8:00 AM</p></div>
                    </div>
                </div>
                <div class="bg-white/5 backdrop-blur-sm rounded-lg sm:rounded-xl p-3 sm:p-4 border border-white/10">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-green-600/20 flex items-center justify-center flex-shrink-0"><span class="material-symbols-outlined text-green-500 text-lg sm:text-xl">signal_cellular_alt</span></div>
                        <div class="min-w-0 flex-1"><p class="text-gray-400 text-[10px] sm:text-xs truncate">Stream Quality</p><p class="text-white text-xs sm:text-sm font-semibold truncate">HD • 1080p</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="script.js"></script>
<script src="category-filter.js"></script>

</body>
</html>