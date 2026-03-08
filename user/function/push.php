<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

$id = $_GET['id'] ?? null;
$to = $_GET['to'] ?? null;

if (!$id || $to === null) {
    die("Invalid request");
}

$stmt = $pdo->prepare("UPDATE news SET is_pushed = ? WHERE id = ?");
$stmt->execute([$to, $id]);

// ── Redirect back to wherever the user came from ──────────────────────────────
// HTTP_REFERER preserves the full URL including ?page=2&section=edited etc.
// Fallback to dashboard if referer is missing (direct URL access, etc.)
$fallback = dirname(__DIR__) . '/user_dashboard.php';
$redirect = $_SERVER['HTTP_REFERER'] ?? $fallback;

// Safety check: only redirect to same origin, never an external URL
$host = $_SERVER['HTTP_HOST'] ?? '';
if (!empty($host) && strpos($redirect, $host) === false) {
    $redirect = $fallback;
}

header("Location: {$redirect}");
exit;