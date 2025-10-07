<?php
session_start();
require 'db.php';

$token = $_GET['token'] ?? null;
$error = '';
$success = false;

if (!$token) {
    $error = 'Invalid or missing token.';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $token = $_POST['token'] ?? '';

    if (empty($password) || empty($confirm)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Validate token
        $stmt = $pdo->prepare("SELECT user_id, expires_at FROM password_resets WHERE token=? LIMIT 1");
        $stmt->execute([$token]);
        $reset = $stmt->fetch();

        if (!$reset) {
            $error = 'Invalid token.';
        } elseif (strtotime($reset['expires_at']) < time()) {
            $error = 'Token has expired.';
        } else {
            // Update password
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $update = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            $update->execute([$hashed, $reset['user_id']]);

            // Delete the token
            $del = $pdo->prepare("DELETE FROM password_resets WHERE user_id=?");
            $del->execute([$reset['user_id']]);

            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <style>
        body {
            font-family: sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #f0f2f5;
        }
        .reset-container {
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .reset-container h2 { text-align: center; margin-bottom: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group input {
            width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ccc;
        }
        .btn {
            width: 100%; padding: 12px; background: #667eea; color: white; border: none;
            border-radius: 8px; cursor: pointer; font-size: 16px;
        }
        .btn:hover { background: #5a67d8; }
        .error { color: red; margin-bottom: 15px; text-align: center; }
        .success { color: green; margin-bottom: 15px; text-align: center; }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2>Reset Password</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">Password has been reset successfully! <a href="login.php">Login now</a></div>
        <?php else: ?>
            <form method="post">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <input type="password" name="password" placeholder="New Password" required>
                </div>
                <div class="form-group">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>
                <button type="submit" class="btn">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
