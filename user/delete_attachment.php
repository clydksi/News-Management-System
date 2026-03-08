<?php
require '../auth.php';
require '../db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: user_dashboard.php?error=invalid_attachment');
    exit;
}

$attachmentId = intval($_GET['id']);

try {
    // Get attachment details and verify permissions
    $stmt = $pdo->prepare("
        SELECT a.*, n.department_id, n.id as news_id
        FROM attachments a
        JOIN news n ON a.news_id = n.id
        WHERE a.id = ?
    ");
    $stmt->execute([$attachmentId]);
    $attachment = $stmt->fetch();
    
    if (!$attachment) {
        header('Location: user_dashboard.php?error=attachment_not_found');
        exit;
    }
    
    // Check permissions
    if ($_SESSION['role'] !== 'admin' && $attachment['department_id'] != $_SESSION['department_id']) {
        header('Location: user_dashboard.php?error=permission_denied');
        exit;
    }
    
    // Delete file from filesystem
    if (file_exists($attachment['file_path'])) {
        unlink($attachment['file_path']);
    }
    
    // Delete from database
    $deleteStmt = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
    $deleteStmt->execute([$attachmentId]);
    
    // Redirect back with success message
    $redirect = isset($_GET['redirect']) ? $_GET['redirect'] : 'user_dashboard.php';
    header("Location: {$redirect}?success=attachment_deleted&news_id=" . $attachment['news_id']);
    exit;
    
} catch (PDOException $e) {
    header('Location: user_dashboard.php?error=delete_failed');
    exit;
}
?>