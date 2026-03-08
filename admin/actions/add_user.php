<?php
session_start();
require '../../db.php';

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

    // Check if department name already exists (excluding current department)
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
    $checkStmt->execute([$name, $id]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Department name already exists']);
        exit;
    }

    // Get old name for logging
    $oldStmt = $pdo->prepare("SELECT name FROM departments WHERE id = ?");
    $oldStmt->execute([$id]);
    $oldName = $oldStmt->fetchColumn();

    // Update department
    $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
        VALUES (?, 'update', ?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'],
        "Updated department from '$oldName' to '$name'",
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Department updated successfully'
    ]);

} catch (PDOException $e) {
    error_log("Edit department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>