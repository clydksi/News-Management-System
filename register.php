<?php
require 'db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $department_id = $_POST['department_id'];

    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username=?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        $error = "Username already taken!";
    } else {
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Default role = user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, department_id, role) VALUES (?, ?, ?, 'user')");
        $stmt->execute([$username, $hashedPassword, $department_id]);

        $success = "Account created successfully! <a href='login.php'>Login here</a>";
    }
}

// Fetch departments
$departments = $pdo->query("SELECT * FROM departments")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account - Join Our Team</title>
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

        .register-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
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

        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .register-header h2 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .register-header p {
            color: #666;
            font-size: 16px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: #fff;
            appearance: none;
        }

        .form-group select {
            padding-right: 45px;
            cursor: pointer;
        }

        .form-group input:focus,
        .form-group select:focus {
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
            pointer-events: none;
        }

        .form-group .select-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            pointer-events: none;
            transition: transform 0.3s ease;
        }

        .form-group select:focus + .select-arrow {
            transform: translateY(-50%) rotate(180deg);
        }

        .register-btn {
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

        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .register-btn:active {
            transform: translateY(0);
        }

        .register-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
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

        .success-message {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
            animation: bounce 0.6s ease-out;
        }

        .success-message a {
            color: white;
            text-decoration: underline;
            font-weight: bold;
        }

        .success-message a:hover {
            text-decoration: none;
        }

        @keyframes shake {
            0%, 20%, 40%, 60%, 80% { transform: translateX(0); }
            10%, 30%, 50%, 70% { transform: translateX(-5px); }
            15%, 35%, 55%, 75% { transform: translateX(5px); }
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
        }

        .login-link p {
            color: #666;
            font-size: 14px;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-link a:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 8px;
            height: 4px;
            border-radius: 2px;
            overflow: hidden;
            background: #e1e5e9;
            transition: all 0.3s ease;
        }

        .password-strength.weak { background: linear-gradient(90deg, #ff6b6b 0%, #ff6b6b 33%, #e1e5e9 33%); }
        .password-strength.medium { background: linear-gradient(90deg, #ffd43b 0%, #ffd43b 66%, #e1e5e9 66%); }
        .password-strength.strong { background: linear-gradient(90deg, #51cf66 0%, #51cf66 100%); }

        .strength-text {
            font-size: 12px;
            margin-top: 4px;
            transition: all 0.3s ease;
        }

        .strength-text.weak { color: #ff6b6b; }
        .strength-text.medium { color: #ffd43b; }
        .strength-text.strong { color: #51cf66; }

        /* Form validation */
        .form-group.invalid input,
        .form-group.invalid select {
            border-color: #ff6b6b;
            animation: shake 0.3s ease-in-out;
        }

        .form-group.valid input,
        .form-group.valid select {
            border-color: #51cf66;
        }

        /* Responsive design */
        @media (max-width: 480px) {
            .register-container {
                padding: 30px 25px;
                margin: 10px;
            }

            .register-header h2 {
                font-size: 24px;
            }
        }

        /* Loading animation */
        .register-btn.loading {
            position: relative;
            color: transparent;
        }

        .register-btn.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .requirements {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }

        .requirements h4 {
            color: #333;
            margin-bottom: 8px;
        }

        .requirements ul {
            margin-left: 20px;
        }

        .requirements li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2>Create Account</h2>
            <p>Join our team and get started</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?= $success ?>
            </div>
        <?php else: ?>
            <div class="requirements">
                <h4>Account Requirements:</h4>
                <ul>
                    <li>Username must be unique</li>
                    <li>Password should be at least 8 characters</li>
                    <li>Select your department</li>
                </ul>
            </div>

            <form method="post" id="registerForm">
                <div class="form-group">
                    <input type="text" name="username" id="username" placeholder="Username" required>
                    <span class="icon">👤</span>
                </div>

                <div class="form-group">
                    <input type="password" name="password" id="password" placeholder="Password (min. 8 characters)" required minlength="8">
                    <span class="icon">🔒</span>
                    <div class="password-strength" id="passwordStrength"></div>
                    <div class="strength-text" id="strengthText"></div>
                </div>

                <div class="form-group">
                    <select name="department_id" id="department" required>
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="select-arrow">▼</span>
                </div>

                <button type="submit" class="register-btn" id="registerBtn">
                    Create Account
                </button>
            </form>
        <?php endif; ?>

        <div class="login-link">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>

    <script>
        // Password strength checker
        function checkPasswordStrength(password) {
            let score = 0;
            if (password.length >= 8) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^A-Za-z0-9]/.test(password)) score++;

            return score;
        }

        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthText.textContent = '';
                return;
            }

            const strength = checkPasswordStrength(password);
            
            strengthBar.className = 'password-strength';
            strengthText.className = 'strength-text';

            if (strength <= 2) {
                strengthBar.classList.add('weak');
                strengthText.classList.add('weak');
                strengthText.textContent = 'Weak password';
            } else if (strength <= 3) {
                strengthBar.classList.add('medium');
                strengthText.classList.add('medium');
                strengthText.textContent = 'Medium password';
            } else {
                strengthBar.classList.add('strong');
                strengthText.classList.add('strong');
                strengthText.textContent = 'Strong password';
            }
        });

        // Form validation and submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('registerBtn');
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const department = document.getElementById('department');

            // Visual validation
            [username, password, department].forEach(field => {
                if (!field.value.trim()) {
                    field.parentElement.classList.add('invalid');
                    setTimeout(() => field.parentElement.classList.remove('invalid'), 3000);
                } else {
                    field.parentElement.classList.add('valid');
                }
            });

            // Check if form is valid
            if (username.value.trim() && password.value.length >= 8 && department.value) {
                btn.classList.add('loading');
                btn.disabled = true;
                btn.textContent = 'Creating Account...';
            }
        });

        // Input animations and validation
        document.querySelectorAll('input, select').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });

            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });

            // Real-time validation
            input.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.parentElement.classList.remove('invalid');
                    this.parentElement.classList.add('valid');
                } else {
                    this.parentElement.classList.remove('valid');
                }
            });
        });

        // Username availability checker (visual only - actual check happens on server)
        let usernameTimeout;
        document.getElementById('username').addEventListener('input', function() {
            clearTimeout(usernameTimeout);
            const username = this.value.trim();
            
            if (username.length >= 3) {
                usernameTimeout = setTimeout(() => {
                    // Simulate checking animation
                    this.style.borderColor = '#ffd43b';
                    setTimeout(() => {
                        this.style.borderColor = '#51cf66';
                    }, 500);
                }, 500);
            }
        });

        // Prevent multiple submissions
        let submitted = false;
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            if (submitted) {
                e.preventDefault();
                return false;
            }
            submitted = true;
        });
    </script>
</body>
</html>