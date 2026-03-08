<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

csrf_verify();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
if (!$userId) { echo json_encode(['success' => false, 'message' => 'Invalid user ID']); exit; }
if ($userId == $_SESSION['user_id']) { echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']); exit; }

try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) { echo json_encode(['success' => false, 'message' => 'User not found']); exit; }

    // Block deletion if user has articles
    $articleCount = (int)$pdo->prepare("SELECT COUNT(*) FROM news WHERE created_by = ?")->execute([$userId])
        ? $pdo->query("SELECT COUNT(*) FROM news WHERE created_by = {$userId}")->fetchColumn()
        : 0;

    // Use safe prepared statement
    $s = $pdo->prepare("SELECT COUNT(*) FROM news WHERE created_by = ?");
    $s->execute([$userId]);
    $articleCount = (int)$s->fetchColumn();

    if ($articleCount > 0) {
        echo json_encode(['success' => false, 'message' => "Cannot delete '{$user['username']}'. They have {$articleCount} article(s). Reassign or delete their articles first."]);
        exit;
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'delete', ?, ?, NOW())")
            ->execute([$_SESSION['user_id'], "Deleted user '{$user['username']}'", $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (PDOException $e) {}

    echo json_encode(['success' => true, 'message' => "User '{$user['username']}' deleted successfully"]);

} catch (PDOException $e) {
    error_log("Delete user error: " . $e->getMessage());
    if ($e->getCode() == '23000') {
        echo json_encode(['success' => false, 'message' => 'Cannot delete user — they are referenced by other records.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
}
