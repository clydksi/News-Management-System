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
    // Sanitize and validate input
    $deptId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
    $name = trim($_POST['name'] ?? '');

    // Validation checks
    if (!$deptId) {
        echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
        exit;
    }

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Department name is required']);
        exit;
    }

    // Length validation
    if (strlen($name) < 2 || strlen($name) > 100) {
        echo json_encode(['success' => false, 'message' => 'Department name must be 2-100 characters']);
        exit;
    }

    // Check if department exists
    $checkExistStmt = $pdo->prepare("SELECT id FROM departments WHERE id = ?");
    $checkExistStmt->execute([$deptId]);
    
    if (!$checkExistStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit;
    }

    // Check if name already exists for another department (case-insensitive)
    $checkStmt = $pdo->prepare("SELECT id FROM departments WHERE LOWER(name) = LOWER(?) AND id != ?");
    $checkStmt->execute([$name, $deptId]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Department name already exists']);
        exit;
    }

    // Update department
    $stmt = $pdo->prepare("UPDATE departments SET name = ? WHERE id = ?");
    $result = $stmt->execute([$name, $deptId]);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Department updated successfully',
            'department_name' => $name
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update department']);
    }

} catch (PDOException $e) {
    error_log("Update department error: " . $e->getMessage());
    
    if ($e->getCode() == '23000') {
        echo json_encode(['success' => false, 'message' => 'Department name already exists']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
