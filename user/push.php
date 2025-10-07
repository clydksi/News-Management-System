<?php
require '../auth.php';
require '../db.php';

$id = $_GET['id'] ?? null;
$to = $_GET['to'] ?? null;

if (!$id || $to === null) {
    die("Invalid request");
}

$stmt = $pdo->prepare("UPDATE news SET is_pushed=? WHERE id=?");
$stmt->execute([$to, $id]);

header("Location: user_dashboard.php");
exit;
