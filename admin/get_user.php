<?php
session_start();
require '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.username, 
        u.role,
        u.is_active,                -- raw value
        d.name AS department, 
        u.created_at AS joined,
        u.last_seen                 -- new column
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ?
");

$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Format joined date
$user['joined'] = $user['joined'] 
    ? date('F j, Y', strtotime($user['joined'])) 
    : null;

// Format last_seen date
$user['last_seen'] = $user['last_seen'] 
    ? date('F j, Y g:i A', strtotime($user['last_seen'])) 
    : null;

// Add a human-readable status
$user['status'] = $user['is_active'] == 1 ? 'Active' : 'Inactive';

echo json_encode($user);
