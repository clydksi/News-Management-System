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
    $name = trim($_POST['name'] ?? '');

    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Check if department name already exists (excluding current department)
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
    $checkStmt->execute([$name, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Department name already exists']);
        exit;
    }

    // Update department
    $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Department updated successfully'
    ]);

} catch (PDOException $e) {
    error_log("Edit department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
