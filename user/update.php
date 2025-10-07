<?php
require '../auth.php';
require '../db.php';

$id = $_GET['id'] ?? null;
if (!$id) header("Location: user_dashboard.php");

// Fetch news
if ($_SESSION['role'] === 'admin') {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id=?");
    $stmt->execute([$id]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id=? AND department_id=?");
    $stmt->execute([$id, $_SESSION['department_id']]);
}
$news = $stmt->fetch();
if (!$news) die("Not allowed.");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $push = isset($_POST['push']) ? 1 : 0;

    $fileName = $news['attachment']; // keep old file
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = "uploads/";
        $fileName = time() . "_" . basename($_FILES['attachment']['name']);
        $targetFile = $uploadDir . $fileName;
        move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile);
    }
    if (isset($_POST['remove_attachment'])) {
        $fileName = null;
    }

    if ($_SESSION['role'] === 'admin') {
        $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, is_pushed=?, attachment=? WHERE id=?");
        $stmt->execute([$title, $content, $push, $fileName, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, is_pushed=?, attachment=? WHERE id=? AND department_id=?");
        $stmt->execute([$title, $content, $push, $fileName, $id, $_SESSION['department_id']]);
    }

    header("Location: user_dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit News</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .error {
            border-color: #ef4444; 
        }

        .error-message { 
            color: #ef4444; 
            font-size: 0.875rem; 
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
            line-height: 1.6;
        }

    </style>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-2xl w-full bg-white p-8 rounded-lg shadow-lg">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit News</h2>
        
        <form method="post" enctype="multipart/form-data" class="space-y-6" id="newsForm">
            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                <input 
                    type="text" 
                    name="title" 
                    id="title" 
                    value="<?= htmlspecialchars($news['title']) ?>" 
                    required
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                >
                <p class="error-message hidden" id="title-error">Title is required</p>
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                <textarea 
                    name="content" 
                    id="content" 
                    required
                    rows="6"
                    class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                ><?= htmlspecialchars($news['content']) ?></textarea>
                <p class="error-message hidden" id="content-error">Content is required</p>
            </div>

            <div>
                <label class="flex items-center">
                    <input 
                        type="checkbox" 
                        name="push" 
                        value="1" 
                        <?= $news['is_pushed'] ? 'checked' : '' ?> 
                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                    >
                    <span class="ml-2 text-sm text-gray-700">Push this update</span>
                </label>
            </div>

            <?php if ($news['attachment']): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Current Attachment</label>
                    <p class="mt-1 text-sm text-gray-600">
                        <a href="uploads/<?= $news['attachment'] ?>" target="_blank" class="text-blue-600 hover:underline">
                            <?= htmlspecialchars($news['attachment']) ?>
                        </a>
                    </p>
                    <label class="flex items-center mt-2">
                        <input 
                            type="checkbox" 
                            name="remove_attachment" 
                            value="1" 
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                        >
                        <span class="ml-2 text-sm text-gray-700">Remove attachment</span>
                    </label>
                </div>
            <?php endif; ?>

            <div>
                <label for="attachment" class="block text-sm font-medium text-gray-700">Upload New Attachment</label>
                <input 
                    type="file" 
                    name="attachment" 
                    id="attachment"
                    class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                >
            </div>

            <div class="flex justify-between items-center">
                <a href="user_dashboard.php" class="text-blue-600 hover:underline flex items-center">
                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
                <button 
                    type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Update News
                </button>
            </div>
        </form>
    </div>

    <script>
        // Client-side form validation
        document.getElementById('newsForm').addEventListener('submit', function(e) {
            let valid = true;
            const title = document.getElementById('title');
            const content = document.getElementById('content');
            const titleError = document.getElementById('title-error');
            const contentError = document.getElementById('content-error');

            // Reset error states
            title.classList.remove('error');
            content.classList.remove('error');
            titleError.classList.add('hidden');
            contentError.classList.add('hidden');

            // Validate title
            if (!title.value.trim()) {
                valid = false;
                title.classList.add('error');
                titleError.classList.remove('hidden');
            }

            // Validate content
            if (!content.value.trim()) {
                valid = false;
                content.classList.add('error');
                contentError.classList.remove('hidden');
            }

            if (!valid) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>