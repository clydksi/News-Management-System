<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

// Security: Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Validate and sanitize input
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$password = $_POST['password'] ?? '';
$departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
$role = filter_input(INPUT_POST, 'role', FILTER_SANITIZE_STRING);
$isActive = isset($_POST['is_active']) ? 1 : 0;

// Validation
if (!$userId || empty($username) || empty($departmentId) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate role
$allowedRoles = ['user', 'admin'];
if ($_SESSION['role'] === 'superadmin') {
    $allowedRoles[] = 'superadmin';
}

if (!in_array($role, $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected']);
    exit;
}

try {
    // Check if username already exists for other users
    $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :id");
    $checkStmt->execute([':username' => $username, ':id' => $userId]);
    
    if ($checkStmt->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    // Build update query
    if (!empty($password)) {
        // Update with new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = :username, 
                password = :password, 
                department_id = :department_id, 
                role = :role, 
                is_active = :is_active
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':username' => $username,
            ':password' => $hashedPassword,
            ':department_id' => $departmentId,
            ':role' => $role,
            ':is_active' => $isActive,
            ':id' => $userId
        ]);
    } else {
        // Update without changing password
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = :username, 
                department_id = :department_id, 
                role = :role, 
                is_active = :is_active
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':username' => $username,
            ':department_id' => $departmentId,
            ':role' => $role,
            ':is_active' => $isActive,
            ':id' => $userId
        ]);
    }
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}