<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$stagingId = $data['staging_id'] ?? null;
$status = $data['status'] ?? 0; // 0=draft, 1=edited, 2=headline

if (!$stagingId) {
    echo json_encode(['success' => false, 'message' => 'Staging ID required']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get article from staging
    $stmt = $pdo->prepare("SELECT * FROM external_articles WHERE id = ? AND is_processed = FALSE");
    $stmt->execute([$stagingId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        throw new Exception('Article not found or already processed');
    }

    // Get or create category
    $categoryId = null;
    if (!empty($article['category'])) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?)");
        $stmt->execute([$article['category']]);
        $cat = $stmt->fetch();
        
        if ($cat) {
            $categoryId = $cat['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([ucfirst($article['category'])]);
            $categoryId = $pdo->lastInsertId();
        }
    }

    // Build full content
    $fullContent = $article['content'] ?: $article['description'];
    if ($article['author'] || $article['source'] || $article['published_at']) {
        $fullContent .= "\n\n---\n";
        if ($article['author']) $fullContent .= "Author: {$article['author']}\n";
        if ($article['source']) $fullContent .= "Source: {$article['source']}\n";
        if ($article['published_at']) $fullContent .= "Published: " . date('F d, Y', strtotime($article['published_at'])) . "\n";
    }

    // Insert into news table
    $stmt = $pdo->prepare("
        INSERT INTO news (
            title, 
            content, 
            category_id, 
            department_id, 
            created_by, 
            external_url, 
            is_pushed,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ");

    $stmt->execute([
        $article['title'],
        $fullContent,
        $categoryId,
        $_SESSION['department_id'] ?? null,
        $_SESSION['user_id'] ?? null,
        $article['external_url'],
        $status
    ]);

    $newsId = $pdo->lastInsertId();

    // Update staging table
    $stmt = $pdo->prepare("
        UPDATE external_articles 
        SET is_processed = TRUE, 
            news_id = ?,
            pushed_to_news_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$newsId, $stagingId]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Article pushed to news successfully',
        'news_id' => $newsId
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>