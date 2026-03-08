<?php
session_start();
require '../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$stmt = $pdo->query("SELECT DISTINCT roles FROM users WHERE roles IS NOT NULL ORDER BY roles");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($roles);