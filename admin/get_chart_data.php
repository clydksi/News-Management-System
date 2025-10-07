<?php
require '../db.php'; // your DB connection

header('Content-Type: application/json');

// --- Query 1: New users per day ---
$stmt = $pdo->query("SELECT DATE(created_at) AS reg_date, COUNT(*) AS total 
                     FROM users 
                     GROUP BY DATE(created_at) 
                     ORDER BY reg_date ASC");
$traffic = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Query 2: Users by role ---
$stmt2 = $pdo->query("SELECT role, COUNT(*) AS total 
                      FROM users 
                      GROUP BY role");
$roles = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Build response
echo json_encode([
    "traffic" => $traffic,
    "roles"   => $roles
]);
