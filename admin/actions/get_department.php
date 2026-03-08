<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get department ID
$deptId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$deptId) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT 
            d.id, 
            d.name,
            COUNT(DISTINCT u.id) as user_count,
            COUNT(DISTINCT n.id) as article_count
        FROM departments d
        LEFT JOIN users u ON d.id = u.department_id
        LEFT JOIN news n ON d.id = n.department_id
        WHERE d.id = ?
        GROUP BY d.id
    ");
    $stmt->execute([$deptId]);
    $department = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($department) {
        echo json_encode([
            'success' => true,
            'department' => $department
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Department not found']);
    }
} catch (PDOException $e) {
    error_log("Get department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}