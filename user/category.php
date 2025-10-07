<?php
require '../auth.php';
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $user = $_SESSION['user_id'];

if (!empty($name)) {
    $stmt = $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)");
    $stmt->execute([$name, $user]);
    header("Location: user_dashboard.php");
    exit;
}

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Category - Share Your Story</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 700px; margin: 0 auto; animation: fadeIn 0.6s ease-out; }
        @keyframes fadeIn { from {opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }
        .header {
            background: rgba(255,255,255,0.95);
            border-radius: 20px 20px 0 0;
            padding: 25px 35px;
            display: flex; justify-content: space-between; align-items: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .header h2 { font-size: 26px; color: #333; display: flex; align-items: center; gap: 10px; }
        .header .icon { font-size: 28px; background: linear-gradient(135deg,#43cea2,#185a9d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .back-btn {
            padding: 10px 20px; background: linear-gradient(135deg,#43cea2,#185a9d); color: #fff;
            border-radius: 10px; text-decoration: none; font-weight: 600;
            box-shadow: 0 4px 12px rgba(67,206,162,0.3); transition: 0.3s;
        }
        .back-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67,206,162,0.4); }
        .form-container {
            background: rgba(255,255,255,0.95);
            border-radius: 0 0 20px 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { font-weight: 600; display: block; margin-bottom: 8px; color: #333; }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%; padding: 14px 18px;
            border: 2px solid #e1e5e9; border-radius: 10px; font-size: 15px;
            transition: 0.3s; resize: vertical;
        }
        .form-group input:focus, .form-group textarea:focus {
            border-color: #43cea2; box-shadow: 0 0 0 3px rgba(67,206,162,0.1); outline: none;
        }
        .form-group textarea { min-height: 120px; }
        .char-counter { font-size: 12px; color: #888; margin-top: 4px; text-align: right; }
        .submit-btn {
            width: 100%; padding: 15px;
            background: linear-gradient(135deg,#43cea2 0%,#185a9d 100%);
            border: none; border-radius: 12px; font-size: 17px; font-weight: 600;
            color: #fff; cursor: pointer; transition: 0.3s;
        }
        .submit-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(67,206,162,0.3); }
        .submit-btn:disabled { background: #ccc; cursor: not-allowed; }
        @media (max-width:600px){
            .header, .form-container { padding: 20px; }
            .header h2 { font-size: 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2><span class="icon">📂</span> Create Category</h2>
            <a href="user_dashboard.php" class="back-btn">⬅️ Back to Dashboard</a>
        </div>

        <div class="form-container">
            <form method="post" id="categoryForm">
                <div class="form-group">
                    <label for="name">📝 Category Name</label>
                    <input type="text" name="name" id="name" placeholder="Enter category name..." maxlength="100" required>
                    <div class="char-counter" id="nameCounter">0/100</div>
                </div>


                <button type="submit" class="submit-btn" id="submitBtn">🚀 Save Category</button>
            </form>
        </div>
    </div>

    <script>
        const nameInput = document.getElementById('name');
        const descInput = document.getElementById('description');
        const nameCounter = document.getElementById('nameCounter');
        const descCounter = document.getElementById('descCounter');

        nameInput.addEventListener('input', function(){
            const len = this.value.length;
            nameCounter.textContent = `${len}/100`;
            nameCounter.style.color = len > 90 ? "#ff6b6b" : "#888";
        });

        descInput.addEventListener('input', function(){
            descCounter.textContent = `${this.value.length} characters`;
        });

        const form = document.getElementById('categoryForm');
        const submitBtn = document.getElementById('submitBtn');

        form.addEventListener('submit', function(e){
            if (!nameInput.value.trim()) {
                e.preventDefault();
                alert("Please enter a category name.");
                return;
            }
            submitBtn.disabled = true;
            submitBtn.textContent = "⏳ Saving...";
        });
    </script>
</body>
</html>
