<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

csrf_verify();

$userId   = filter_input(INPUT_POST, 'user_id',   FILTER_VALIDATE_INT);
$isActive = filter_input(INPUT_POST, 'is_active', FILTER_VALIDATE_INT);

if (!$userId || $isActive === null || $isActive === false) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Cannot toggle your own account
if ($userId === (int)$_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Cannot modify your own account status']);
    exit;
}

$isActive = $isActive ? 1 : 0;

try {
    $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->execute([$isActive, $userId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }

    // Activity log
    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, 'update', ?, ?)")
            ->execute([
                $_SESSION['user_id'],
                ($isActive ? 'Activated' : 'Deactivated') . " user ID $userId",
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
            ]);
    } catch (PDOException $e) {}

    echo json_encode([
        'success'   => true,
        'message'   => 'User ' . ($isActive ? 'activated' : 'deactivated') . ' successfully',
        'is_active' => $isActive,
    ]);

} catch (PDOException $e) {
    error_log("toggle_user_status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
