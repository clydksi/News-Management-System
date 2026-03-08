<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';


$id = $_GET['id'] ?? null;
$to = $_GET['to'] ?? null; // 👈 capture target revert state

if (!$id || $to === null) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Only allow valid revert targets
$allowed = [0, 1]; // 0 = Regular, 1 = Edited
if (!in_array((int)$to, $allowed, true)) {
    header("Location: ../user_dashboard.php");
    exit;
}

if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("UPDATE news SET is_pushed=? WHERE id=?");
    $stmt->execute([$to, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE news SET is_pushed=? WHERE id=? AND department_id=?");
    $stmt->execute([$to, $id, $_SESSION['department_id']]);
}

header("Location: ../user_dashboard.php");
exit;
