<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']); exit;
}

csrf_verify();

$username    = trim($_POST['username']      ?? '');
$password    = $_POST['password']           ?? '';
$deptId      = intval($_POST['department_id'] ?? 0);
$role        = trim($_POST['role']          ?? 'user');
$viewScope   = trim($_POST['view_scope']    ?? 'own');
$isActive    = isset($_POST['is_active']) ? 1 : 1; // default active

// Validate
if (empty($username) || empty($password) || empty($deptId)) {
    echo json_encode(['success' => false, 'message' => 'Username, password and department are required']); exit;
}
if (strlen($username) < 3 || strlen($username) > 50) {
    echo json_encode(['success' => false, 'message' => 'Username must be 3–50 characters']); exit;
}
if (strlen($password) < 8) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters']); exit;
}

$allowedRoles = ['user', 'admin'];
if ($_SESSION['role'] === 'superadmin') $allowedRoles[] = 'superadmin';
if (!in_array($role, $allowedRoles)) {
    echo json_encode(['success' => false, 'message' => 'Invalid role']); exit;
}

if (!in_array($viewScope, ['own', 'granted', 'all'])) $viewScope = 'own';

try {
    // Check username duplicate
    $dup = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $dup->execute([$username]);
    if ($dup->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already taken']); exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, department_id, role, view_scope, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt->execute([$username, $hash, $deptId, $role, $viewScope]);
    $newId = $pdo->lastInsertId();

    // Log activity
    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'create', ?, ?, NOW())")
            ->execute([$_SESSION['user_id'], "Created user '$username' (role: $role)", $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (PDOException $e) {}

    echo json_encode(['success' => true, 'message' => "User '$username' created successfully", 'user_id' => $newId]);

} catch (PDOException $e) {
    error_log("Add user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
