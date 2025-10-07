<?php
session_start();
require 'db.php'; // include your PDO connection

if (isset($_SESSION['user_id'])) {
    // Mark user as inactive and update last_seen timestamp
    $stmt = $pdo->prepare("UPDATE users SET is_active = 0, last_seen = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie as well
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login
header("Location: login.php");
exit;
