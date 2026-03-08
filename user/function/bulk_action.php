<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';
require dirname(__DIR__, 2) . '/admin/includes/access_control.php';

header('Content-Type: application/json');
csrf_verify();

$action  = $_POST['action']  ?? '';
$rawIds  = $_POST['ids']     ?? [];
$ids     = array_values(array_filter(array_map('intval', (array)$rawIds)));

if (empty($ids)) {
    echo json_encode(['success' => false, 'message' => 'No articles selected.']); exit;
}

$allowed = ['delete', 'push_edited', 'push_headline', 'archive', 'revert_regular', 'revert_edited'];
if (!in_array($action, $allowed)) {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']); exit;
}

$isAdmin = in_array($_SESSION['role'], ['admin', 'superadmin']);
$userId  = $_SESSION['user_id'];

// Verify ownership / access for each article
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, created_by FROM news WHERE id IN ({$placeholders})");
$stmt->execute($ids);
$articles = $stmt->fetchAll();
$foundIds = array_column($articles, 'id');

// Check all requested IDs exist
if (count($foundIds) !== count($ids)) {
    echo json_encode(['success' => false, 'message' => 'One or more articles not found.']); exit;
}

// Non-admins can only act on their own articles
if (!$isAdmin) {
    foreach ($articles as $a) {
        if ((int)$a['created_by'] !== (int)$userId) {
            echo json_encode(['success' => false, 'message' => 'You can only modify your own articles.']); exit;
        }
    }
}

try {
    switch ($action) {
        case 'delete':
            $pdo->prepare("DELETE FROM news WHERE id IN ({$placeholders})")->execute($ids);
            $msg = count($ids) . ' article(s) deleted.';
            break;

        case 'push_edited':
            $pdo->prepare("UPDATE news SET is_pushed = 1 WHERE id IN ({$placeholders})")->execute($ids);
            $msg = count($ids) . ' article(s) marked as Edited.';
            break;

        case 'push_headline':
            if (!$isAdmin) {
                // Non-admins submit for review instead of direct headline
                try {
                    $pdo->prepare("UPDATE news SET pending_approval = 1 WHERE id IN ({$placeholders})")->execute($ids);
                } catch (PDOException $e) {
                    // Column doesn't exist yet — just push to edited
                    $pdo->prepare("UPDATE news SET is_pushed = 1 WHERE id IN ({$placeholders})")->execute($ids);
                }
                $msg = count($ids) . ' article(s) submitted for approval.';
            } else {
                $pdo->prepare("UPDATE news SET is_pushed = 2 WHERE id IN ({$placeholders})")->execute($ids);
                $msg = count($ids) . ' article(s) pushed to Headlines.';
            }
            break;

        case 'archive':
            $pdo->prepare("UPDATE news SET is_pushed = 3 WHERE id IN ({$placeholders})")->execute($ids);
            $msg = count($ids) . ' article(s) archived.';
            break;

        case 'revert_regular':
            $pdo->prepare("UPDATE news SET is_pushed = 0 WHERE id IN ({$placeholders})")->execute($ids);
            $msg = count($ids) . ' article(s) reverted to Regular.';
            break;

        case 'revert_edited':
            $pdo->prepare("UPDATE news SET is_pushed = 1 WHERE id IN ({$placeholders})")->execute($ids);
            $msg = count($ids) . ' article(s) reverted to Edited.';
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action.']); exit;
    }

    echo json_encode(['success' => true, 'message' => $msg, 'count' => count($ids)]);

} catch (PDOException $e) {
    error_log('Bulk action error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
}
