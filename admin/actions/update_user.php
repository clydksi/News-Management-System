<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

csrf_verify();

$userId   = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$username = trim($_POST['username']      ?? '');
$password = $_POST['password']           ?? '';
$deptId   = intval($_POST['department_id'] ?? 0);
$role     = trim($_POST['role']          ?? '');
$isActive = isset($_POST['is_active']) ? 1 : 0;
$viewScope = trim($_POST['view_scope']   ?? 'own');

if (!$userId || empty($username) || empty($deptId) || empty($role)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']); exit;
}

$allowedRoles = ['user', 'admin'];
if ($_SESSION['role'] === 'superadmin') $allowedRoles[] = 'superadmin';
if (!in_array($role, $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role selected']); exit;
}

if (!in_array($viewScope, ['own', 'granted', 'all'])) $viewScope = 'own';

try {
    $dup = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $dup->execute([$username, $userId]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already in use by another account']); exit;
    }

    if (!empty($password)) {
        if (strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']); exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET username=?, password=?, department_id=?, role=?, view_scope=?, is_active=? WHERE id=?")
            ->execute([$username, $hash, $deptId, $role, $viewScope, $isActive, $userId]);
    } else {
        $pdo->prepare("UPDATE users SET username=?, department_id=?, role=?, view_scope=?, is_active=? WHERE id=?")
            ->execute([$username, $deptId, $role, $viewScope, $isActive, $userId]);
    }

    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'update', ?, ?, NOW())")
            ->execute([$_SESSION['user_id'], "Updated user '$username' (role: $role, scope: $viewScope)", $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (PDOException $e) {}

    echo json_encode(['success' => true, 'message' => "User '$username' updated successfully"]);

} catch (PDOException $e) {
    error_log("Update user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
