<?php
require '../db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("SELECT id, name FROM departments ORDER BY name");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($departments);
