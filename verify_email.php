<?php
session_start();
require 'db.php';

$token   = trim($_GET['token'] ?? '');
$message = '';
$success = false;

if (empty($token)) {
    $message = "Invalid verification link.";
} else {
    $stmt = $pdo->prepare("
        SELECT id, email_verified_at, email_token_expires_at 
        FROM users 
        WHERE email_verify_token = ? 
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        $message = "Verification link is invalid or has already been used.";
    } elseif ($user['email_verified_at'] !== null) {
        $message = "Your email is already verified. You can log in.";
        $success = true;
    } elseif (strtotime($user['email_token_expires_at']) < time()) {
        $message = "This verification link has expired. Please register again.";
    } else {
        // Mark as verified
        $pdo->prepare("
            UPDATE users 
            SET email_verified_at    = NOW(),
                email_verify_token   = NULL,
                email_token_expires_at = NULL,
                is_active            = 1
            WHERE id = ?
        ")->execute([$user['id']]);

        $success = true;
        $message = "Email verified! Your account is now active.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification — NewsRoom</title>
    <!-- reuse the same purple design tokens from register.php -->
</head>
<body>
    <h2><?= $success ? '✅ ' : '❌ ' ?><?= htmlspecialchars($message) ?></h2>
    <?php if ($success): ?>
        <a href="login.php">Go to Login</a>
    <?php else: ?>
        <a href="register.php">Register again</a>
    <?php endif; ?>
</body>
</html>