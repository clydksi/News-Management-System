<?php
/**
 * Simplified Article Publishing System
 * Publishes articles from news table to published_news table
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Configuration
define('BASE_URL', 'http://newsnetwork.mbcradio.net/crud/user/');
define('ZAPIER_WEBHOOK_URL', 'https://hooks.zapier.com/hooks/catch/25253563/us1t4ya/');
define('WEBHOOK_TIMEOUT', 10);
define('LOG_FILE', dirname(__DIR__, 2) . '/logs/publish.log');

function logMessage($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
    
    if (!file_exists(dirname(LOG_FILE))) {
        @mkdir(dirname(LOG_FILE), 0755, true);
    }
    @file_put_contents(LOG_FILE, $logEntry, FILE_APPEND);
}

function buildThumbnailUrl($thumbnailPath) {
    if (empty($thumbnailPath)) {
        return '';
    }
    return BASE_URL . ltrim($thumbnailPath, '/');
}

function sendWebhook($data) {
    if (empty(ZAPIER_WEBHOOK_URL)) {
        logMessage("Webhook not configured - skipping", 'WARNING');
        return ['success' => false, 'error' => 'Not configured'];
    }
    
    try {
        $ch = curl_init(ZAPIER_WEBHOOK_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: MBC-NewsPublisher/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => WEBHOOK_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            logMessage("Webhook sent - HTTP {$httpCode}", 'SUCCESS');
            return ['success' => true, 'http_code' => $httpCode];
        } else {
            logMessage("Webhook failed - HTTP {$httpCode}", 'WARNING');
            return ['success' => false, 'http_code' => $httpCode];
        }

    } catch (Exception $e) {
        logMessage("Webhook error: " . $e->getMessage(), 'ERROR');
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function redirectWithStatus($status, $message = '', $webhookStatus = '') {
    $params = ['status' => $status];
    
    if (!empty($message)) {
        $params['message'] = urlencode($message);
    }
    
    if (!empty($webhookStatus)) {
        $params['webhook'] = $webhookStatus;
    }
    
    $queryString = http_build_query($params);
    
    // Check for explicit return URL parameter (preferred method)
    $returnUrl = $_GET['return_url'] ?? '';
    
    // Fallback to HTTP_REFERER if no return_url provided
    if (empty($returnUrl)) {
        $returnUrl = $_SERVER['HTTP_REFERER'] ?? '';
    }
    
    // Validate and sanitize return URL
    if (!empty($returnUrl)) {
        $parsedUrl = parse_url($returnUrl);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        
        // Case 1: Full URL with host - validate same domain
        if (isset($parsedUrl['host'])) {
            if ($parsedUrl['host'] === $currentHost) {
                $path = $parsedUrl['path'] ?? '';
                $existingQuery = $parsedUrl['query'] ?? '';
                
                // Merge existing query params with new status params
                if ($existingQuery) {
                    parse_str($existingQuery, $existingParams);
                    // Remove old status messages to avoid duplication
                    unset($existingParams['status'], $existingParams['message'], $existingParams['webhook']);
                    $params = array_merge($existingParams, $params);
                    $queryString = http_build_query($params);
                }
                
                $redirectUrl = $path . '?' . $queryString;
                logMessage("Redirecting to validated URL: {$redirectUrl}", 'INFO');
            } else {
                // Different host - security risk, use default
                $redirectUrl = "../user_dashboard.php?{$queryString}";
                logMessage("Invalid host in return URL - using default dashboard", 'WARNING');
            }
        } 
        // Case 2: Relative URL (no host) - safe to use
        else if (isset($parsedUrl['path'])) {
            $path = $parsedUrl['path'];
            $existingQuery = $parsedUrl['query'] ?? '';
            
            // Merge existing query params
            if ($existingQuery) {
                parse_str($existingQuery, $existingParams);
                unset($existingParams['status'], $existingParams['message'], $existingParams['webhook']);
                $params = array_merge($existingParams, $params);
                $queryString = http_build_query($params);
            }
            
            // Check if path already has query string
            $separator = (strpos($path, '?') !== false) ? '&' : '?';
            $redirectUrl = $path . $separator . $queryString;
            logMessage("Redirecting to relative URL: {$redirectUrl}", 'INFO');
        } 
        // Case 3: Invalid URL format
        else {
            $redirectUrl = "../user_dashboard.php?{$queryString}";
            logMessage("Invalid return URL format - using default dashboard", 'WARNING');
        }
    } 
    // No return URL provided - use default
    else {
        $redirectUrl = "../user_dashboard.php?{$queryString}";
        logMessage("No return URL - using default dashboard", 'INFO');
    }
    
    header("Location: {$redirectUrl}");
    exit;
}

// ==================== MAIN EXECUTION ====================

try {
    // 1. Validate ID
    if (!isset($_GET['id'])) {
        throw new Exception("Missing article ID");
    }
    
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        throw new Exception("Invalid article ID format");
    }
    
    logMessage("Publishing article ID: {$id}", 'INFO');
    
    // 2. Start transaction
    $pdo->beginTransaction();
    
    // 3. Fetch article with all details
    $stmt = $pdo->prepare("
        SELECT n.*, 
               c.name as category_name,
               u.username as author_name,
               d.name as department_name,
               parent.title as parent_title,
               parent.is_pushed as parent_status
        FROM news n
        JOIN users u ON n.created_by = u.id
        JOIN departments d ON n.department_id = d.id
        LEFT JOIN categories c ON n.category_id = c.id
        LEFT JOIN news parent ON n.parent_article_id = parent.id
        WHERE n.id = ?
    ");
    
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$article) {
        throw new Exception("Article not found with ID: {$id}");
    }
    
    logMessage("Fetched article: {$article['title']}", 'INFO');
    
    // 4. Validate article is in Headline status (is_pushed = 2)
    if ($article['is_pushed'] != 2) {
        $statusNames = [0 => 'Regular', 1 => 'Edited', 2 => 'Headline', 3 => 'Archived'];
        throw new Exception(
            "Only 'Headline' articles can be published. " .
            "This article is currently in '{$statusNames[$article['is_pushed']]}' status. " .
            "Please push it to Headlines first."
        );
    }
    
    // 5. Check if already published
    $checkStmt = $pdo->prepare("
        SELECT id, published_at 
        FROM published_news 
        WHERE original_id = ? 
        LIMIT 1
    ");
    $checkStmt->execute([$id]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        throw new Exception(
            "This article was already published on " . 
            date('M d, Y \a\t g:i A', strtotime($existing['published_at']))
        );
    }
    
    // 6. ONLY check parent if this is an update
    $publishedParentId = null;
    
    if ($article['is_update'] == 1 && !empty($article['parent_article_id'])) {
        logMessage("This is an update - checking parent article: {$article['parent_article_id']}", 'INFO');
        
        // Check if parent is published
        $checkParent = $pdo->prepare("
            SELECT id FROM published_news 
            WHERE original_id = ? 
            LIMIT 1
        ");
        $checkParent->execute([$article['parent_article_id']]);
        $publishedParent = $checkParent->fetch();
        
        if (!$publishedParent) {
            throw new Exception(
                "This is an update article. The parent article must be published before publishing updates. " .
                "Parent article: '{$article['parent_title']}'"
            );
        }
        
        $publishedParentId = $publishedParent['id'];
        logMessage("Using published parent ID: {$publishedParentId}", 'INFO');
    } else {
        logMessage("This is a regular/parent article - no parent check needed", 'INFO');
    }
    
    // 7. Build insert data
    $insertData = [
        'title' => $article['title'],
        'content' => $article['content'],
        'original_id' => $id,
        'category_id' => $article['category_id'] ?? null,
        'department_id' => $article['department_id'] ?? null,
        'created_by' => $article['created_by'],
        'thumbnail' => $article['thumbnail'] ?? null,
        'is_update' => $article['is_update'] ?? 0,
        'parent_article_id' => $publishedParentId,
        'update_type' => $article['update_type'] ?? null,
        'update_number' => $article['update_number'] ?? null
    ];
    
    // 8. Execute insert
    $fields = array_keys($insertData);
    $placeholders = array_fill(0, count($fields), '?');
    
    $sql = "INSERT INTO published_news (" . implode(', ', $fields) . ") 
            VALUES (" . implode(', ', $placeholders) . ")";
    
    logMessage("Executing INSERT with " . count($insertData) . " fields", 'INFO');
    
    $insertStmt = $pdo->prepare($sql);
    $insertStmt->execute(array_values($insertData));
    
    $newId = $pdo->lastInsertId();
    
    if (!$newId) {
        throw new Exception("Insert failed - no ID returned");
    }
    
    logMessage("Article published with new ID: {$newId}", 'SUCCESS');
    
    // 9. Archive original article (is_pushed = 3)
    $archiveStmt = $pdo->prepare("UPDATE news SET is_pushed = 3 WHERE id = ?");
    $archiveStmt->execute([$id]);
    
    logMessage("Original article archived (is_pushed = 3)", 'INFO');
    
    // 10. Commit transaction
    $pdo->commit();
    logMessage("Transaction committed successfully", 'SUCCESS');
    
    // 11. Send webhook
    $webhookData = [
        'id' => $newId,
        'original_id' => $id,
        'title' => $article['title'],
        'content' => substr($article['content'], 0, 500), // Truncate for webhook
        'category_name' => $article['category_name'] ?? 'Uncategorized',
        'department_name' => $article['department_name'] ?? 'Unknown',
        'thumbnail' => buildThumbnailUrl($article['thumbnail']),
        'author_name' => $article['author_name'] ?? 'Unknown',
        'published_at' => date('Y-m-d H:i:s'),
        'is_update' => (bool)($article['is_update'] ?? 0),
        'parent_article_id' => $publishedParentId,
        'parent_title' => $article['parent_title'] ?? null,
        'update_type' => $article['update_type'] ?? null,
        'update_number' => $article['update_number'] ?? null,
        'article_type' => ($article['is_update'] ?? 0) ? 'update' : 'original',
        'system' => 'MBC News Dashboard'
    ];
    
    $webhookResult = sendWebhook($webhookData);
    $webhookStatus = $webhookResult['success'] ? 'sent' : 'failed';
    
    // 12. Build success message
    if ($article['is_update'] == 1) {
        $message = "Update #{$article['update_number']} published successfully!";
    } else {
        $message = "Article '{$article['title']}' published successfully!";
    }
    
    logMessage("Publishing complete: {$message}", 'SUCCESS');
    
    // 13. Redirect back to origin page with success notification
    redirectWithStatus('published', $message, $webhookStatus);
    
} catch (Exception $e) {
    // Rollback transaction if active
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        logMessage("Transaction rolled back", 'ERROR');
    }
    
    // Log error
    logMessage("Publishing error: " . $e->getMessage(), 'ERROR');
    
    // Redirect back to origin page with error message
    redirectWithStatus('error', $e->getMessage());
}
?>