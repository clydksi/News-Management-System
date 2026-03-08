<?php
/**
 * DZRH News Homepage
 * Updated to leverage all published_news fields
 */

// ============================================
// CONFIGURATION & INITIALIZATION
// ============================================
require dirname(__DIR__, 2) . '/db.php';

// ============================================
// HELPER FUNCTIONS
// ============================================

function timeAgo($timestamp) {
    if (!$timestamp) return 'Just now';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M d, Y g:i A', $time);
}

function getExcerpt($content, $length = 150) {
    $text = strip_tags($content);
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}

function getThumbnail($thumbnail, $default = 'https://via.placeholder.com/800x400?text=No+Image') {
    return $thumbnail ? '../' . htmlspecialchars($thumbnail) : $default;
}

function formatViews($views) {
    if (!$views) return null;
    if ($views >= 1000000) return round($views / 1000000, 1) . 'M';
    if ($views >= 1000)    return round($views / 1000, 1) . 'K';
    return $views;
}

function priorityBadge($priority) {
    switch ($priority) {
        case 'urgent': return ['bg-red-600',    'URGENT'];
        case 'high':   return ['bg-orange-500', 'HIGH'];
        default:       return [null, null];
    }
}

// ============================================
// DATA FETCHING  (MySQL 5.7+ compatible — no CTEs)
// ============================================

// Shared derived-table subquery that counts child/update articles per parent.
// Works on MySQL 5.6, 5.7, 8.x and MariaDB 10.x
$updateSubquery = "
    SELECT
        parent_article_id                       AS base_id,
        COUNT(*)                                AS update_count,
        MAX(COALESCE(updated_at, published_at)) AS latest_update,
        SUM(is_breaking)                        AS breaking_count
    FROM published_news
    WHERE parent_article_id IS NOT NULL
    GROUP BY parent_article_id
";

