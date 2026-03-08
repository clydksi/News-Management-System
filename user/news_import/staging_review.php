<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

// Get unprocessed articles from staging
$stmt = $pdo->prepare("
    SELECT * FROM external_articles 
    WHERE is_processed = FALSE 
    ORDER BY fetched_at DESC
");
$stmt->execute();
$stagingArticles = $stmt->fetchAll();
?>

<!-- Display staging articles with "Push to News" buttons -->
<!-- Each can have a status selector (Draft/Edited/Headline) -->
<!-- Then call push_to_news.php when they click "Publish" -->