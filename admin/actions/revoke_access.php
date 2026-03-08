<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

// Auth + superadmin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    header('Location: ../admin_dashboard.php?tab=access&error=unauthorized');
    exit;
}

// CSRF (passed via URL for GET-based revoke link)
if (
    empty($_GET['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])
) {
    header('Location: ../admin_dashboard.php?tab=access&error=invalid');
    exit;
}

$grantId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$grantId) {
    header('Location: ../admin_dashboard.php?tab=access&error=invalid');
    exit;
}

try {
    // Get the user_id before deleting so we can check remaining grants
    $stmt = $pdo->prepare("SELECT user_id FROM department_access WHERE id = ?");
    $stmt->execute([$grantId]);
    $userId = (int)$stmt->fetchColumn();

    // Delete the grant
    $pdo->prepare("DELETE FROM department_access WHERE id = ?")
        ->execute([$grantId]);

    // If user has no remaining grants, reset view_scope back to 'own'
    if ($userId) {
        $remaining = $pdo->prepare("
            SELECT COUNT(*) FROM department_access WHERE user_id = ?
        ");
        $remaining->execute([$userId]);

        if ((int)$remaining->fetchColumn() === 0) {
            $pdo->prepare("
                UPDATE users SET view_scope = 'own' WHERE id = ? AND view_scope = 'granted'
            ")->execute([$userId]);
        }
    }

    header('Location: ../admin_dashboard.php?tab=access&success=revoked');

} catch (PDOException $e) {
    error_log('revoke_access error: ' . $e->getMessage());
    header('Location: ../admin_dashboard.php?tab=access&error=invalid');
}
exit;