try {

    // ── Breaking News Ticker ─────────────────────────────────────────────────
    $breakingArticles = $pdo->query("
        SELECT id, title
        FROM published_news
        WHERE is_breaking = 1
          AND (breaking_until IS NULL OR breaking_until > NOW())
          AND parent_article_id IS NULL
          
        ORDER BY published_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Flash Reports Bar ────────────────────────────────────────────────────
    $flashArticles = $pdo->query("
        SELECT p.id, p.title, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_flash = 1
          AND (p.flash_until IS NULL OR p.flash_until > NOW())
          AND p.parent_article_id IS NULL
          
        ORDER BY p.published_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Featured Carousel (is_featured = 1, ordered by featured_order) ──────
    // Uses a derived-table JOIN instead of CTE — MySQL 5.7 safe
    $featuredArticles = $pdo->query("
        SELECT
            p.*,
            c.name                        AS category_name,
            COALESCE(au.update_count, 0)  AS developing_count,
            au.latest_update,
            COALESCE(au.breaking_count,0) AS has_breaking_updates
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN ($updateSubquery) AS au ON p.id = au.base_id
        WHERE p.is_featured = 1
          AND p.parent_article_id IS NULL
          
        ORDER BY p.featured_order ASC, p.published_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Carousel fallback: no is_featured rows → use priority + breaking ─────
    if (empty($featuredArticles)) {
        $featuredArticles = $pdo->query("
            SELECT
                p.*,
                c.name                        AS category_name,
                COALESCE(au.update_count, 0)  AS developing_count,
                au.latest_update,
                COALESCE(au.breaking_count,0) AS has_breaking_updates
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN ($updateSubquery) AS au ON p.id = au.base_id
            WHERE p.parent_article_id IS NULL
              
            ORDER BY
                p.is_breaking DESC,
                COALESCE(au.update_count, 0) DESC,
                FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
                p.published_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Latest Articles (priority-weighted) ──────────────────────────────────
    $articles = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.parent_article_id IS NULL
          
        ORDER BY
            FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
            p.published_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // ── Trending (is_trending flag → 7-day engagement fallback → recency) ────
    $trendingArticles = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_trending = 1
          AND p.parent_article_id IS NULL
          
        ORDER BY p.engagement_score DESC, p.views DESC, p.published_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($trendingArticles)) {
        // Fallback 1: last 7 days by engagement
        $trendingArticles = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.parent_article_id IS NULL
              
              AND p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY p.engagement_score DESC, p.views DESC, p.published_at DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($trendingArticles)) {
        // Fallback 2: all-time most viewed
        $trendingArticles = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.parent_article_id IS NULL
              
            ORDER BY p.views DESC, p.published_at DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Editor's Picks (urgent/high priority or featured; random fallback) ───
    $editorPicks = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.parent_article_id IS NULL
          
          AND (p.priority IN ('urgent','high') OR p.is_featured = 1)
        ORDER BY FIELD(p.priority,'urgent','high','normal','low'), p.published_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($editorPicks)) {
        $editorPicks = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.parent_article_id IS NULL
              
            ORDER BY RAND()
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    $topStories = array_slice($articles, 0, 5);
    $headline   = $articles[0] ?? null;

} catch (PDOException $e) {
    // Log full error so you can see what actually failed
    error_log("[index.php] DB error: " . $e->getMessage() . " | Query hint: check column names match published_news schema");
    $breakingArticles = $flashArticles = $featuredArticles = [];
    $articles = $trendingArticles = $editorPicks = $topStories = [];
    $headline = null;
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
            .text-shadow    { text-shadow: 0 2px 4px rgba(0,0,0,0.3); }
            .text-shadow-lg { text-shadow: 0 4px 8px rgba(0,0,0,0.5); }
            .card-hover { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
            .card-hover:hover { transform: translateY(-4px); }
            html { scroll-behavior: smooth; }

            @keyframes shimmer {
                0%   { background-position: -1000px 0; }
                100% { background-position:  1000px 0; }
            }
            .skeleton {
                animation: shimmer 2s infinite linear;
                background: linear-gradient(to right, #f0f0f0 4%, #e0e0e0 25%, #f0f0f0 36%);
                background-size: 1000px 100%;
            }

            /* ⭐ Flash pulse */
            @keyframes flashPulse {
                0%, 100% { opacity: 1; }
                50%       { opacity: 0.4; }
            }
            .flash-pulse { animation: flashPulse 1s ease-in-out infinite; }

            @keyframes ticker {
                0%   { transform: translateX( 100%); }
                100% { transform: translateX(-100%); }
            }
            .ticker-content { animation: ticker 30s linear infinite; }

            @keyframes pulse-ring {
                0%   { transform: scale(0.95); opacity: 1; }
                50%  { transform: scale(1.05); opacity: 0.7; }
                100% { transform: scale(0.95); opacity: 1; }
            }
            .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }

            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to   { opacity: 1; transform: translateY(0); }
            }
            .fade-in  { animation: fadeIn 0.6s ease-out forwards; }
            .stagger-1 { animation-delay: 0.1s; }
            .stagger-2 { animation-delay: 0.2s; }
            .stagger-3 { animation-delay: 0.3s; }
            .stagger-4 { animation-delay: 0.4s; }
            .stagger-5 { animation-delay: 0.5s; }

            @keyframes float         { 0%,100%{transform:translateY(0)}  50%{transform:translateY(-8px)} }
            @keyframes float-delayed { 0%,100%{transform:translateY(0)}  50%{transform:translateY(-10px)} }
            .animate-float          { animation: float 5s ease-in-out infinite; }
            .animate-float-delayed  { animation: float-delayed 6s ease-in-out infinite; animation-delay: 0.8s; }

            @keyframes spin-slow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
            .animate-spin-slow { animation: spin-slow 30s linear infinite; }

            .scrollbar-hide::-webkit-scrollbar { display: none; }
            .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        }
    </style>

    <script>
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
                    borderRadius: { "DEFAULT":"0.375rem","lg":"0.625rem","xl":"0.875rem","2xl":"1.25rem","full":"9999px" },
                    boxShadow: {
                        'card':        '0 2px 8px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.05)',
                        'card-hover':  '0 12px 32px rgba(0,0,0,0.18), 0 4px 8px rgba(0,0,0,0.12)',
                        'button':      '0 4px 12px rgba(37,99,235,0.3)',
                        'button-hover':'0 6px 20px rgba(37,99,235,0.4)',
                        'feature':     '0 4px 16px rgba(0,0,0,0.12)',
                        'sidebar':     '0 2px 12px rgba(0,0,0,0.08)',
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

    <!-- ⭐ Breaking News Ticker — real is_breaking articles -->
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
                            <a href="article.php?id=<?= $ba['id'] ?>" class="text-sm font-semibold text-white hover:text-yellow-300 transition-colors">
                                <?= htmlspecialchars($ba['title']) ?>
                            </a>
                            <?php if ($i < count($breakingArticles) - 1): ?>
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
            <div class="flex items-center gap-3 flex-shrink-0">
                <div class="hidden sm:flex items-center gap-2 bg-white/90 px-3 py-1 rounded-sm">
                    <span class="material-symbols-outlined text-base">calendar_today</span>
                    <span id="phDate" class="text-xs font-semibold whitespace-nowrap"></span>
                </div>
                <div class="flex items-center gap-2 bg-white/90 px-3 py-1 rounded-sm">
                    <span class="material-symbols-outlined text-base">schedule</span>
                    <span id="phTime" class="text-sm font-bold whitespace-nowrap"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ⭐ Flash Reports Bar -->
    <?php if (!empty($flashArticles)): ?>
    <div class="bg-amber-500 py-1.5 overflow-hidden">
        <div class="max-w-[1600px] mx-auto px-8 flex items-center gap-3">
            <span class="flex items-center gap-1.5 font-bold text-xs uppercase flex-shrink-0 bg-black text-amber-400 px-2.5 py-1 rounded-full">
                <span class="material-symbols-outlined text-sm flash-pulse">bolt</span>
                Flash Report
            </span>
            <div class="overflow-hidden flex-1">
                <div class="ticker-content whitespace-nowrap" style="animation-duration:18s;">
                    <?php foreach ($flashArticles as $i => $fa): ?>
                        <a href="article.php?id=<?= $fa['id'] ?>" class="text-xs font-bold text-black hover:underline">
                            <?= htmlspecialchars($fa['title']) ?>
                        </a>
                        <?php if ($i < count($flashArticles) - 1): echo '<span class="mx-3 text-black/40">|</span>'; endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TopNavBar -->
    <header class="sticky top-0 z-40 border-b border-gray-200 dark:border-surface-dark bg-white/95 dark:bg-surface-dark/95 backdrop-blur-sm shadow-sm">
        <div class="max-w-[1600px] mx-auto px-8 py-3 flex items-center justify-between whitespace-nowrap">
            <div class="flex items-center gap-8">
                <a href="index.php" class="inline-block">
                    <img src="https://www.dzrh.com.ph/dzrh-logo.svg" alt="DZRH News"
                        class="h-8 w-auto drop-shadow-md transition-transform hover:scale-110 cursor-pointer">
                </a>
                <nav class="hidden lg:flex items-center gap-6">
                    <?php foreach ([['news.php','News'],['programs.php','Programs'],['schedule.php','On-Air Schedule'],['about_us.php','About Us']] as [$href,$label]): ?>
                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="<?= $href ?>">
                        <?= $label ?>
                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                    </a>
                    <?php endforeach; ?>
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
                <!-- notification dot: only show when breaking news exists -->
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
        <div id="mobileMenu" class="hidden lg:hidden border-t border-gray-200 dark:border-surface-dark bg-white dark:bg-surface-dark">
            <nav class="max-w-[1600px] mx-auto px-8 py-4 flex flex-col gap-3">
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="news.php">News</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="programs.php">Programs</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="schedule.php">On-Air Schedule</a>
                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="about_us.php">About Us</a>
            </nav>
        </div>
    </header>

    <main class="px-8 py-6 grid grid-cols-1 lg:grid-cols-4 gap-6 max-w-[1600px] mx-auto w-full">
        <!-- Main Content -->
        <div class="lg:col-span-3 flex flex-col gap-6">

            <!-- ⭐ Hero Carousel — is_featured ordered by featured_order, with developing/breaking/priority badges -->
            <?php if (!empty($featuredArticles)): ?>
            <div class="relative rounded-2xl overflow-hidden shadow-feature fade-in">
                <div id="heroCarousel" class="relative h-[500px] md:h-[600px]">
                    <?php foreach ($featuredArticles as $index => $article):
                        $thumb    = getThumbnail($article['thumbnail']);
                        $excerpt  = getExcerpt($article['content'], 180);
                        $devCount = (int)($article['developing_count'] ?? 0);
                        [$pBg, $pLabel] = priorityBadge($article['priority'] ?? 'normal');
                    ?>
                    <div class="carousel-slide <?= $index === 0 ? 'active opacity-100' : 'opacity-0' ?> absolute inset-0 bg-cover bg-center transition-opacity duration-1000"
                         style="background-image: linear-gradient(0deg, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.55) 40%, rgba(0,0,0,0) 100%), url('<?= $thumb ?>');"
                         data-index="<?= $index ?>"
                         data-article-id="<?= $article['id'] ?>"
                         data-dev-count="<?= $devCount ?>"
                         role="group" aria-roledescription="slide"
                         aria-label="<?= $index + 1 ?> of <?= count($featuredArticles) ?>">

                        <div class="absolute top-4 left-4 flex items-center gap-2 flex-wrap z-10">
                            <!-- Featured badge -->
                            <div class="bg-primary text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">star</span>Featured
                            </div>
                            <!-- ⭐ Is-breaking badge -->
                            <?php if (!empty($article['is_breaking'])): ?>
                            <div class="bg-red-600 text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1 pulse-ring">
                                <span class="material-symbols-outlined text-sm">campaign</span>Breaking
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Priority badge -->
                            <?php if ($pBg): ?>
                            <div class="<?= $pBg ?> text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg">
                                <?= $pLabel ?>
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Developing updates badge -->
                            <?php if ($devCount > 0): ?>
                            <div class="bg-accent-red text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1 animate-pulse">
                                <span class="material-symbols-outlined text-sm">update</span>
                                <?= $devCount ?> Update<?= $devCount > 1 ? 's' : '' ?>
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Has breaking chain -->
                            <?php if (($article['has_breaking_updates'] ?? 0) > 0): ?>
                            <div class="bg-yellow-500 text-gray-900 text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">warning</span>Breaking Updates
                            </div>
                            <?php endif; ?>
                            <div class="bg-black/60 backdrop-blur-sm text-white text-xs font-medium px-3 py-1.5 rounded-lg flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">schedule</span>
                                <?= timeAgo($article['published_at']) ?>
                            </div>
                        </div>

                        <?php if (!empty($article['category_name'])): ?>
                        <div class="absolute top-4 right-4 bg-white/90 dark:bg-surface-dark/90 backdrop-blur-sm text-primary text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg z-10">
                            <?= htmlspecialchars($article['category_name']) ?>
                        </div>
                        <?php endif; ?>

                        <div class="absolute bottom-0 inset-x-0 p-4 md:p-6 flex flex-col md:flex-row items-start md:items-end justify-between gap-4 z-10">
                            <div class="flex max-w-2xl flex-1 flex-col gap-3">
                                <h2 class="text-white text-2xl md:text-4xl font-bold leading-tight text-shadow-lg">
                                    <?= htmlspecialchars($article['title']) ?>
                                </h2>
                                <p class="text-gray-100 text-sm md:text-base font-medium leading-relaxed text-shadow-lg">
                                    <?= $excerpt ?>
                                </p>
                                <!-- ⭐ Author + views + latest update -->
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-300">
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
                                <?php if (!empty($article['latest_update'])): ?>
                                <div class="flex items-center gap-2 text-yellow-300">
                                    <span class="w-2 h-2 bg-yellow-300 rounded-full animate-pulse"></span>
                                    <span class="text-xs font-semibold">Latest update: <?= timeAgo($article['latest_update']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <a href="article.php?id=<?= $article['id'] ?>"
                               class="flex min-w-[84px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-12 px-8 bg-primary hover:bg-primary-dark text-white text-sm font-bold leading-normal tracking-[0.015em] shrink-0 shadow-button hover:shadow-button-hover transition-all duration-300 transform hover:scale-105 group"
                               aria-label="Read more about <?= htmlspecialchars($article['title']) ?>">
                                <span class="truncate">Read More</span>
                                <span class="material-symbols-outlined ml-2 transition-transform group-hover:translate-x-1">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button id="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-3 rounded-full transition-all duration-300 hover:scale-110 z-20 shadow-lg focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Previous slide">
                    <span class="material-symbols-outlined text-2xl">chevron_left</span>
                </button>
                <button id="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 bg-white/20 hover:bg-white/30 backdrop-blur-sm text-white p-3 rounded-full transition-all duration-300 hover:scale-110 z-20 shadow-lg focus:outline-none focus:ring-2 focus:ring-primary" aria-label="Next slide">
                    <span class="material-symbols-outlined text-2xl">chevron_right</span>
                </button>
                <div class="absolute bottom-20 left-1/2 -translate-x-1/2 flex items-center gap-2 z-20" role="group" aria-label="Slide indicators">
                    <?php foreach ($featuredArticles as $index => $item): ?>
                    <button class="carousel-indicator w-2 h-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-primary w-8' : 'bg-white/50 hover:bg-white/80' ?>"
                            data-index="<?= $index ?>" aria-label="Go to slide <?= $index + 1 ?>"
                            aria-current="<?= $index === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-white dark:bg-surface-dark rounded-2xl p-8 shadow-card text-center">
                <span class="material-symbols-outlined text-6xl text-gray-300 mb-4 block">article</span>
                <h3 class="text-xl font-bold mb-2">No Featured Stories</h3>
                <p class="text-gray-500">Check back later for breaking news and updates</p>
            </div>
            <?php endif; ?>

            <!-- ⭐ Editor's Picks — high priority / featured articles -->
            <?php if (!empty($editorPicks)): ?>
            <div class="bg-white dark:bg-surface-dark rounded-2xl p-6 shadow-card fade-in stagger-2">
                <div class="flex items-center justify-between pb-4 pt-2 border-b-2 border-accent-yellow mb-6">
                    <h2 class="text-2xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-yellow">edit_note</span>
                        Editor's Picks
                    </h2>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <?php foreach ($editorPicks as $pick):
                        $thumb = getThumbnail($pick['thumbnail'], 'https://via.placeholder.com/400x200?text=No+Image');
                        [$pBg, $pLabel] = priorityBadge($pick['priority'] ?? 'normal');
                    ?>
                    <a href="article.php?id=<?= $pick['id'] ?>" class="flex flex-col gap-3 group card-hover">
                        <div class="w-full bg-center bg-no-repeat aspect-video bg-cover rounded-xl shadow-card group-hover:shadow-card-hover overflow-hidden relative"
                             style="background-image: url('<?= $thumb ?>');">
                            <?php if (!empty($pick['category_name'])): ?>
                            <div class="absolute top-2 left-2 bg-accent-yellow text-gray-900 text-xs font-bold uppercase px-2 py-1 rounded shadow-lg">
                                <?= htmlspecialchars($pick['category_name']) ?>
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Status badges -->
                            <div class="absolute top-2 right-2 flex flex-col gap-1">
                                <?php if (!empty($pick['is_breaking'])): ?>
                                <span class="bg-red-600 text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded pulse-ring">Breaking</span>
                                <?php endif; ?>
                                <?php if ($pBg): ?>
                                <span class="<?= $pBg ?> text-white text-[10px] font-bold uppercase px-2 py-0.5 rounded"><?= $pLabel ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold leading-tight group-hover:text-primary transition-colors mb-2 line-clamp-2">
                                <?= htmlspecialchars($pick['title']) ?>
                            </p>
                            <div class="flex items-center justify-between text-xs text-text-muted-light dark:text-text-muted-dark">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    <?= timeAgo($pick['published_at']) ?>
                                </span>
                                <?php if ($v = formatViews($pick['views'] ?? 0)): ?>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">visibility</span>
                                    <?= $v ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ⭐ Trending Now — is_trending + engagement_score -->
            <?php if (!empty($trendingArticles)): ?>
            <div class="bg-white dark:bg-surface-dark rounded-2xl p-6 shadow-card fade-in stagger-3">
                <div class="flex items-center justify-between pb-4 pt-2 border-b-2 border-accent-red mb-6">
                    <h2 class="text-2xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-red">trending_up</span>
                        Trending Now
                    </h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
                    <?php foreach ($trendingArticles as $trend):
                        $thumb = getThumbnail($trend['thumbnail'], 'https://via.placeholder.com/400x200?text=No+Image');
                        [$pBg, $pLabel] = priorityBadge($trend['priority'] ?? 'normal');
                    ?>
                    <a href="article.php?id=<?= $trend['id'] ?>" class="flex gap-3 group card-hover p-3 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        <div class="w-20 h-20 bg-center bg-no-repeat bg-cover rounded-lg shadow-sm flex-shrink-0 relative overflow-hidden"
                             style="background-image: url('<?= $thumb ?>');">
                            <?php if (!empty($trend['is_breaking'])): ?>
                            <span class="absolute top-1 left-1 w-2 h-2 bg-red-500 rounded-full pulse-ring"></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm leading-tight group-hover:text-primary transition-colors mb-1.5 line-clamp-2">
                                <?= htmlspecialchars($trend['title']) ?>
                            </p>
                            <div class="flex flex-wrap items-center gap-2 text-[10px] text-text-muted-light dark:text-text-muted-dark">
                                <span class="flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-xs">schedule</span>
                                    <?= timeAgo($trend['published_at']) ?>
                                </span>
                                <?php if ($v = formatViews($trend['views'] ?? 0)): ?>
                                <span class="flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-xs">visibility</span><?= $v ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($trend['engagement_score'])): ?>
                                <span class="flex items-center gap-0.5 text-orange-500 font-semibold">
                                    <span class="material-symbols-outlined text-xs">whatshot</span><?= $trend['engagement_score'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($pBg): ?>
                                <span class="<?= $pBg ?> text-white px-1.5 py-0.5 rounded text-[9px] font-bold"><?= $pLabel ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ⭐ Latest News — priority-sorted, with all badges + author + views -->
            <div class="bg-white dark:bg-surface-dark rounded-2xl p-6 shadow-card fade-in stagger-4">
                <div class="flex items-center justify-between pb-4 pt-2 border-b-2 border-primary mb-6">
                    <h2 class="text-2xl font-bold leading-tight tracking-[-0.015em]">Latest News</h2>
                    <div class="flex items-center gap-2">
                        <button id="viewModeGrid" class="p-2 rounded-lg bg-primary text-white transition-colors">
                            <span class="material-symbols-outlined">grid_view</span>
                        </button>
                        <button id="viewModeList" class="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                            <span class="material-symbols-outlined">view_list</span>
                        </button>
                    </div>
                </div>
                <div id="articlesContainer" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php foreach (array_slice($articles, 0, 12) as $article):
                        $thumb = getThumbnail($article['thumbnail'], 'https://via.placeholder.com/400x200?text=No+Image');
                        [$pBg, $pLabel] = priorityBadge($article['priority'] ?? 'normal');
                    ?>
                    <a href="article.php?id=<?= $article['id'] ?>" 
                       class="article-card flex flex-col gap-3 group card-hover"
                       data-category="<?= strtolower($article['category_name'] ?? 'all') ?>">
                        <div class="w-full bg-center bg-no-repeat aspect-video bg-cover rounded-xl shadow-card group-hover:shadow-card-hover overflow-hidden relative"
                             style="background-image: url('<?= $thumb ?>');">
                            <!-- Hover overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-end p-3">
                                <span class="text-white text-xs font-semibold flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">visibility</span>Read Article
                                </span>
                            </div>
                            <!-- Category -->
                            <?php if (!empty($article['category_name'])): ?>
                            <div class="absolute top-2 left-2 bg-primary text-white text-xs font-bold uppercase px-2 py-1 rounded shadow-lg z-10">
                                <?= htmlspecialchars($article['category_name']) ?>
                            </div>
                            <?php endif; ?>
                            <!-- ⭐ Status badges top-right -->
                            <div class="absolute top-2 right-2 flex flex-col gap-1 z-10">
                                <?php if (!empty($article['is_breaking'])): ?>
                                <span class="bg-red-600 text-white text-[10px] font-bold uppercase px-1.5 py-0.5 rounded pulse-ring">🔴 Breaking</span>
                                <?php endif; ?>
                                <?php if (!empty($article['is_flash'])): ?>
                                <span class="bg-amber-500 text-black text-[10px] font-bold uppercase px-1.5 py-0.5 rounded flash-pulse">⚡ Flash</span>
                                <?php endif; ?>
                                <?php if (!empty($article['is_trending'])): ?>
                                <span class="bg-orange-500 text-white text-[10px] font-bold uppercase px-1.5 py-0.5 rounded">🔥 Hot</span>
                                <?php endif; ?>
                                <?php if ($pBg): ?>
                                <span class="<?= $pBg ?> text-white text-[10px] font-bold uppercase px-1.5 py-0.5 rounded"><?= $pLabel ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div>
                            <p class="font-semibold leading-tight group-hover:text-primary transition-colors mb-2 line-clamp-2">
                                <?= htmlspecialchars($article['title']) ?>
                            </p>
                            <!-- ⭐ Time + views row -->
                            <div class="flex items-center justify-between text-xs text-text-muted-light dark:text-text-muted-dark mb-1">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">schedule</span>
                                    <?= timeAgo($article['published_at']) ?>
                                </span>
                                <?php if ($v = formatViews($article['views'] ?? 0)): ?>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-sm">visibility</span><?= $v ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <!-- ⭐ Author + shares -->
                            <div class="flex items-center justify-between text-[10px] text-text-muted-light dark:text-text-muted-dark border-t border-gray-100 dark:border-gray-700 pt-1.5 mt-1">
                                <span class="flex items-center gap-1 truncate max-w-[65%]">
                                    <span class="material-symbols-outlined text-xs"><?= !empty($article['author']) ? 'person' : 'newspaper' ?></span>
                                    <span class="truncate"><?= !empty($article['author']) ? htmlspecialchars($article['author']) : 'DZRH News' ?></span>
                                </span>
                                <?php if (!empty($article['shares'])): ?>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">share</span>
                                    <?= formatViews($article['shares']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="mt-6 text-center">
                    <a href="news.php" class="inline-flex items-center gap-2 px-6 py-3 bg-primary hover:bg-primary-dark text-white font-semibold rounded-xl transition-all duration-300 hover:shadow-button transform hover:scale-105 group">
                        <span>See More Articles</span>
                        <span class="material-symbols-outlined transition-transform group-hover:translate-x-1">arrow_forward</span>
                    </a>
                </div>
            </div>

            <!-- Video Section -->
            <div class="bg-gradient-to-br from-gray-900 to-black rounded-2xl p-6 shadow-card text-white">
                <div class="flex items-center justify-between pb-4 border-b-2 border-accent-red mb-6">
                    <h2 class="text-2xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-red">play_circle</span>
                        Latest Videos
                    </h2>
                    <a href="https://www.youtube.com/@DZRHTV" target="_blank" rel="noopener noreferrer"
                       class="text-sm text-accent-yellow font-semibold hover:underline flex items-center gap-1 transition-all duration-300 hover:text-yellow-300">
                        View All on YouTube
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </a>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ([
                        ['https://www.youtube.com/watch?v=R92jFBOQFbs', 'DZRH News Television', 'Watch Live • DZRH 666 kHz'],
                        ['https://www.youtube.com/watch?v=gmT1B4Gi7NI', 'Latest News & Updates',  'View Latest Videos • DZRH News'],
                    ] as [$url, $title, $sub]): ?>
                    <a href="<?= $url ?>" target="_blank" rel="noopener noreferrer"
                       class="relative rounded-xl overflow-hidden group cursor-pointer transform hover:scale-105 transition-all duration-300">
                        <img src="https://i.ytimg.com/vi/Y4Q6DwEwQ5Q/maxresdefault.jpg"
                             alt="<?= $title ?>" class="w-full h-48 object-cover"
                             onerror="this.src='https://via.placeholder.com/600x400?text=DZRH'">
                        <div class="absolute inset-0 bg-black/40 group-hover:bg-black/60 transition-colors flex items-center justify-center">
                            <div class="w-16 h-16 rounded-full bg-accent-red/90 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <span class="material-symbols-outlined text-4xl">play_arrow</span>
                            </div>
                        </div>
                        <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black to-transparent">
                            <p class="text-white font-semibold text-sm"><?= $title ?></p>
                            <p class="text-gray-300 text-xs mt-1"><?= $sub ?></p>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="mt-4 pt-4 border-t border-gray-700 flex items-center justify-between">
                    <p class="text-sm text-gray-400">Stay updated with our latest broadcasts</p>
                    <a href="https://www.youtube.com/@DZRHTV" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg transition-all duration-300 transform hover:scale-105">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                        Subscribe
                    </a>
                </div>
            </div>

        </div><!-- /main content -->

        <!-- Sidebar -->
        <aside class="lg:col-span-1 flex flex-col gap-6">

            <!-- ⭐ Developing Stories — loads updates via AJAX per carousel slide -->
            <div class="flex flex-col gap-4 bg-white dark:bg-surface-dark rounded-2xl p-5 shadow-card fade-in stagger-4">
                <div class="flex items-center justify-between border-b-2 border-primary pb-3">
                    <h3 class="text-xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-accent-red">trending_up</span>
                        <span id="developingTitle">Developing Stories</span>
                    </h3>
                    <div id="updateIndicator" class="hidden animate-pulse">
                        <span class="flex items-center gap-1 text-xs font-medium text-primary">
                            <span class="material-symbols-outlined text-sm">sync</span>Live
                        </span>
                    </div>
                </div>
                <div id="developingStoriesContainer" class="min-h-[200px]">
                    <ul id="developingStoriesList" class="space-y-4">
                        <li class="text-center py-8 text-gray-400">
                            <span class="material-symbols-outlined text-4xl mb-2 block">article</span>
                            <p class="text-sm">Select a featured story to see developing updates</p>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Traffic & Weather Widgets -->
            <div class="grid grid-cols-1 gap-3">
                <!-- Traffic -->
                <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-700 shadow-md group cursor-pointer transition-all duration-300 hover:shadow-lg hover:scale-[1.01]">
                    <a href="https://www.waze.com/live-map" target="_blank" class="block p-3">
                        <div class="absolute inset-0 opacity-10">
                            <div class="absolute inset-0" style="background-image: repeating-linear-gradient(45deg,transparent,transparent 8px,rgba(255,255,255,.1) 8px,rgba(255,255,255,.1) 16px);"></div>
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

                <!-- Weather -->
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
                                <div class="w-14 h-14 flex items-center justify-center" id="weatherIcon">
                                    <svg class="w-12 h-12 text-yellow-300 animate-spin-slow" fill="currentColor" viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="5"/>
                                        <path d="M12 1v2m0 18v2M4.22 4.22l1.42 1.42m12.72 12.72l1.42 1.42M1 12h2m18 0h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                                    </svg>
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

            <!-- Quick Stats -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 shadow-card">
                    <div class="w-8 h-8 bg-primary rounded-lg flex items-center justify-center mb-2"><span class="material-symbols-outlined text-white text-lg">article</span></div>
                    <p class="text-2xl font-bold text-primary"><?= count($articles) ?>+</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Articles</p>
                </div>
                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-xl p-4 shadow-card">
                    <div class="w-8 h-8 bg-accent-yellow rounded-lg flex items-center justify-center mb-2"><span class="material-symbols-outlined text-gray-900 text-lg">visibility</span></div>
                    <p class="text-2xl font-bold text-accent-yellow-dark">1.2M</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Views</p>
                </div>
                <div class="bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/20 dark:to-red-800/20 rounded-xl p-4 shadow-card">
                    <div class="w-8 h-8 bg-accent-red rounded-lg flex items-center justify-center mb-2"><span class="material-symbols-outlined text-white text-lg">radio</span></div>
                    <p class="text-2xl font-bold text-accent-red">24/7</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Live Radio</p>
                </div>
                <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 shadow-card">
                    <div class="w-8 h-8 bg-green-600 rounded-lg flex items-center justify-center mb-2"><span class="material-symbols-outlined text-white text-lg">verified</span></div>
                    <p class="text-2xl font-bold text-green-600">75+</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400">Years</p>
                </div>
            </div>

            <!-- Newsletter -->
            <div class="flex flex-col gap-4 bg-gradient-to-br from-accent-yellow to-accent-yellow-dark text-gray-900 rounded-2xl p-5 shadow-card">
                <h3 class="text-lg font-bold">Stay Updated</h3>
                <p class="text-sm opacity-90">Get the latest news delivered to your inbox</p>
                <form id="newsletterForm" class="flex gap-2">
                    <input type="email" id="newsletterEmail" placeholder="Enter your email"
                           class="flex-1 px-4 py-2 rounded-lg text-text-light bg-white focus:outline-none focus:ring-2 focus:ring-gray-900" required>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white font-semibold rounded-lg hover:bg-gray-800 transition-colors">
                        <span class="material-symbols-outlined">send</span>
                    </button>
                </form>
            </div>

            <!-- ⭐ Top Stories — with views + engagement_score -->
            <?php if (!empty($topStories)): ?>
            <div class="flex flex-col gap-4 bg-white dark:bg-surface-dark rounded-2xl p-5 shadow-card fade-in stagger-5">
                <div class="flex items-center justify-between border-b-2 border-primary pb-3">
                    <h3 class="text-xl font-bold leading-tight tracking-[-0.015em] flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">star</span>
                        Top Stories
                    </h3>
                </div>
                <ul class="space-y-4">
                    <?php $rank = 1; foreach ($topStories as $story): ?>
                    <li class="flex items-start gap-3 group pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg transition-colors">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-primary to-primary-dark text-white font-extrabold text-sm flex-shrink-0 shadow-md">
                            <?= $rank++ ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <a class="text-sm font-semibold leading-tight group-hover:text-primary transition-colors block mb-1"
                               href="article.php?id=<?= $story['id'] ?>">
                                <?= htmlspecialchars($story['title']) ?>
                            </a>
                            <!-- ⭐ Views + engagement_score -->
                            <div class="flex items-center gap-3 text-[10px] text-text-muted-light dark:text-text-muted-dark">
                                <?php if ($v = formatViews($story['views'] ?? 0)): ?>
                                <span class="flex items-center gap-0.5">
                                    <span class="material-symbols-outlined text-xs">visibility</span><?= $v ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($story['engagement_score'])): ?>
                                <span class="flex items-center gap-0.5 text-orange-500 font-semibold">
                                    <span class="material-symbols-outlined text-xs">whatshot</span><?= $story['engagement_score'] ?>
                                </span>
                                <?php endif; ?>
                                <?php if (!empty($story['is_breaking'])): ?>
                                <span class="text-red-500 font-bold">● Breaking</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

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
                    <div class="size-10 text-primary drop-shadow-md"><img src="https://www.dzrh.com.ph/dzrh-logo.svg" alt="DZRH"/></div>
                    <h2 class="text-xl font-bold">DZRH News</h2>
                </div>
                <p class="text-sm text-gray-300 leading-relaxed mb-4">Your trusted source for breaking news, in-depth analysis, and live radio broadcasts since 1950.</p>
                <div class="flex space-x-3">
                    <?php
                    $socials = [
                        ['#','M22.675 0h-21.35C.59 0 0 .59 0 1.325v21.35C0 23.41.59 24 1.325 24H12.82v-9.29H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116c.735 0 1.325-.59 1.325-1.325V1.325C24 .59 23.409 0 22.675 0z','hover:bg-primary'],
                        ['#','M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.223.085c.645 1.956 2.52 3.379 4.738 3.419-1.914 1.493-4.32 2.387-6.94 2.387-.452 0-.898-.027-1.336-.079a13.97 13.97 0 007.548 2.212c9.058 0 14.01-7.502 14.01-14.01 0-.213 0-.425-.015-.636A10.016 10.016 0 0024 4.59z','hover:bg-primary'],
                    ];
                    foreach ($socials as [$href, $d, $hov]): ?>
                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 <?= $hov ?> hover:text-white transition-all transform hover:scale-110" href="<?= $href ?>">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="<?= $d ?>"></path></svg>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <h4 class="text-lg font-bold mb-4 text-white">Quick Links</h4>
                <ul class="space-y-2 text-sm">
                    <?php foreach (['About Us','Our Team','Careers','Advertise'] as $link): ?>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                        <span class="material-symbols-outlined text-sm">chevron_right</span><?= $link ?>
                    </a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4 class="text-lg font-bold mb-4 text-white">Categories</h4>
                <ul class="space-y-2 text-sm">
                    <?php foreach (['Politics','Business','Sports','Entertainment'] as $cat): ?>
                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                        <span class="material-symbols-outlined text-sm">chevron_right</span><?= $cat ?>
                    </a></li>
                    <?php endforeach; ?>
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
                <button id="ytExpand" class="text-white hover:bg-white/20 rounded p-1 sm:p-1.5 transition-colors"><span class="material-symbols-outlined text-base sm:text-lg">open_in_full</span></button>
                <button id="ytMinimize" class="text-white hover:bg-white/20 rounded p-1 sm:p-1.5 transition-colors"><span class="material-symbols-outlined text-base sm:text-lg">close</span></button>
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
    <div id="ytBackdrop" class="absolute inset-0 bg-black/95 backdrop-blur-sm"></div>
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
                <button id="ytPip" class="hidden md:flex items-center gap-2 px-3 py-1.5 sm:px-4 sm:py-2 bg-white/10 hover:bg-white/20 text-white rounded-lg transition-colors">
                    <span class="material-symbols-outlined text-base sm:text-lg">picture_in_picture</span>
                    <span class="text-xs sm:text-sm font-medium hidden lg:inline">Mini Player</span>
                </button>
                <button id="ytModalClose" class="text-white hover:bg-white/20 rounded-lg p-1.5 sm:p-2 transition-colors">
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
                <?php foreach ([
                    ['accent-red','radio','Current Program','Morning Balita'],
                    ['primary','schedule','Broadcasting','6:00 AM - 8:00 AM'],
                    ['green-500 bg-green-600/20','signal_cellular_alt','Stream Quality','HD • 1080p'],
                ] as [$color, $icon, $label, $val]): ?>
                <div class="bg-white/5 backdrop-blur-sm rounded-lg sm:rounded-xl p-3 sm:p-4 border border-white/10">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-<?= $color ?>/20 flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-<?= $color ?> text-lg sm:text-xl"><?= $icon ?></span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-gray-400 text-[10px] sm:text-xs truncate"><?= $label ?></p>
                            <p class="text-white text-xs sm:text-sm font-semibold truncate"><?= $val ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT — Developing Stories + Carousel
     ============================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentArticleId = null;

    // ⭐ Load developing stories via AJAX (passes is_breaking, is_trending, is_flash, views, source)
    async function loadDevelopingStories(articleId) {
        if (currentArticleId === articleId) return;
        currentArticleId = articleId;

        const list      = document.getElementById('developingStoriesList');
        const title     = document.getElementById('developingTitle');
        const indicator = document.getElementById('updateIndicator');
        if (!list || !title || !indicator) return;

        list.innerHTML = `
            <li class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                <p class="text-sm text-gray-500 mt-2">Loading updates...</p>
            </li>`;

        try {
            const res  = await fetch(`get_developing_stories.php?article_id=${articleId}`);
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();

            if (data.success && data.stories && data.stories.length > 0) {
                const short = data.articleTitle.length > 30
                    ? data.articleTitle.substring(0, 30) + '...'
                    : data.articleTitle;
                title.textContent = `Updates: ${short}`;
                indicator.classList.remove('hidden');

                list.innerHTML = data.stories.map((story, i) => {
                    const badges = [];
                    if (story.is_breaking)  badges.push('<span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded pulse-ring">BREAKING</span>');
                    if (story.is_trending)  badges.push('<span class="px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded">🔥 TRENDING</span>');
                    if (story.is_flash)     badges.push('<span class="px-2 py-0.5 bg-amber-500 text-black text-xs font-bold rounded flash-pulse">⚡ FLASH</span>');
                    if (story.update_type)  badges.push(`<span class="px-2 py-0.5 bg-blue-500 text-white text-xs font-bold rounded">${esc(story.update_type).toUpperCase()}</span>`);
                    // ⭐ Priority badge
                    if (story.priority === 'urgent') badges.push('<span class="px-2 py-0.5 bg-red-700 text-white text-xs font-bold rounded">URGENT</span>');
                    if (story.priority === 'high')   badges.push('<span class="px-2 py-0.5 bg-orange-600 text-white text-xs font-bold rounded">HIGH</span>');

                    return `
                        <li class="flex items-start gap-3 group pb-3 border-b border-gray-100 dark:border-gray-700 last:border-0 hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded-lg transition-all">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-gradient-to-br from-primary to-primary-dark text-white font-extrabold text-sm flex-shrink-0 shadow-md">${i+1}</div>
                            <div class="flex-1 min-w-0">
                                ${badges.length ? `<div class="flex items-center gap-1.5 mb-1 flex-wrap">${badges.join('')}</div>` : ''}
                                <a class="text-sm font-semibold leading-tight group-hover:text-primary transition-colors block mb-1"
                                   href="article.php?id=${story.id}">${esc(story.title)}</a>
                                <div class="flex flex-wrap items-center gap-3 text-[10px] text-gray-500 dark:text-gray-400">
                                    <span class="flex items-center gap-0.5">
                                        <span class="material-symbols-outlined text-xs">schedule</span>${story.time_ago}
                                    </span>
                                    ${story.views ? `<span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-xs">visibility</span>${fmtNum(story.views)}</span>` : ''}
                                    ${story.engagement_score ? `<span class="flex items-center gap-0.5 text-orange-500 font-semibold"><span class="material-symbols-outlined text-xs">whatshot</span>${story.engagement_score}</span>` : ''}
                                    ${story.source ? `<span class="flex items-center gap-0.5"><span class="material-symbols-outlined text-xs">source</span>${esc(story.source)}</span>` : ''}
                                </div>
                            </div>
                        </li>`;
                }).join('');
            } else {
                title.textContent = 'Developing Stories';
                indicator.classList.add('hidden');
                list.innerHTML = `
                    <li class="text-center py-8 text-gray-400">
                        <span class="material-symbols-outlined text-4xl mb-2 block opacity-50">check_circle</span>
                        <p class="text-sm">No developing updates for this story yet</p>
                    </li>`;
            }
        } catch (err) {
            console.error('Developing stories error:', err);
            title.textContent = 'Developing Stories';
            indicator.classList.add('hidden');
            list.innerHTML = `
                <li class="text-center py-8 text-red-400">
                    <span class="material-symbols-outlined text-4xl mb-2 block">error</span>
                    <p class="text-sm">Failed to load updates</p>
                </li>`;
        }
    }

    function esc(t) {
        if (!t) return '';
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function fmtNum(n) {
        if (!n) return '0';
        if (n >= 1e6) return (n/1e6).toFixed(1)+'M';
        if (n >= 1e3) return (n/1e3).toFixed(1)+'K';
        return String(n);
    }

    // Carousel
    const slides     = document.querySelectorAll('.carousel-slide');
    const indicators = document.querySelectorAll('.carousel-indicator');
    let current = 0;
    let autoPlay;

    function showSlide(idx) {
        if (!slides.length) return;
        slides.forEach(s => { s.classList.remove('active','opacity-100'); s.classList.add('opacity-0'); });
        indicators.forEach((ind, i) => {
            if (i === idx) { ind.classList.remove('bg-white/50','w-2'); ind.classList.add('bg-primary','w-8'); ind.setAttribute('aria-current','true'); }
            else           { ind.classList.remove('bg-primary','w-8'); ind.classList.add('bg-white/50','w-2'); ind.setAttribute('aria-current','false'); }
        });
        if (slides[idx]) { slides[idx].classList.add('active','opacity-100'); slides[idx].classList.remove('opacity-0'); }
        current = idx;
        const id = slides[idx]?.dataset?.articleId;
        if (id) loadDevelopingStories(id);
    }

    const next = () => { if (slides.length) showSlide((current+1) % slides.length); };
    const prev = () => { if (slides.length) showSlide((current-1+slides.length) % slides.length); };

    const resetAP = () => { clearInterval(autoPlay); autoPlay = setInterval(next, 5000); };

    document.getElementById('nextSlide')?.addEventListener('click', () => { next(); resetAP(); });
    document.getElementById('prevSlide')?.addEventListener('click', () => { prev(); resetAP(); });
    indicators.forEach(ind => ind.addEventListener('click', () => { showSlide(parseInt(ind.dataset.index)); resetAP(); }));

    const carousel = document.getElementById('heroCarousel');
    if (carousel) {
        carousel.addEventListener('mouseenter', () => clearInterval(autoPlay));
        carousel.addEventListener('mouseleave', resetAP);
    }

    if (slides.length > 0) {
        const initId = slides[0].dataset?.articleId;
        if (initId) loadDevelopingStories(initId);
        autoPlay = setInterval(next, 5000);
    }

    // Refresh developing stories every 30s
    setInterval(() => { if (currentArticleId) loadDevelopingStories(currentArticleId); }, 30000);
});
</script>

<script src="script.js"></script>
</body>
</html>