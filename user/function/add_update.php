<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

$parent_id = $_GET['parent_id'] ?? null;
if (!$parent_id) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Get parent article with full details
$stmt = $pdo->prepare("
    SELECT n.*, 
           u.username as author_name,
           d.name as dept_name,
           c.name as category_name,
           (SELECT COUNT(*) FROM news WHERE parent_article_id = n.id) as update_count
    FROM news n
    JOIN users u ON n.created_by = u.id
    JOIN departments d ON n.department_id = d.id
    LEFT JOIN categories c ON n.category_id = c.id
    WHERE n.id = ?
");
$stmt->execute([$parent_id]);
$parent_article = $stmt->fetch();

if (!$parent_article) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Check if user has permission
if ($_SESSION['role'] !== 'admin' && $_SESSION['department_id'] != $parent_article['department_id']) {
    die("Unauthorized access");
}

// Get existing updates for this parent
$stmt = $pdo->prepare("
    SELECT n.*, 
           u.username as author_name,
           d.name as dept_name
    FROM news n
    JOIN users u ON n.created_by = u.id
    JOIN departments d ON n.department_id = d.id
    WHERE n.parent_article_id = ?
    ORDER BY n.update_number ASC, n.created_at ASC
");
$stmt->execute([$parent_id]);
$existing_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get next update number
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(update_number), 0) + 1 as next_number 
            FROM news 
            WHERE parent_article_id = ?
        ");
        $stmt->execute([$parent_id]);
        $next_number = $stmt->fetchColumn();
        
        // Handle thumbnail upload
        $thumbnailPath = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                // Validate file size (max 10MB)
                if ($_FILES['thumbnail']['size'] <= 10 * 1024 * 1024) {
                    $newFileName = uniqid('thumb_') . '.' . $fileExtension;
                    $thumbnailPath = 'uploads/' . $newFileName;
                    move_uploaded_file($_FILES['thumbnail']['tmp_name'], dirname(__DIR__) . '/' . $thumbnailPath);
                } else {
                    throw new Exception("Image file size must be less than 10MB");
                }
            } else {
                throw new Exception("Invalid image format. Allowed: JPG, PNG, GIF, WEBP");
            }
        }
        
        // Validate input
        if (empty($_POST['title']) || empty($_POST['content']) || empty($_POST['update_type'])) {
            throw new Exception("Please fill in all required fields");
        }
        
        // Insert update article
        $stmt = $pdo->prepare("
            INSERT INTO news (
                title, content, thumbnail, parent_article_id, is_update, 
                update_type, update_number, category_id, department_id,
                created_by, is_pushed, created_at
            ) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 0, NOW())
        ");
        
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['content']),
            $thumbnailPath,
            $parent_id,
            $_POST['update_type'],
            $next_number,
            $parent_article['category_id'],
            $parent_article['department_id'],
            $_SESSION['user_id']
        ]);
        
        $success = true;
        $new_update_id = $pdo->lastInsertId();
        
        // Redirect back to dashboard with success message
        header("Location: ../user_dashboard.php?update_success=1&parent_id=" . $parent_id . "&update_id=" . $new_update_id);
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Calculate next update number for display
$next_update_number = count($existing_updates) + 1;

