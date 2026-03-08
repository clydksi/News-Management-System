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

if (!$id || !is_numeric($id)) {
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
