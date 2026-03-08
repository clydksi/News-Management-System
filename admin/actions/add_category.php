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
    $name = trim($_POST['name'] ?? '');
    $created_by = $_SESSION['user_id'] ?? null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }

    if (!$created_by) {
        echo json_encode(['success' => false, 'message' => 'User session invalid']);
        exit;
    }

    // Check if category already exists
    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $checkStmt->execute([$name]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Category already exists']);
        exit;
    }

    // Insert category with created_by
    $stmt = $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)");
    $stmt->execute([$name, $created_by]);

    echo json_encode([
        'success' => true, 
        'message' => 'Category added successfully',
        'category_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Add category error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