// Helper function
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Add Update #<?= $next_update_number ?> - <?= e($parent_article['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <style>
        body { 
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
        }
        
        @keyframes fadeIn { 
            from { opacity: 0; transform: translateY(10px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .fade-in { animation: fadeIn 0.4s ease-out; }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .slide-in { animation: slideInRight 0.4s ease-out; }
        
        .update-type-card {
            transition: all 0.2s ease;
        }
        
        .update-type-card:hover {
            transform: translateY(-2px);
        }
        
        /* Timeline styles */
        .timeline-item {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 1rem;
            bottom: -1rem;
            width: 2px;
            background: linear-gradient(to bottom, #f97316, #dc2626);
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 0.25rem;
            top: 1rem;
            width: 12px;
            height: 12px;
            background: white;
            border: 3px solid #f97316;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.1);
        }
        
        /* Character counter */
        .char-counter {
            transition: color 0.3s ease;
        }
        
        .char-counter.warning {
            color: #f59e0b;
        }
        
        .char-counter.danger {
            color: #ef4444;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Main Form Area (Left - 2 columns) -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Header -->
                <div class="bg-white rounded-2xl shadow-lg p-6 fade-in">
                    <div class="flex items-center mb-4">
                        <a href="../user_dashboard.php" class="mr-4 text-purple-600 hover:text-purple-800 transition-colors">
                            <span class="material-icons text-3xl">arrow_back</span>
                        </a>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <h1 class="text-3xl font-bold text-gray-800">Add Update #<?= $next_update_number ?></h1>
                                <span class="bg-gradient-to-r from-orange-100 to-red-100 text-orange-700 px-3 py-1 rounded-full text-sm font-bold">
                                    NEW
                                </span>
                            </div>
                            <p class="text-gray-500 text-sm">Create a developing story update</p>
                        </div>
                    </div>
                    
                    <!-- Parent Article Info -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl border-l-4 border-blue-600">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <p class="text-sm text-blue-600 font-semibold mb-1 flex items-center">
                                    <span class="material-icons text-sm mr-1">account_tree</span>
                                    Adding update to:
                                </p>
                                <h3 class="text-lg font-bold text-gray-800"><?= e($parent_article['title']) ?></h3>
                            </div>
                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-bold">
                                <?= $parent_article['update_count'] ?> existing
                            </span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-gray-600 mt-3">
                            <span class="flex items-center">
                                <span class="material-icons text-xs mr-1">person</span>
                                <?= e($parent_article['author_name']) ?>
                            </span>
                            <span class="flex items-center">
                                <span class="material-icons text-xs mr-1">schedule</span>
                                <?= date('M d, Y g:i A', strtotime($parent_article['created_at'])) ?>
                            </span>
                            <span class="flex items-center">
                                <span class="material-icons text-xs mr-1">label</span>
                                <?= e($parent_article['category_name'] ?: 'Uncategorized') ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Error Message -->
                <?php if ($error): ?>
                <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded-xl shadow-sm fade-in">
                    <div class="flex items-center">
                        <span class="material-icons text-red-500 mr-3">error</span>
                        <div>
                            <p class="font-bold text-red-700">Error</p>
                            <p class="text-red-600 text-sm"><?= e($error) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="bg-white rounded-2xl shadow-lg p-6 space-y-6 fade-in" id="updateForm">
                    <!-- Update Type Selection -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-3 flex items-center">
                            <span class="material-icons text-purple-600 mr-2">label</span>
                            Update Type *
                        </label>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <!-- Developing -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all">
                                <input type="radio" name="update_type" value="Developing" required class="sr-only peer">
                                <div class="text-center peer-checked:text-purple-600">
                                    <span class="material-icons text-2xl mb-1">sync</span>
                                    <p class="font-semibold text-xs">Developing</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-purple-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                            
                            <!-- Breaking -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-red-500 hover:bg-red-50 transition-all">
                                <input type="radio" name="update_type" value="Breaking" class="sr-only peer">
                                <div class="text-center peer-checked:text-red-600">
                                    <span class="material-icons text-2xl mb-1">notification_important</span>
                                    <p class="font-semibold text-xs">Breaking</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-red-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                            
                            <!-- Update -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all">
                                <input type="radio" name="update_type" value="Update" class="sr-only peer">
                                <div class="text-center peer-checked:text-blue-600">
                                    <span class="material-icons text-2xl mb-1">update</span>
                                    <p class="font-semibold text-xs">Update</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-blue-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                            
                            <!-- Latest -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-green-500 hover:bg-green-50 transition-all">
                                <input type="radio" name="update_type" value="Latest" class="sr-only peer">
                                <div class="text-center peer-checked:text-green-600">
                                    <span class="material-icons text-2xl mb-1">new_releases</span>
                                    <p class="font-semibold text-xs">Latest</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-green-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                            
                            <!-- Correction -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-yellow-500 hover:bg-yellow-50 transition-all">
                                <input type="radio" name="update_type" value="Correction" class="sr-only peer">
                                <div class="text-center peer-checked:text-yellow-600">
                                    <span class="material-icons text-2xl mb-1">edit</span>
                                    <p class="font-semibold text-xs">Correction</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-yellow-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                            
                            <!-- Final -->
                            <label class="update-type-card relative flex flex-col items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-gray-600 hover:bg-gray-50 transition-all">
                                <input type="radio" name="update_type" value="Final" class="sr-only peer">
                                <div class="text-center peer-checked:text-gray-600">
                                    <span class="material-icons text-2xl mb-1">check_circle</span>
                                    <p class="font-semibold text-xs">Final</p>
                                </div>
                                <div class="absolute inset-0 border-2 border-gray-600 rounded-xl opacity-0 peer-checked:opacity-100 pointer-events-none"></div>
                            </label>
                        </div>
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <span class="material-icons text-xs mr-1">info</span>
                            Select the type of update that best describes this development
                        </p>
                    </div>

                    <!-- Update Title -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                            <span class="material-icons text-purple-600 mr-2">title</span>
                            Update Title *
                        </label>
                        <input type="text" name="title" required id="titleInput"
                            class="w-full p-4 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all"
                            placeholder="e.g., Rescue operations continue at disaster site">
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-xs text-gray-500 flex items-center">
                                <span class="material-icons text-xs mr-1">lightbulb</span>
                                Be clear and concise about what's new
                            </p>
                            <span class="text-xs text-gray-500 char-counter" id="titleCounter">0/200</span>
                        </div>
                    </div>

                    <!-- Thumbnail Upload -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                            <span class="material-icons text-purple-600 mr-2">image</span>
                            Update Image (Optional)
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-xl p-6 text-center hover:border-purple-500 transition-all" id="uploadArea">
                            <input type="file" name="thumbnail" accept="image/*" id="thumbnailInput" class="hidden">
                            <label for="thumbnailInput" class="cursor-pointer">
                                <span class="material-icons text-5xl text-gray-400 mb-2">cloud_upload</span>
                                <p class="text-gray-600 font-medium">Click to upload image</p>
                                <p class="text-sm text-gray-400 mt-1">PNG, JPG, GIF, WEBP up to 10MB</p>
                            </label>
                            <div id="imagePreview" class="mt-4 hidden">
                                <img id="previewImg" class="max-h-48 mx-auto rounded-lg shadow-lg">
                                <button type="button" onclick="removeImage()" class="mt-2 text-red-600 hover:text-red-700 text-sm font-medium">
                                    <span class="material-icons text-sm align-middle">delete</span>
                                    Remove Image
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Update Content -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                            <span class="material-icons text-purple-600 mr-2">article</span>
                            Update Content *
                        </label>
                        <textarea name="content" required rows="12" id="contentInput"
                            class="w-full p-4 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all resize-none"
                            placeholder="Provide detailed information about this update. Include facts, quotes, and context that readers need to know..."></textarea>
                        <div class="flex justify-between items-center mt-2">
                            <p class="text-xs text-gray-500 flex items-center">
                                <span class="material-icons text-xs mr-1">info</span>
                                Provide clear and factual information about the development
                            </p>
                            <span class="text-xs text-gray-500 char-counter" id="contentCounter">0/5000</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" id="submitBtn"
                            class="flex-1 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-6 py-4 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl flex items-center justify-center">
                            <span class="material-icons mr-2">publish</span>
                            Publish Update #<?= $next_update_number ?>
                        </button>
                        <a href="../user_dashboard.php"
                            class="px-6 py-4 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-bold transition-all flex items-center justify-center">
                            <span class="material-icons mr-2">cancel</span>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Sidebar (Right - 1 column) -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Tips Card -->
                <div class="bg-gradient-to-br from-purple-50 to-blue-50 rounded-2xl shadow-lg p-6 slide-in border border-purple-200">
                    <div class="flex items-center mb-4">
                        <div class="w-10 h-10 bg-purple-600 rounded-lg flex items-center justify-center mr-3">
                            <span class="material-icons text-white">tips_and_updates</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-800">Update Tips</h3>
                    </div>
                    <ul class="space-y-3 text-sm text-gray-700">
                        <li class="flex items-start">
                            <span class="material-icons text-purple-600 text-sm mr-2 mt-0.5">check_circle</span>
                            <span><strong>Be timely:</strong> Post updates as developments occur</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons text-purple-600 text-sm mr-2 mt-0.5">check_circle</span>
                            <span><strong>Stay factual:</strong> Only include verified information</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons text-purple-600 text-sm mr-2 mt-0.5">check_circle</span>
                            <span><strong>Add context:</strong> Explain how this relates to earlier updates</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons text-purple-600 text-sm mr-2 mt-0.5">check_circle</span>
                            <span><strong>Use images:</strong> Visual updates engage readers better</span>
                        </li>
                        <li class="flex items-start">
                            <span class="material-icons text-purple-600 text-sm mr-2 mt-0.5">check_circle</span>
                            <span><strong>Choose type wisely:</strong> "Breaking" for major news, "Update" for regular developments</span>
                        </li>
                    </ul>
                </div>

                <!-- Existing Updates Timeline -->
                <?php if (!empty($existing_updates)): ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 slide-in">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center">
                            <span class="material-icons text-orange-600 mr-2">timeline</span>
                            Previous Updates
                        </h3>
                        <span class="bg-orange-100 text-orange-700 px-2 py-1 rounded-full text-xs font-bold">
                            <?= count($existing_updates) ?>
                        </span>
                    </div>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach ($existing_updates as $update): ?>
                        <div class="timeline-item">
                            <div class="bg-gradient-to-r from-orange-50 to-red-50 p-3 rounded-lg border border-orange-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full text-xs font-bold">
                                        <?= e($update['update_type']) ?> #<?= $update['update_number'] ?>
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        <?= date('M d, g:i A', strtotime($update['created_at'])) ?>
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-800 text-sm mb-1 line-clamp-2">
                                    <?= e($update['title']) ?>
                                </h4>
                                <p class="text-xs text-gray-600 flex items-center">
                                    <span class="material-icons text-xs mr-1">person</span>
                                    <?= e($update['author_name']) ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-white rounded-2xl shadow-lg p-6 slide-in text-center">
                    <span class="material-icons text-6xl text-gray-300 mb-3">update</span>
                    <h3 class="text-lg font-bold text-gray-800 mb-2">First Update</h3>
                    <p class="text-sm text-gray-600">This will be the first update for this article. Make it count!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Character counters
    const titleInput = document.getElementById('titleInput');
    const contentInput = document.getElementById('contentInput');
    const titleCounter = document.getElementById('titleCounter');
    const contentCounter = document.getElementById('contentCounter');

    titleInput.addEventListener('input', function() {
        const length = this.value.length;
        titleCounter.textContent = `${length}/200`;
        
        if (length > 180) {
            titleCounter.classList.add('danger');
            titleCounter.classList.remove('warning');
        } else if (length > 150) {
            titleCounter.classList.add('warning');
            titleCounter.classList.remove('danger');
        } else {
            titleCounter.classList.remove('warning', 'danger');
        }
        
        // Limit to 200 characters
        if (length > 200) {
            this.value = this.value.substring(0, 200);
        }
    });

    contentInput.addEventListener('input', function() {
        const length = this.value.length;
        contentCounter.textContent = `${length}/5000`;
        
        if (length > 4800) {
            contentCounter.classList.add('danger');
            contentCounter.classList.remove('warning');
        } else if (length > 4500) {
            contentCounter.classList.add('warning');
            contentCounter.classList.remove('danger');
        } else {
            contentCounter.classList.remove('warning', 'danger');
        }
        
        // Limit to 5000 characters
        if (length > 5000) {
            this.value = this.value.substring(0, 5000);
        }
    });

    // Image preview
    const thumbnailInput = document.getElementById('thumbnailInput');
    const imagePreview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const uploadArea = document.getElementById('uploadArea');

    thumbnailInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validate file size
            if (file.size > 10 * 1024 * 1024) {
                alert('Image file size must be less than 10MB');
                this.value = '';
                return;
            }
            
            // Validate file type
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid image format. Allowed: JPG, PNG, GIF, WEBP');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                imagePreview.classList.remove('hidden');
                uploadArea.classList.add('border-purple-500', 'bg-purple-50');
            };
            reader.readAsDataURL(file);
        }
    });

    function removeImage() {
        thumbnailInput.value = '';
        imagePreview.classList.add('hidden');
        uploadArea.classList.remove('border-purple-500', 'bg-purple-50');
    }

    // Form validation
    const form = document.getElementById('updateForm');
    const submitBtn = document.getElementById('submitBtn');

    form.addEventListener('submit', function(e) {
        // Check if update type is selected
        const updateType = document.querySelector('input[name="update_type"]:checked');
        if (!updateType) {
            e.preventDefault();
            alert('Please select an update type');
            return false;
        }
        
        // Check title
        const title = titleInput.value.trim();
        if (title.length < 10) {
            e.preventDefault();
            alert('Title must be at least 10 characters long');
            titleInput.focus();
            return false;
        }
        
        // Check content
        const content = contentInput.value.trim();
        if (content.length < 50) {
            e.preventDefault();
            alert('Content must be at least 50 characters long');
            contentInput.focus();
            return false;
        }
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="material-icons mr-2 animate-spin">sync</span>Publishing...';
    });

    // Warn before leaving if form has content
    let formModified = false;
    
    form.addEventListener('input', function() {
        formModified = true;
    });
    
    window.addEventListener('beforeunload', function(e) {
        if (formModified && !form.submitting) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
    
    form.addEventListener('submit', function() {
        form.submitting = true;
    });

    // Auto-resize textarea
    contentInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    </script>
</body>
</html>