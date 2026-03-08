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
    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Check if category name already exists (excluding current category)
    $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $checkStmt->execute([$name, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Category name already exists']);
        exit;
    }

    // Update category
    $stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Category updated successfully'
    ]);

} catch (PDOException $e) {
    error_log("Edit category error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>