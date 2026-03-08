<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

$id = intval($_GET['id'] ?? 0);
$to = $_GET['to'] ?? null;

if (!$id || $to === null) {
    die("Invalid request");
}

$isAdmin = in_array($_SESSION['role'], ['admin', 'superadmin']);

// Approval workflow: non-admins submitting to headline go to pending review instead
if ((int)$to === 2 && !$isAdmin) {
    // Ensure columns exist
    try { $pdo->exec("ALTER TABLE news ADD COLUMN pending_approval TINYINT(1) NOT NULL DEFAULT 0"); } catch (PDOException $e) {}
    try { $pdo->exec("ALTER TABLE news ADD COLUMN rejection_note TEXT NULL"); } catch (PDOException $e) {}

    $pdo->prepare("UPDATE news SET pending_approval = 1, rejection_note = NULL WHERE id = ?")
        ->execute([$id]);

    $fallback = dirname(__DIR__) . '/user_dashboard.php';
    $redirect = $_SERVER['HTTP_REFERER'] ?? $fallback;
    $host     = $_SERVER['HTTP_HOST'] ?? '';
    if (!empty($host) && strpos($redirect, $host) === false) $redirect = $fallback;
    $sep = strpos($redirect, '?') !== false ? '&' : '?';
    header("Location: {$redirect}{$sep}approval_submitted=1");
    exit;
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