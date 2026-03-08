<?php
/**
 * DZRH News — Data Layer
 * All queries are MySQL 5.7+ compatible (no CTEs).
 */

// Counts child articles per parent — reused across multiple queries.
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

// Root-article base WHERE, keeps all queries consistent.
define('ROOT_ARTICLE', 'parent_article_id IS NULL');

try {

    // Breaking ticker
    $breakingArticles = $pdo->query("
        SELECT id, title
        FROM published_news
        WHERE is_breaking = 1
          AND (breaking_until IS NULL OR breaking_until > NOW())
          AND " . ROOT_ARTICLE . "
        ORDER BY published_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Flash bar
    $flashArticles = $pdo->query("
        SELECT p.id, p.title, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_flash = 1
          AND (p.flash_until IS NULL OR p.flash_until > NOW())
          AND p." . ROOT_ARTICLE . "
        ORDER BY p.published_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Featured carousel
    $featuredArticles = $pdo->query("
        SELECT p.*, c.name AS category_name,
               COALESCE(au.update_count,  0) AS developing_count,
               au.latest_update,
               COALESCE(au.breaking_count,0) AS has_breaking_updates
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN ({$updateSubquery}) AS au ON p.id = au.base_id
        WHERE p.is_featured = 1
          AND p." . ROOT_ARTICLE . "
        ORDER BY p.featured_order ASC, p.published_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Carousel fallback: no is_featured → priority + activity
    if (empty($featuredArticles)) {
        $featuredArticles = $pdo->query("
            SELECT p.*, c.name AS category_name,
                   COALESCE(au.update_count,  0) AS developing_count,
                   au.latest_update,
                   COALESCE(au.breaking_count,0) AS has_breaking_updates
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN ({$updateSubquery}) AS au ON p.id = au.base_id
            WHERE p." . ROOT_ARTICLE . "
            ORDER BY
                p.is_breaking DESC,
                COALESCE(au.update_count, 0) DESC,
                FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
                p.published_at DESC
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Latest news (priority-weighted)
    $articles = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p." . ROOT_ARTICLE . "
        ORDER BY
            FIELD(p.priority, 'urgent', 'high', 'normal', 'low'),
            p.published_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Trending — flag → 7-day engagement → all-time views
    $trendingArticles = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.is_trending = 1
          AND p." . ROOT_ARTICLE . "
        ORDER BY p.engagement_score DESC, p.views DESC, p.published_at DESC
        LIMIT 6
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($trendingArticles)) {
        $trendingArticles = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p." . ROOT_ARTICLE . "
              AND p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ORDER BY p.engagement_score DESC, p.views DESC, p.published_at DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($trendingArticles)) {
        $trendingArticles = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p." . ROOT_ARTICLE . "
            ORDER BY p.views DESC, p.published_at DESC
            LIMIT 6
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Editor's picks — high-priority / featured, random fallback
    $editorPicks = $pdo->query("
        SELECT p.*, c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p." . ROOT_ARTICLE . "
          AND (p.priority IN ('urgent','high') OR p.is_featured = 1)
        ORDER BY FIELD(p.priority, 'urgent', 'high', 'normal', 'low'), p.published_at DESC
        LIMIT 3
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($editorPicks)) {
        $editorPicks = $pdo->query("
            SELECT p.*, c.name AS category_name
            FROM published_news p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p." . ROOT_ARTICLE . "
            ORDER BY RAND()
            LIMIT 3
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    // Derived sets
    $topStories = array_slice($articles, 0, 5);
    $headline   = $articles[0] ?? null;

    // Developing stories index — all parents with at least one child update
    $developingIndex = $pdo->query("
        SELECT p.id, p.title, p.published_at, p.is_breaking, p.is_flash, p.priority,
               c.name AS category_name,
               au.update_count, au.latest_update, au.breaking_count
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        INNER JOIN ({$updateSubquery}) AS au ON p.id = au.base_id
        WHERE p." . ROOT_ARTICLE . "
        ORDER BY au.latest_update DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[DZRH] DB error: ' . $e->getMessage());
    $breakingArticles = $flashArticles = $featuredArticles = $developingIndex = [];
    $articles = $trendingArticles = $editorPicks = $topStories = [];
    $headline = null;
}