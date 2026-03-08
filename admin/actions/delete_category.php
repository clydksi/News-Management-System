<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

csrf_verify();

try {
    $id = intval($_POST['id'] ?? 0);

    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Category ID is required']);
        exit;
    }

    // Get category name before deletion
    $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
    $catStmt->execute([$id]);
    $category = $catStmt->fetch();

    if (!$category) {
        echo json_encode(['success' => false, 'message' => 'Category not found']);
        exit;
    }

    // Check if category has articles
    $articleStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE category_id = ?");
    $articleStmt->execute([$id]);
    $articleCount = $articleStmt->fetchColumn();

    if ($articleCount > 0) {
        echo json_encode([
            'success' => false, 
            'message' => "Cannot delete category. It has $articleCount article(s). Please reassign articles first."
        ]);
        exit;
    }

    // Delete category
    $deleteStmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $deleteStmt->execute([$id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Category deleted successfully'
    ]);

} catch (PDOException $e) {
    error_log("Delete category error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
