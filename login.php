<?php
session_start();
require 'db.php';
require 'csrf.php';

$error = '';

// --- Rate limiting ---
$maxAttempts = 5;
$lockoutSeconds = 300; // 5 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_first_attempt'] = time();
}

$isLockedOut = false;
if ($_SESSION['login_attempts'] >= $maxAttempts) {
    $elapsed = time() - $_SESSION['login_first_attempt'];
    if ($elapsed < $lockoutSeconds) {
        $remaining = $lockoutSeconds - $elapsed;
        $error = "Too many failed attempts. Please wait " . ceil($remaining / 60) . " minute(s).";
        $isLockedOut = true;
    } else {
        $_SESSION['login_attempts'] = 0;
        $_SESSION['login_first_attempt'] = time();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isLockedOut) {
    // CSRF check
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = "Invalid request. Please try again.";
    } else {
        $username = isset($_POST['username']) ? trim($_POST['username']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        if ($username === '' || $password === '') {
            $error = "Please enter both username and password!";
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Reset rate limit on success
                $_SESSION['login_attempts'] = 0;
                $_SESSION['login_first_attempt'] = time();

                $update = $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?");
                $update->execute([$user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['department_id'] = $user['department_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];

                if ($user['role'] === 'admin') {
                    header("Location: admin/admin_dashboard.php");
                } else {
                    header("Location: user/user_dashboard.php");
                }
                exit;
            } else {
                $_SESSION['login_attempts']++;
                $error = "Invalid credentials!";
            }
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Welcome Back</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .login-header h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .login-header p {
            color: #666;
            font-size: 16px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .form-group input::placeholder {
            color: #999;
        }

        .form-group .icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            font-size: 18px;
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .error-message {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 20%, 40%, 60%, 80% { transform: translateX(0); }
            10%, 30%, 50%, 70% { transform: translateX(-5px); }
            15%, 35%, 55%, 75% { transform: translateX(5px); }
        }

        .register-link {
            text-align: center;
            margin-top: 20px;
        }

        .register-link p {
            color: #666;
            font-size: 14px;
        }

        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .register-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }

        .forgot-password a {
            color: #999;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .forgot-password a:hover {
            color: #667eea;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 25px;
                margin: 10px;
            }

            .login-header h2 {
                font-size: 24px;
            }
        }

        /* Loading state */
        .login-btn.loading {
            background: #ccc;
            cursor: not-allowed;
            position: relative;
        }

        .login-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            margin: auto;
            border: 2px solid transparent;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>Saas System</h2>
            <p>Please sign in to your account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" id="loginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <div class="form-group">
                <input type="text" name="username" placeholder="Username" required>
                <span class="icon">👤</span>
            </div>

            <div class="form-group">
                <input type="password" name="password" placeholder="Password" required>
                <span class="icon">🔒</span>
            </div>

            <button type="submit" class="login-btn" id="loginBtn">
                Sign In
            </button>
        </form>

        <div class="forgot-password">
            <a href="" onclick="alert('Please contact your administrator to reset your password.')">
                Forgot your password?
            </a>
        </div>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create one here</a></p>
        </div>
    </div>

    <script>
        // Add loading state to form submission
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.classList.add('loading');
            btn.disabled = true;
            btn.textContent = 'Signing in...';
        });

        // Add subtle animations to form inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Prevent multiple form submissions
        let submitted = false;
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
        });
    </script>
</body>
</html>