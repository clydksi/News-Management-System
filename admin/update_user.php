<?php
session_start();
require '../db.php';
require '../csrf.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

csrf_verify();

$id = $_POST['id'] ?? null;
$username = $_POST['username'] ?? null; // nullable
$role = $_POST['role'] ?? null;         // nullable
$deptId = isset($_POST['department']) && $_POST['department'] !== '' ? (int)$_POST['department'] : null;
$status = isset($_POST['status']) ? (int)$_POST['status'] : null;
$password = $_POST['password'] ?? null;

if (!$id || !is_numeric($id)) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

// If deptId is provided, check that it exists in departments table
if ($deptId !== null) {
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
    $stmtCheck->execute([$deptId]);
    if ($stmtCheck->fetchColumn() == 0) {
        echo json_encode(['error' => 'Selected department does not exist']);
        exit;
    }
}

// Build SQL dynamically based on provided fields
$fields = [];
$params = [];

if ($username !== null) {
    $fields[] = "username = ?";
    $params[] = $username;
}

if ($role !== null) {
    $fields[] = "role = ?";
    $params[] = $role;
}

if ($deptId !== null) {
    $fields[] = "department_id = ?";
    $params[] = $deptId;
}

if ($status !== null) {
    $fields[] = "is_active = ?";
    $params[] = $status;
    if ($status == 0) {
        $fields[] = "last_seen = NOW()";
    }
}

if (!empty($password)) {
    $fields[] = "password = ?";
    $params[] = password_hash($password, PASSWORD_DEFAULT);
}

if (empty($fields)) {
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
$params[] = $id;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
