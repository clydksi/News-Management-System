<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    $action = $_POST['action'] ?? '';
    $userIds = isset($_POST['user_ids']) ? explode(',', $_POST['user_ids']) : [];
    
    // Validate input
    if (empty($action) || empty($userIds)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    // Remove current user from the list to prevent self-modification
    $userIds = array_filter($userIds, function($id) {
        return $id != $_SESSION['user_id'];
    });
    
    if (empty($userIds)) {
        echo json_encode(['success' => false, 'message' => 'Cannot perform action on yourself']);
        exit;
    }
    
    // Sanitize user IDs
    $userIds = array_map('intval', $userIds);
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    
    $pdo->beginTransaction();
    
    try {
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $affected = $stmt->rowCount();
                
                echo json_encode([
                    'success' => true,
                    'message' => "{$affected} user(s) activated successfully"
                ]);
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $affected = $stmt->rowCount();
                
                echo json_encode([
                    'success' => true,
                    'message' => "{$affected} user(s) deactivated successfully"
                ]);
                break;
                
            case 'delete':
                // Check if any users have articles
                $checkStmt = $pdo->prepare("
                    SELECT u.username, COUNT(n.id) as article_count
                    FROM users u
                    LEFT JOIN news n ON u.id = n.author_id
                    WHERE u.id IN ($placeholders)
                    GROUP BY u.id
                    HAVING article_count > 0
                ");
                $checkStmt->execute($userIds);
                $usersWithArticles = $checkStmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($usersWithArticles)) {
                    $pdo->rollBack();
                    $usernames = array_map(function($u) { return $u['username']; }, $usersWithArticles);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Cannot delete users with articles: ' . implode(', ', $usernames)
                    ]);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
                $stmt->execute($userIds);
                $affected = $stmt->rowCount();
                
                echo json_encode([
                    'success' => true,
                    'message' => "{$affected} user(s) deleted successfully"
                ]);
                break;
                
            default:
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }
        
        $pdo->commit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (PDOException $e) {
    error_log("Bulk users error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}