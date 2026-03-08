<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

// Only admins and superadmins can approve (direct check — no auth.php which redirects)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

csrf_verify();

$id     = intval($_POST['id']     ?? 0);
$action = $_POST['action']        ?? '';   // 'approve' or 'reject'
$note   = trim($_POST['note']     ?? '');

if (!$id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']); exit;
}

// Ensure approval columns exist
try {
    $pdo->exec("ALTER TABLE news ADD COLUMN pending_approval TINYINT(1) NOT NULL DEFAULT 0");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE news ADD COLUMN rejection_note TEXT NULL");
} catch (PDOException $e) {}

try {
    if ($action === 'approve') {
        $pdo->prepare("UPDATE news SET is_pushed = 2, pending_approval = 0, rejection_note = NULL WHERE id = ?")
            ->execute([$id]);
        echo json_encode(['success' => true, 'message' => 'Article approved and pushed to Headlines.']);
    } else {
        if (empty($note)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a rejection reason.']); exit;
        }
        $pdo->prepare("UPDATE news SET pending_approval = 0, rejection_note = ? WHERE id = ?")
            ->execute([$note, $id]);
        echo json_encode(['success' => true, 'message' => 'Article rejected.']);
    }
} catch (PDOException $e) {
    error_log('Approve error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
