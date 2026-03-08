<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

csrf_verify();

try {
    $name = trim($_POST['name'] ?? '');

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Department name is required']);
        exit;
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Department name must be 2-100 characters']);
        exit;
    }

    // Check duplicate
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?)");
    $checkStmt->execute([$name]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Department already exists']);
        exit;
    }

    // Insert
    $stmt = $pdo->prepare("INSERT INTO departments (name) VALUES (?)");
    $stmt->execute([$name]);

    echo json_encode([
        'success' => true, 
        'message' => 'Department added successfully',
        'department_id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Add department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
