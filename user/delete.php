<?php
require '../auth.php';
require '../db.php';

$id = $_GET['id'] ?? null;
if (!$id) header("Location: dashboard.php");

if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("DELETE FROM news WHERE id=?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("DELETE FROM news WHERE id=? AND department_id=?");
    $stmt->execute([$id, $_SESSION['department_id']]);
}
header("Location: user_dashboard.php");
exit;
