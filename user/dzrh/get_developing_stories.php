<?php
/**
 * get_developing_stories.php
 * AJAX endpoint — returns all child/update articles for a given parent article ID.
 *
 * GET params:
 *   article_id (int, required) — the parent (featured) article whose updates to fetch
 *
 * Response: JSON
 *   { success: true,  articleTitle: "...", stories: [ {...}, ... ] }
 *   { success: false, message: "..." }
 */

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require dirname(__DIR__, 2) . '/db.php';

// ── Validate input ────────────────────────────────────────────────────────────
$articleId = filter_input(INPUT_GET, 'article_id', FILTER_VALIDATE_INT);
if (!$articleId || $articleId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid article_id']);
    exit;
}

// ── Helper ────────────────────────────────────────────────────────────────────
function timeAgoAjax(string $timestamp): string {
    if (!$timestamp) return 'Just now';
    $diff = time() - strtotime($timestamp);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M d, Y g:i A', strtotime($timestamp));
}

try {
    // ── Fetch the parent article title ────────────────────────────────────────
    $parentStmt = $pdo->prepare("
        SELECT id, title
        FROM published_news
        WHERE id = ?
          AND parent_article_id IS NULL
        LIMIT 1
    ");
    $parentStmt->execute([$articleId]);
    $parent = $parentStmt->fetch(PDO::FETCH_ASSOC);

    if (!$parent) {
        echo json_encode(['success' => false, 'message' => 'Parent article not found']);
        exit;
    }

    // ── Fetch child/update articles ───────────────────────────────────────────
    // Children are rows where parent_article_id = $articleId
    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.title,
            p.content,
            p.thumbnail,
            p.published_at,
            p.updated_at,
            p.is_breaking,
            p.is_trending,
            p.is_flash,
            p.priority,
            p.views,
            p.engagement_score,
            p.source,
            p.author,
            p.update_type,
            c.name AS category_name
        FROM published_news p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.parent_article_id = ?
        ORDER BY p.published_at DESC
        LIMIT 30
    ");
    $stmt->execute([$articleId]);
    $updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Shape the response ────────────────────────────────────────────────────
    $stories = array_map(function (array $row): array {
        $ts = $row['updated_at'] ?: $row['published_at'];
        return [
            'id'              => (int) $row['id'],
            'title'           => $row['title'],
            'time_ago'        => timeAgoAjax($ts),
            'published_at'    => $ts,
            'is_breaking'     => (bool) $row['is_breaking'],
            'is_trending'     => (bool) $row['is_trending'],
            'is_flash'        => (bool) $row['is_flash'],
            'priority'        => $row['priority'],
            'views'           => $row['views'] ? (int) $row['views'] : null,
            'engagement_score'=> $row['engagement_score'] ? (float) $row['engagement_score'] : null,
            'source'          => $row['source'] ?: null,
            'author'          => $row['author'] ?: null,
            'update_type'     => $row['update_type'] ?: null,
            'category_name'   => $row['category_name'] ?: null,
        ];
    }, $updates);

    echo json_encode([
        'success'      => true,
        'articleId'    => (int) $articleId,
        'articleTitle' => $parent['title'],
        'count'        => count($stories),
        'stories'      => $stories,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    error_log('[get_developing_stories] DB error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}