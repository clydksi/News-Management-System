<?php
require '../db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT DISTINCT roles FROM users WHERE roles IS NOT NULL ORDER BY roles");
$roles = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo json_encode($roles);