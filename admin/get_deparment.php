<?php
session_start();
require '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($departments);
