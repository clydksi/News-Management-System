<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

// Auth + superadmin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../admin_dashboard.php?tab=access&error=unauthorized');
    exit;
}

// CSRF
if (
    empty($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    header('Location: ../admin_dashboard.php?tab=access&error=invalid');
    exit;
}

$userId       = filter_input(INPUT_POST, 'user_id',       FILTER_VALIDATE_INT);
$departmentId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);
$viewScope    = in_array($_POST['view_scope'] ?? '', ['own', 'granted', 'all'])
                    ? $_POST['view_scope']
                    : 'own';

if (!$userId || !$departmentId) {
    header('Location: ../admin_dashboard.php?tab=access&error=invalid');
    exit;
}

// Prevent granting access to own department (they already have it)
$ownDept = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
$ownDept->execute([$userId]);
$ownDeptId = (int)$ownDept->fetchColumn();

if ($ownDeptId === $departmentId) {
    header('Location: ../admin_dashboard.php?tab=access&error=self');
    exit;
}

try {
    // Insert grant
    $pdo->prepare("
        INSERT INTO department_access (user_id, department_id, granted_by)
        VALUES (?, ?, ?)
    ")->execute([$userId, $departmentId, $_SESSION['user_id']]);

    // Update user's view_scope to 'granted' if it was 'own'
    $pdo->prepare("
        UPDATE users
        SET    view_scope = ?
        WHERE  id = ? AND view_scope = 'own'
    ")->execute([$viewScope, $userId]);

    header('Location: ../admin_dashboard.php?tab=access&success=granted');

} catch (PDOException $e) {
    // Duplicate entry = already has access
    if ($e->getCode() === '23000') {
        header('Location: ../admin_dashboard.php?tab=access&error=duplicate');
    } else {
        error_log('save_access error: ' . $e->getMessage());
        header('Location: ../admin_dashboard.php?tab=access&error=invalid');
    }
}
exit;