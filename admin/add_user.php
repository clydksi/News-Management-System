<?php
require '../db.php'; // Database connection
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method."]);
    exit;
}

// Get POST data
$username     = trim($_POST['username'] ?? '');
$password     = $_POST['password'] ?? '';
$role         = $_POST['role'] ?? '';
$departmentId = isset($_POST['department']) && $_POST['department'] !== '' ? (int)$_POST['department'] : null;

// Validate required fields
$missingFields = [];
if (!$username) $missingFields[] = "Username";
if (!$password) $missingFields[] = "Password";
if (!$role) $missingFields[] = "Role";
if (!$departmentId) $missingFields[] = "Department";

if (!empty($missingFields)) {
    echo json_encode([
        "success" => false,
        "message" => "Missing required fields: " . implode(", ", $missingFields)
    ]);
    exit;
}

// Validate role
$allowedRoles = ['admin', 'user', 'superadmin'];
if (!in_array($role, $allowedRoles)) {
    echo json_encode(["success" => false, "message" => "Invalid role selected."]);
    exit;
}

// Validate department exists in database
$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE id = ?");
$stmtCheck->execute([$departmentId]);
if ($stmtCheck->fetchColumn() == 0) {
    echo json_encode(["success" => false, "message" => "Selected department does not exist."]);
    exit;
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $pdo->prepare("
        INSERT INTO users (username, password, role, department_id, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$username, $hashedPassword, $role, $departmentId]);

    echo json_encode([
        "success" => true,
        "message" => "User '$username' has been added."
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => "Database error: " . $e->getMessage()
    ]);
}
