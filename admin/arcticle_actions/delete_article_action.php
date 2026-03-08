<?php
/**
 * Delete Article Action
 * Handles article deletion with proper validation and security checks
 */

// Start session first
session_start();

require '../../db.php';

// Set JSON header
header('Content-Type: application/json');

/**
 * Send JSON response and exit
 */
function sendResponse($success, $message = '', $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// Authentication check - allow both admin and superadmin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    sendResponse(false, 'Unauthorized access');
}

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Validate article ID
$articleId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$articleId || $articleId <= 0) {
    sendResponse(false, 'Invalid article ID');
}

try {
    // First, check if article exists and get its details
    $checkStmt = $pdo->prepare("
        SELECT id, title, created_by, category_id 
        FROM news 
        WHERE id = :id
    ");
    $checkStmt->execute([':id' => $articleId]);
    $article = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$article) {
        sendResponse(false, 'Article not found');
    }

    // Permission check: Only allow users to delete their own articles unless they're superadmin
    if ($_SESSION['role'] === 'admin' && $article['created_by'] != $_SESSION['user_id']) {
        sendResponse(false, 'You can only delete your own articles');
    }

    // Begin transaction for data integrity
    $pdo->beginTransaction();

    try {
        // Optional: Archive article before deletion (recommended for audit trail)
        $archiveStmt = $pdo->prepare("
            INSERT INTO deleted_articles (
                article_id, title, category_id, deleted_by, deleted_at
            ) VALUES (
                :article_id, :title, :category_id, :deleted_by, NOW()
            )
        ");
        
        // Try to archive, but don't fail if table doesn't exist
        try {
            $archiveStmt->execute([
                ':article_id' => $articleId,
                ':title' => $article['title'],
                ':category_id' => $article['category_id'],
                ':deleted_by' => $_SESSION['user_id']
            ]);
        } catch (PDOException $e) {
            // Archive table might not exist, log but continue
            error_log("Archive failed (table may not exist): " . $e->getMessage());
        }

        // Delete related data first (if any)
        // Delete article comments (if comments table exists)
        try {
            $deleteCommentsStmt = $pdo->prepare("DELETE FROM comments WHERE article_id = :id");
            $deleteCommentsStmt->execute([':id' => $articleId]);
        } catch (PDOException $e) {
            error_log("Comments deletion skipped: " . $e->getMessage());
        }

        // Delete article views/analytics (if exists)
        try {
            $deleteViewsStmt = $pdo->prepare("DELETE FROM article_views WHERE article_id = :id");
            $deleteViewsStmt->execute([':id' => $articleId]);
        } catch (PDOException $e) {
            error_log("Views deletion skipped: " . $e->getMessage());
        }

        // Delete the article
        $deleteStmt = $pdo->prepare("DELETE FROM news WHERE id = :id");
        $deleteStmt->execute([':id' => $articleId]);

        if ($deleteStmt->rowCount() === 0) {
            throw new Exception('Article deletion failed');
        }

        // Commit transaction
        $pdo->commit();

        // Log the action
        error_log(
            "Article deleted: ID={$articleId}, " .
            "Title={$article['title']}, " .
            "By User={$_SESSION['username']} (ID={$_SESSION['user_id']})"
        );

        sendResponse(true, 'Article deleted successfully', [
            'deleted_article_id' => $articleId,
            'deleted_article_title' => $article['title']
        ]);

    } catch (Exception $e) {
        // Rollback on any error
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    // Log error for debugging (don't expose to client)
    error_log("Database error in delete_article_action.php: " . $e->getMessage());
    sendResponse(false, 'An error occurred while deleting the article');
} catch (Exception $e) {
    error_log("Error in delete_article_action.php: " . $e->getMessage());
    sendResponse(false, $e->getMessage());
}