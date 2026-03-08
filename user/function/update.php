<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

/**
 * Enhanced News Article Editor - WriterAI Pro Edition with Article Updates System
 * Features: Modern UI, Auto-save, File Management, Real-time Stats, Category Selection, Article Updates
 */

// ==================== HELPER FUNCTIONS ====================

function checkAttachmentsTable($pdo) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE 'attachments'");
        return $result->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function getCategoryIcon($categoryName) {
    $categoryName = strtolower($categoryName);
    $icons = [
        'technology' => 'computer',
        'tech' => 'computer',
        'business' => 'business_center',
        'finance' => 'paid',
        'health' => 'health_and_safety',
        'medical' => 'medical_services',
        'sports' => 'sports_soccer',
        'entertainment' => 'movie',
        'politics' => 'account_balance',
        'science' => 'science',
        'education' => 'school',
        'lifestyle' => 'emoji_people',
        'travel' => 'flight',
        'food' => 'restaurant',
        'fashion' => 'checkroom',
        'automotive' => 'directions_car',
        'real estate' => 'home',
        'environment' => 'eco',
        'art' => 'palette',
        'music' => 'music_note',
        'gaming' => 'sports_esports',
        'news' => 'newspaper',
        'opinion' => 'chat_bubble',
        'weather' => 'wb_sunny',
        'local' => 'location_on',
        'announcement' => 'campaign'
    ];
    
    foreach ($icons as $keyword => $icon) {
        if (strpos($categoryName, $keyword) !== false) {
            return $icon;
        }
    }
    
    return 'folder'; // Default icon
}

function getFileIconSymbol($extension) {
    $iconMap = [
        'pdf' => 'picture_as_pdf',
        'doc' => 'description', 'docx' => 'description', 'txt' => 'description',
        'xls' => 'table_chart', 'xlsx' => 'table_chart', 'csv' => 'table_chart',
        'ppt' => 'slideshow', 'pptx' => 'slideshow',
        'jpg' => 'image', 'jpeg' => 'image', 'png' => 'image', 'gif' => 'image', 'webp' => 'image',
        'zip' => 'folder_zip', 'rar' => 'folder_zip', '7z' => 'folder_zip',
        'mp4' => 'videocam', 'avi' => 'videocam', 'mov' => 'videocam',
        'mp3' => 'audio_file', 'wav' => 'audio_file'
    ];
    return $iconMap[strtolower($extension)] ?? 'insert_drive_file';
}

function formatFileSize($bytes) {
    if (!$bytes || $bytes == 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i) * 100) / 100 . ' ' . $sizes[$i];
}

// ==================== DATA FETCHING ====================

$attachmentsTableExists = checkAttachmentsTable($pdo);
$newsId = $_GET['id'] ?? null;

if (!$newsId) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Fetch news article with permission checks - INCLUDING update-related fields
$newsQuery = "SELECT n.*, 
                     c.id as category_id, 
                     c.name as category_name,
                     n.parent_article_id,
                     n.is_update,
                     n.update_type,
                     n.update_number
              FROM news n 
              LEFT JOIN categories c ON n.category_id = c.id 
              WHERE n.id = ?";

if ($_SESSION['role'] !== 'admin') {
    $newsQuery .= " AND n.department_id = ?";
    $stmt = $pdo->prepare($newsQuery);
    $stmt->execute([$newsId, $_SESSION['department_id']]);
} else {
    $stmt = $pdo->prepare($newsQuery);
    $stmt->execute([$newsId]);
}

$news = $stmt->fetch();

if (!$news) {
    http_response_code(404);
    die("News article not found or you don't have permission to edit it.");
}

// Check if this article has updates
$updateCountStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE parent_article_id = ?");
$updateCountStmt->execute([$newsId]);
$updateCount = $updateCountStmt->fetchColumn();

// If this is an update article, fetch parent article info
$parentArticle = null;
if ($news['is_update'] && $news['parent_article_id']) {
    $parentStmt = $pdo->prepare("SELECT id, title FROM news WHERE id = ?");
    $parentStmt->execute([$news['parent_article_id']]);
    $parentArticle = $parentStmt->fetch();
}

// Fetch existing attachments
$existingAttachments = [];
if ($attachmentsTableExists) {
    try {
        $attachStmt = $pdo->prepare("SELECT * FROM attachments WHERE news_id = ? ORDER BY id ASC");
        $attachStmt->execute([$newsId]);
        $existingAttachments = $attachStmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to fetch attachments: " . $e->getMessage());
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ==================== FORM PROCESSING ====================

$response = ['success' => false, 'error' => null];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $categoryId = $_POST['category'] ?? $news['category_id'];

        // Validation
        if (empty($title) || empty($content)) {
            throw new Exception("Title and content are required.");
        }

        if (mb_strlen($title) > 200) {
            throw new Exception("Title cannot exceed 200 characters.");
        }

        // Handle thumbnail removal
        $thumbnail = $news['thumbnail'];
        if (!empty($_POST['remove_thumbnail']) && $_POST['remove_thumbnail'] == '1') {
            if ($thumbnail && file_exists(dirname(__DIR__) . '/' . $thumbnail)) {
                unlink(dirname(__DIR__) . '/' . $thumbnail);
            }
            $thumbnail = null;
        }

        // Handle new thumbnail upload
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = dirname(__DIR__) . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $fileTmp = $_FILES['thumbnail']['tmp_name'];
            $fileName = basename($_FILES['thumbnail']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                throw new Exception("Invalid file type. Allowed: " . implode(', ', $allowedExts));
            }

            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $fileTmp);
            finfo_close($finfo);
            if (!str_starts_with($mime, 'image/')) {
                throw new Exception("Invalid image file");
            }

            // Delete old thumbnail if exists
            if ($thumbnail && file_exists(dirname(__DIR__) . '/' . $thumbnail)) {
                unlink(dirname(__DIR__) . '/' . $thumbnail);
            }

            $newName = uniqid('thumb_', true) . '.' . $ext;
            $filePath = $uploadDir . $newName;

            if (move_uploaded_file($fileTmp, $filePath)) {
                $thumbnail = 'uploads/' . $newName;
            } else {
                throw new Exception("Failed to upload thumbnail");
            }
        }

        // Update news article
        $updateQuery = "UPDATE news SET title = ?, content = ?, category_id = ?, thumbnail = ?, updated_at = NOW() WHERE id = ?";
        $params = [$title, $content, $categoryId, $thumbnail, $newsId];

        if ($_SESSION['role'] !== 'admin') {
            $updateQuery .= " AND department_id = ?";
            $params[] = $_SESSION['department_id'];
        }

        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute($params);

        // Handle attachment deletions
        if ($attachmentsTableExists && !empty($_POST['delete_attachments'])) {
            $deletedCount = 0;
            foreach ((array)$_POST['delete_attachments'] as $attachmentId) {
                $attachmentId = (int)$attachmentId;
                
                $delStmt = $pdo->prepare("SELECT * FROM attachments WHERE id = ? AND news_id = ?");
                $delStmt->execute([$attachmentId, $newsId]);
                $attachment = $delStmt->fetch();
                
                if ($attachment) {
                    if (file_exists($attachment['file_path'])) {
                        unlink($attachment['file_path']);
                    }
                    
                    $pdo->prepare("DELETE FROM attachments WHERE id = ?")->execute([$attachmentId]);
                    $deletedCount++;
                }
            }
        }

        // Handle new file uploads
        if ($attachmentsTableExists && !empty($_FILES['new_attachments']['name'][0])) {
            $uploadDir = dirname(__DIR__) . "/uploads/attachments/";
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
                                 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'zip', 'rar', '7z', 
                                 'txt', 'csv', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
            $maxFileSize = 50 * 1024 * 1024; // 50MB
            $uploadedCount = 0;

            foreach ($_FILES['new_attachments']['name'] as $index => $fileName) {
                if ($_FILES['new_attachments']['error'][$index] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $fileSize = $_FILES['new_attachments']['size'][$index];
                $fileTmpName = $_FILES['new_attachments']['tmp_name'][$index];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($fileSize > $maxFileSize || !in_array($fileExtension, $allowedExtensions)) {
                    continue;
                }

                $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($fileName, PATHINFO_FILENAME));
                $uniqueFilename = $sanitizedName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $uniqueFilename;

                if (move_uploaded_file($fileTmpName, $uploadPath)) {
                    $mimeType = mime_content_type($uploadPath);
                    
                    $stmt = $pdo->prepare("INSERT INTO attachments (news_id, file_name, file_path, file_size, file_type, uploaded_by) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$newsId, $fileName, $uploadPath, $fileSize, $mimeType, $_SESSION['user_id']]);
                    $uploadedCount++;
                }
            }
        }

        header("Location: ../user_dashboard.php?success=article_updated");
        exit;

    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
        error_log("Article update error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WriterAI Pro - Edit Article<?= $news['is_update'] ? ' (Update)' : '' ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@300;400;500;600;700" rel="stylesheet"/>
    
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        /* Animations */
        @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
        @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); } 20%, 40%, 60%, 80% { transform: translateX(5px); } }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes confetti { 0% { transform: translateY(0) rotate(0deg); opacity: 1; } 100% { transform: translateY(100vh) rotate(720deg); opacity: 0; } }
        @keyframes progress { from { width: 100%; } to { width: 0%; } }
        
        .toast-enter { animation: slideInRight 0.3s ease-out; }
        .toast-exit { animation: slideOutRight 0.3s ease-in; }
        .bounce-animation { animation: bounce 0.5s ease-in-out; }
        .pulse-animation { animation: pulse 1s ease-in-out infinite; }
        .shake-animation { animation: shake 0.5s ease-in-out; }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        .confetti-piece { position: fixed; width: 10px; height: 10px; animation: confetti 3s ease-out forwards; z-index: 10000; pointer-events: none; }
        .toast-progress { position: absolute; bottom: 0; left: 0; height: 3px; background: currentColor; animation: progress linear; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        
        /* File input styling */
        input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            cursor: pointer;
        }
        
        /* Custom Select Dropdown */
        select {
            background-image: none;
        }
    </style>
    
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#3B82F6",
                        success: "#10B981",
                        warning: "#F59E0B",
                        error: "#EF4444",
                        "background-light": "#F9FAFB",
                        "background-dark": "#1F2937",
                        "panel-light": "#F3F4F6",
                        "panel-dark": "#111827"
                    },
                    boxShadow: {
                        'enhanced-light': '0 1px 3px rgba(0,0,0,0.02), 0 4px 10px rgba(0,0,0,0.06), 0 10px 24px rgba(0,0,0,0.08)',
                        'enhanced-dark': '0 1px 3px rgba(0,0,0,0.1), 0 4px 10px rgba(0,0,0,0.2), 0 10px 24px rgba(255,255,255,0.05)'
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark text-gray-900 dark:text-gray-100 min-h-screen">
    
    <!-- Main Container -->
    <div class="flex flex-col h-screen overflow-hidden">
        
        <!-- Header -->
        <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between h-16">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 text-primary">
                            <svg fill="currentColor" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                                <path d="M42.4379 44C42.4379 44 36.0744 33.9038 41.1692 24C46.8624 12.9336 42.2078 4 42.2078 4L7.01134 4C7.01134 4 11.6577 12.932 5.96912 23.9969C0.876273 33.9029 7.27094 44 7.27094 44L42.4379 44Z"></path>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold">WriterAI Pro</h1>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <?= $news['is_update'] ? 'Edit Update Article' : 'Edit Article' ?>
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <!-- Stats Badge -->
                        <div class="hidden sm:flex items-center gap-4 px-4 py-2 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs">
                            <span><strong id="wordCount">0</strong> words</span>
                            <span class="text-gray-300">|</span>
                            <span><strong id="readTime">0</strong> min read</span>
                        </div>
                        
                        <button type="button" id="saveDraftBtn" class="flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg font-medium text-sm transition-colors">
                            <span class="material-symbols-outlined text-lg">save</span>
                            <span class="hidden sm:inline">Draft</span>
                        </button>
                        
                        <a href="../user_dashboard.php" class="flex items-center gap-2 px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 rounded-lg font-medium text-sm transition-colors">
                            <span class="material-symbols-outlined text-lg">arrow_back</span>
                            <span class="hidden sm:inline">Back</span>
                        </a>
                        
                        <?php if (!$news['is_update']): ?>
                        <a href="add_update.php?parent_id=<?= $newsId ?>" class="flex items-center gap-2 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-semibold text-sm shadow-lg hover:shadow-xl transition-all">
                            <span class="material-symbols-outlined text-lg">add_circle</span>
                            <span class="hidden sm:inline">Add Update</span>
                        </a>
                        <?php endif; ?>
                        
                        <button type="button" id="updateBtn" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-blue-600 text-white rounded-lg font-semibold text-sm shadow-lg hover:shadow-xl transition-all">
                            <span class="material-symbols-outlined text-lg">check_circle</span>
                            <span>Update Article</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                
                <?php if ($response['error']): ?>
                <div class="mb-6 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded-lg fade-in">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-red-600">error</span>
                        <div>
                            <h4 class="font-bold text-red-800 dark:text-red-200">Error</h4>
                            <p class="text-sm text-red-700 dark:text-red-300"><?= htmlspecialchars($response['error']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <form id="articleForm" method="POST" enctype="multipart/form-data" class="space-y-8">
                    
                    <!-- Update Status Banner -->
                    <?php if ($news['is_update'] && $parentArticle): ?>
                        <!-- This is an Update Article -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 rounded-xl p-6 fade-in">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <span class="material-symbols-outlined text-3xl text-blue-600">link</span>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-blue-900 dark:text-blue-100 mb-2">
                                        📰 This is an Update Article
                                    </h3>
                                    <p class="text-sm text-blue-800 dark:text-blue-200 mb-3">
                                        Update Type: <strong class="uppercase"><?= htmlspecialchars($news['update_type'] ?? 'update') ?></strong> 
                                        • Update #<?= $news['update_number'] ?>
                                    </p>
                                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3 mb-3">
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Linked to Parent Article:</p>
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            <?= htmlspecialchars($parentArticle['title']) ?>
                                        </p>
                                    </div>
                                    <div class="flex gap-3">
                                        <a href="view_article_updates.php?id=<?= $news['parent_article_id'] ?>" 
                                           class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-white dark:bg-gray-800 px-3 py-2 rounded-lg border border-blue-300 dark:border-blue-700 transition-colors">
                                            <span class="material-symbols-outlined text-lg">visibility</span>
                                            View All Updates
                                        </a>
                                        <a href="update.php?id=<?= $news['parent_article_id'] ?>" 
                                           class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 bg-white dark:bg-gray-800 px-3 py-2 rounded-lg border border-blue-300 dark:border-blue-700 transition-colors">
                                            <span class="material-symbols-outlined text-lg">edit</span>
                                            Edit Parent Article
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($updateCount > 0): ?>
                        <!-- This Article Has Updates -->
                        <div class="bg-gradient-to-r from-orange-50 to-red-50 dark:from-orange-900/20 dark:to-red-900/20 border-l-4 border-red-500 rounded-xl p-6 fade-in">
                            <div class="flex items-start gap-4">
                                <div class="flex-shrink-0">
                                    <span class="material-symbols-outlined text-3xl text-red-600 pulse-animation">fiber_manual_record</span>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-red-900 dark:text-red-100 mb-2">
                                        🔴 Developing Story - <?= $updateCount ?> Update<?= $updateCount > 1 ? 's' : '' ?> Posted
                                    </h3>
                                    <p class="text-sm text-red-800 dark:text-red-200 mb-4">
                                        This article has <?= $updateCount ?> update<?= $updateCount > 1 ? 's' : '' ?>. You can add more updates or view the timeline.
                                    </p>
                                    <div class="flex gap-3 flex-wrap">
                                        <a href="view_article_updates.php?id=<?= $newsId ?>" 
                                           class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border border-red-300 dark:border-red-700 rounded-lg text-red-700 dark:text-red-300 font-medium text-sm transition-colors shadow-sm">
                                            <span class="material-symbols-outlined text-lg">history</span>
                                            View <?= $updateCount ?> Update<?= $updateCount > 1 ? 's' : '' ?>
                                        </a>
                                        <a href="add_update.php?parent_id=<?= $newsId ?>" 
                                           class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium text-sm transition-colors shadow-md">
                                            <span class="material-symbols-outlined text-lg">add_circle</span>
                                            Add Another Update
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Title Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <input 
                            type="text" 
                            id="articleTitle" 
                            name="title" 
                            value="<?= htmlspecialchars($news['title']) ?>" 
                            placeholder="Enter article title..." 
                            maxlength="200"
                            required
                            class="w-full text-3xl font-extrabold border-none bg-transparent focus:outline-none focus:ring-0 p-0"
                        />
                        <p id="titleCounter" class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            <?= mb_strlen($news['title']) ?>/200 characters
                        </p>
                    </div>

                    <!-- Thumbnail Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <label class="flex items-center gap-2 text-sm font-semibold mb-3">
                            <span class="material-symbols-outlined text-lg">add_photo_alternate</span>
                            Article Thumbnail
                        </label>
                        
                        <input type="file" id="thumbnailInput" name="thumbnail" accept="image/*" class="hidden"/>
                        
                        <?php if (!empty($news['thumbnail']) && file_exists(dirname(__DIR__) . '/' . $news['thumbnail'])): ?>
                            <!-- Existing Thumbnail -->
                            <div id="existingThumbnailContainer" class="mb-4">
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">Current Thumbnail</p>
                                <div class="relative inline-block">
                                    <img src="../<?= htmlspecialchars($news['thumbnail']) ?>" 
                                         alt="Current thumbnail" 
                                         class="w-40 h-40 object-cover rounded-lg border-2 border-gray-200 dark:border-gray-600 shadow-md"/>
                                    <button type="button" id="removeExistingThumbnail" class="absolute -top-2 -right-2 flex items-center justify-center size-7 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg transition-colors">
                                        <span class="material-symbols-outlined text-sm">close</span>
                                    </button>
                                </div>
                                <input type="hidden" name="remove_thumbnail" id="removeThumbnailFlag" value="0"/>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Upload New Thumbnail -->
                        <button type="button" id="thumbnailBtn" class="flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 border border-blue-200 dark:border-blue-700 rounded-lg text-blue-700 dark:text-blue-300 transition-colors">
                            <span class="material-symbols-outlined text-lg">cloud_upload</span>
                            <span class="text-sm font-medium"><?= !empty($news['thumbnail']) ? 'Change Thumbnail' : 'Add Thumbnail' ?></span>
                        </button>
                        
                        <!-- New Thumbnail Preview -->
                        <div id="thumbnailPreview" class="mt-4 hidden">
                            <p class="text-xs text-gray-600 dark:text-gray-400 mb-2">New Thumbnail (will replace current)</p>
                            <div class="relative inline-block">
                                <img class="w-40 h-40 object-cover rounded-lg border-2 border-blue-500 shadow-md" alt="New thumbnail preview"/>
                                <button type="button" id="removeThumbnail" class="absolute -top-2 -right-2 flex items-center justify-center size-7 bg-red-600 hover:bg-red-700 text-white rounded-full shadow-lg transition-colors">
                                    <span class="material-symbols-outlined text-sm">close</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Category Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <label class="flex items-center gap-2 text-sm font-semibold mb-3">
                            <span class="material-symbols-outlined text-lg">category</span>
                            Category
                        </label>
                        
                        <?php if (empty($categories)): ?>
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-3">
                                <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                    No categories available. Please contact an administrator to create categories.
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="relative">
                                <select 
                                    id="categorySelect" 
                                    name="category" 
                                    required
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 p-3 pr-10 focus:ring-2 focus:ring-primary focus:border-primary appearance-none cursor-pointer">
                                    <option value="">Choose a category...</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option 
                                            value="<?= htmlspecialchars($category['id']) ?>" 
                                            <?= $category['id'] == $news['category_id'] ? 'selected' : '' ?>
                                            data-icon="<?= getCategoryIcon($category['name']) ?>">
                                            <?= htmlspecialchars($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <!-- Custom Dropdown Icon -->
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                                    <span class="material-symbols-outlined text-gray-500">expand_more</span>
                                </div>
                            </div>
                            
                            <div id="selectedCategoryDisplay" class="<?= $news['category_id'] ? '' : 'hidden' ?> mt-3 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary/10">
                                        <span id="selectedCategoryIcon" class="material-symbols-outlined text-lg text-primary">
                                            <?= $news['category_id'] ? getCategoryIcon($news['category_name']) : 'folder' ?>
                                        </span>
                                    </div>
                                    <div class="flex-1">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Selected Category</p>
                                        <p id="selectedCategoryName" class="text-sm font-semibold">
                                            <?= htmlspecialchars($news['category_name'] ?? '') ?>
                                        </p>
                                    </div>
                                    <button type="button" id="clearCategoryBtn" class="text-xs text-blue-600 hover:underline font-medium">
                                        Clear
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Update Type Display (only for update articles) -->
                    <?php if ($news['is_update']): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <label class="flex items-center gap-2 text-sm font-semibold mb-3">
                            <span class="material-symbols-outlined text-lg">label</span>
                            Update Type (Read-only)
                        </label>
                        
                        <div class="relative">
                            <div class="px-4 py-3 bg-gray-100 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600">
                                <div class="flex items-center gap-3">
                                    <?php
                                    $updateTypes = [
                                        'developing' => ['icon' => 'sync', 'color' => 'blue', 'label' => 'Developing Story'],
                                        'breaking' => ['icon' => 'notification_important', 'color' => 'red', 'label' => 'Breaking Update'],
                                        'update' => ['icon' => 'update', 'color' => 'green', 'label' => 'Update'],
                                        'correction' => ['icon' => 'edit', 'color' => 'yellow', 'label' => 'Correction']
                                    ];
                                    $currentType = $updateTypes[$news['update_type']] ?? $updateTypes['update'];
                                    ?>
                                    <span class="material-symbols-outlined text-2xl text-<?= $currentType['color'] ?>-600">
                                        <?= $currentType['icon'] ?>
                                    </span>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">
                                            <?= $currentType['label'] ?>
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Update #<?= $news['update_number'] ?> for parent article
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs">info</span>
                            Update type cannot be changed after creation
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Stats Bar -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-4 fade-in">
                        <div class="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-400 flex-wrap">
                            <span><strong>Words:</strong> <span id="statsWordCount">0</span></span>
                            <span class="text-gray-300">|</span>
                            <span><strong>Characters:</strong> <span id="charCount">0</span></span>
                            <span class="text-gray-300">|</span>
                            <span><strong>Sentences:</strong> <span id="sentenceCount">0</span></span>
                            <span class="text-gray-300">|</span>
                            <span><strong>Attachments:</strong> <span id="attachCount"><?= count($existingAttachments) ?></span></span>
                            
                            <?php if ($news['is_update']): ?>
                            <span class="text-gray-300">|</span>
                            <span><strong>Type:</strong> <span class="uppercase text-blue-600 dark:text-blue-400"><?= htmlspecialchars($news['update_type']) ?></span></span>
                            <span class="text-gray-300">|</span>
                            <span><strong>Update #:</strong> <span class="text-purple-600 dark:text-purple-400"><?= $news['update_number'] ?></span></span>
                            <?php elseif ($updateCount > 0): ?>
                            <span class="text-gray-300">|</span>
                            <span><strong>Updates:</strong> <span class="text-red-600 dark:text-red-400 font-bold pulse-animation"><?= $updateCount ?></span></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Content Editor -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <label class="flex items-center gap-2 text-sm font-semibold mb-3">
                            <span class="material-symbols-outlined text-lg">description</span>
                            Article Content
                        </label>
                        
                        <textarea 
                            id="articleContent" 
                            name="content" 
                            placeholder="Start writing your article..."
                            required
                            class="w-full min-h-[400px] text-base leading-relaxed border-gray-300 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-700 focus:ring-2 focus:ring-primary focus:border-primary resize-y"
                        ><?= htmlspecialchars($news['content']) ?></textarea>
                        
                        <div class="flex items-center justify-between mt-2">
                            <p id="contentCounter" class="text-xs text-gray-500 dark:text-gray-400">
                                <?= mb_strlen($news['content']) ?> characters
                            </p>
                        </div>
                    </div>

                    <!-- Existing Attachments -->
                    <?php if ($attachmentsTableExists && !empty($existingAttachments)): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <h3 class="flex items-center gap-2 text-sm font-semibold mb-4">
                            <span class="material-symbols-outlined text-lg">attach_file</span>
                            Existing Attachments (<?= count($existingAttachments) ?>)
                        </h3>
                        
                        <div class="grid gap-3">
                            <?php foreach ($existingAttachments as $attachment): 
                                $fileExt = pathinfo($attachment['file_name'], PATHINFO_EXTENSION);
                                $fileSize = formatFileSize($attachment['file_size']);
                            ?>
                                <div class="file-item flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg transition-all hover:shadow-md">
                                    <span class="material-symbols-outlined text-2xl text-primary">
                                        <?= getFileIconSymbol($fileExt) ?>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium truncate"><?= htmlspecialchars($attachment['file_name']) ?></p>
                                        <?php if ($fileSize): ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= $fileSize ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <label class="flex items-center gap-2 px-3 py-2 cursor-pointer hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors group">
                                        <input 
                                            type="checkbox" 
                                            name="delete_attachments[]" 
                                            value="<?= $attachment['id'] ?>"
                                            class="w-4 h-4 text-red-600 rounded focus:ring-red-500 cursor-pointer"
                                        />
                                        <span class="text-xs text-red-600 font-medium group-hover:underline">Delete</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add New Attachments -->
                    <?php if ($attachmentsTableExists): ?>
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-enhanced-light dark:shadow-enhanced-dark border border-gray-200 dark:border-gray-700 p-6 fade-in">
                        <label class="flex items-center gap-2 text-sm font-semibold mb-4">
                            <span class="material-symbols-outlined text-lg">add_circle</span>
                            Add New Attachments
                        </label>
                        
                        <div id="dropZone" class="relative border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-12 text-center hover:border-primary dark:hover:border-primary hover:bg-blue-50/50 dark:hover:bg-blue-900/10 transition-all cursor-pointer">
                            <input 
                                type="file" 
                                name="new_attachments[]" 
                                id="new_attachments" 
                                multiple
                                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.mp4,.avi,.mov,.mp3,.wav"
                            />
                            <span class="material-symbols-outlined text-6xl text-primary mb-3 inline-block">cloud_upload</span>
                            <p class="text-base font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Click to browse or drag and drop files here
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Supported: PDF, Office, Images, Videos, Audio, Archives (Max 50MB each)
                            </p>
                        </div>
                        
                        <div id="fileList" class="mt-4 grid gap-2"></div>
                    </div>
                    <?php endif; ?>

                </form>
            </div>
        </main>
    </div>

    <!-- Toast Notifications Container -->
    <div id="notificationsContainer" class="fixed top-4 right-4 z-50 flex flex-col gap-2 max-w-sm w-full pointer-events-none"></div>

    <!-- Auto-save Indicator -->
    <div id="autoSaveIndicator" class="hidden fixed bottom-4 left-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-40">
        <span class="material-symbols-outlined text-sm inline-block mr-2">check_circle</span>
        Draft saved
    </div>

    <script>
        // ==================== NOTIFICATION SYSTEM ====================
        class NotificationSystem {
            constructor() {
                this.container = document.getElementById('notificationsContainer');
                this.soundEnabled = true;
                this.maxNotifications = 5;
            }

            playSound(type) {
                if (!this.soundEnabled) return;
                
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    const frequencies = {
                        success: [523.25, 659.25, 783.99],
                        error: [392.00, 349.23],
                        warning: [440.00, 493.88],
                        info: [523.25, 587.33]
                    };
                    
                    const freq = frequencies[type] || frequencies.info;
                    let currentTime = audioContext.currentTime;
                    
                    freq.forEach((f) => {
                        const osc = audioContext.createOscillator();
                        const gain = audioContext.createGain();
                        osc.connect(gain);
                        gain.connect(audioContext.destination);
                        osc.frequency.value = f;
                        osc.type = 'sine';
                        gain.gain.setValueAtTime(0.1, currentTime);
                        gain.gain.exponentialRampToValueAtTime(0.01, currentTime + 0.1);
                        osc.start(currentTime);
                        osc.stop(currentTime + 0.1);
                        currentTime += 0.08;
                    });
                } catch (e) {
                    console.log('Audio not supported');
                }
            }

            createConfetti() {
                const colors = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8', '#F7DC6F', '#BB8FCE'];
                for (let i = 0; i < 50; i++) {
                    setTimeout(() => {
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti-piece';
                        confetti.style.left = Math.random() * window.innerWidth + 'px';
                        confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                        confetti.style.animationDuration = (Math.random() * 2 + 2) + 's';
                        document.body.appendChild(confetti);
                        setTimeout(() => confetti.remove(), 4000);
                    }, i * 30);
                }
            }

            show(message, type = 'info', duration = 4000, options = {}) {
                const existingToasts = this.container.querySelectorAll('.toast');
                if (existingToasts.length >= this.maxNotifications) {
                    existingToasts[0].remove();
                }
                
                this.playSound(type);
                if (type === 'success' && options.celebrate) {
                    this.createConfetti();
                }
                
                const toast = document.createElement('div');
                const toastId = 'toast-' + Date.now();
                toast.id = toastId;
                toast.className = `toast pointer-events-auto relative overflow-hidden rounded-xl shadow-2xl toast-enter ${this.getTypeClasses(type)}`;
                
                toast.innerHTML = `
                    <div class="flex items-start gap-3 p-4">
                        <div class="flex-shrink-0">${this.getIcon(type)}</div>
                        <div class="flex-1 min-w-0">
                            ${options.title ? `<h4 class="font-bold text-sm mb-1">${this.escapeHtml(options.title)}</h4>` : ''}
                            <p class="text-sm">${this.escapeHtml(message)}</p>
                        </div>
                        <button onclick="notifications.dismiss('${toastId}')" class="flex-shrink-0 hover:opacity-70 transition-opacity">
                            <span class="material-symbols-outlined text-lg">close</span>
                        </button>
                    </div>
                    ${duration ? `<div class="toast-progress opacity-30" style="animation-duration: ${duration}ms"></div>` : ''}
                `;
                
                this.container.appendChild(toast);
                if (duration) setTimeout(() => this.dismiss(toastId), duration);
                return toastId;
            }

            dismiss(toastId) {
                const toast = document.getElementById(toastId);
                if (!toast) return;
                toast.classList.remove('toast-enter');
                toast.classList.add('toast-exit');
                setTimeout(() => toast.remove(), 300);
            }

            success(message, duration = 4000, options = {}) {
                return this.show(message, 'success', duration, options);
            }

            error(message, duration = 4000, options = {}) {
                return this.show(message, 'error', duration, options);
            }

            warning(message, duration = 4000, options = {}) {
                return this.show(message, 'warning', duration, options);
            }

            info(message, duration = 4000, options = {}) {
                return this.show(message, 'info', duration, options);
            }

            loading(message) {
                return this.show(message, 'info', 0, { title: 'Processing...' });
            }

            getTypeClasses(type) {
                const classes = {
                    success: 'bg-gradient-to-r from-green-500 to-emerald-600 text-white',
                    error: 'bg-gradient-to-r from-red-500 to-rose-600 text-white',
                    warning: 'bg-gradient-to-r from-amber-500 to-orange-600 text-white',
                    info: 'bg-gradient-to-r from-blue-500 to-cyan-600 text-white'
                };
                return classes[type] || classes.info;
            }

            getIcon(type) {
                const icons = {
                    success: '<span class="material-symbols-outlined text-2xl">check_circle</span>',
                    error: '<span class="material-symbols-outlined text-2xl">error</span>',
                    warning: '<span class="material-symbols-outlined text-2xl">warning</span>',
                    info: '<span class="material-symbols-outlined text-2xl">info</span>'
                };
                return icons[type] || icons.info;
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            clearAll() {
                const toasts = this.container.querySelectorAll('.toast');
                toasts.forEach(toast => this.dismiss(toast.id));
            }
        }

        const notifications = new NotificationSystem();

        // ==================== STATE MANAGEMENT ====================
        class EditorState {
            constructor() {
                this.newsId = <?= $newsId ?>;
                this.formChanged = false;
                this.attachmentCount = <?= count($existingAttachments) ?>;
                this.autoSaveInterval = null;
                this.isUpdate = <?= $news['is_update'] ? 'true' : 'false' ?>;
                this.hasUpdates = <?= $updateCount ?>;
            }

            markChanged() {
                this.formChanged = true;
            }

            resetChanged() {
                this.formChanged = false;
            }

            hasChanges() {
                return this.formChanged;
            }
        }

        const editorState = new EditorState();

        // ==================== STATISTICS TRACKER ====================
        class StatsTracker {
            constructor() {
                this.contentElement = document.getElementById('articleContent');
                this.wordCountElement = document.getElementById('wordCount');
                this.statsWordCountElement = document.getElementById('statsWordCount');
                this.charCountElement = document.getElementById('charCount');
                this.sentenceCountElement = document.getElementById('sentenceCount');
                this.readTimeElement = document.getElementById('readTime');
                this.titleCounterElement = document.getElementById('titleCounter');
                this.contentCounterElement = document.getElementById('contentCounter');
            }

            update() {
                const content = this.contentElement.value;
                const title = document.getElementById('articleTitle').value;
                
                // Calculate statistics
                const words = content.trim() ? content.trim().split(/\s+/).length : 0;
                const chars = content.length;
                const sentences = content.split(/[.!?]+/).filter(s => s.trim().length > 0).length;
                const readTime = Math.ceil(words / 200);
                
                // Update UI
                if (this.wordCountElement) this.wordCountElement.textContent = words;
                if (this.statsWordCountElement) this.statsWordCountElement.textContent = words;
                if (this.charCountElement) this.charCountElement.textContent = chars;
                if (this.sentenceCountElement) this.sentenceCountElement.textContent = sentences;
                if (this.readTimeElement) this.readTimeElement.textContent = readTime;
                if (this.contentCounterElement) this.contentCounterElement.textContent = `${chars} characters`;
                
                // Update title counter
                if (this.titleCounterElement) {
                    const titleLength = title.length;
                    this.titleCounterElement.textContent = `${titleLength}/200 characters`;
                    this.titleCounterElement.classList.toggle('text-red-500', titleLength > 180);
                    this.titleCounterElement.classList.toggle('text-orange-500', titleLength > 150 && titleLength <= 180);
                }
            }
        }

        const statsTracker = new StatsTracker();

        // ==================== FILE MANAGER ====================
        class FileManager {
            constructor() {
                this.fileInput = document.getElementById('new_attachments');
                this.dropZone = document.getElementById('dropZone');
                this.fileList = document.getElementById('fileList');
                this.allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
                                          'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'zip', 'rar', '7z',
                                          'txt', 'csv', 'mp4', 'avi', 'mov', 'mp3', 'wav'];
                this.maxFileSize = 50 * 1024 * 1024; // 50MB
                this.setupEventListeners();
            }

            setupEventListeners() {
                if (!this.fileInput || !this.dropZone) return;

                // Click to upload
                this.dropZone.addEventListener('click', (e) => {
                    if (e.target !== this.fileInput) {
                        this.fileInput.click();
                    }
                });

                // File selection
                this.fileInput.addEventListener('change', () => {
                    if (this.fileInput.files.length > 0) {
                        this.handleFiles(this.fileInput.files);
                    }
                });

                // Drag and drop
                this.dropZone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.dropZone.classList.add('border-primary', 'bg-blue-50', 'dark:bg-blue-900/10');
                });

                this.dropZone.addEventListener('dragleave', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.dropZone.classList.remove('border-primary', 'bg-blue-50', 'dark:bg-blue-900/10');
                });

                this.dropZone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.dropZone.classList.remove('border-primary', 'bg-blue-50', 'dark:bg-blue-900/10');
                    
                    if (e.dataTransfer.files.length > 0) {
                        const dt = new DataTransfer();
                        Array.from(e.dataTransfer.files).forEach(file => dt.items.add(file));
                        this.fileInput.files = dt.files;
                        this.handleFiles(e.dataTransfer.files);
                    }
                });
            }

            handleFiles(files) {
                if (!this.fileList) return;
                
                this.fileList.innerHTML = '';
                let validFilesCount = 0;

                Array.from(files).forEach((file, index) => {
                    if (!this.validateFile(file)) return;
                    
                    validFilesCount++;
                    const fileItem = this.createFileItem(file, index);
                    this.fileList.appendChild(fileItem);
                });

                if (validFilesCount > 0) {
                    editorState.attachmentCount = <?= count($existingAttachments) ?> + validFilesCount;
                    document.getElementById('attachCount').textContent = editorState.attachmentCount;
                    notifications.success(`${validFilesCount} file(s) ready to upload`, 2000);
                }
            }

            validateFile(file) {
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                if (!this.allowedExtensions.includes(fileExt)) {
                    notifications.error(`File type ".${fileExt}" is not allowed`, 4000);
                    return false;
                }
                
                if (file.size > this.maxFileSize) {
                    notifications.error(`File "${file.name}" exceeds 50MB limit`, 4000);
                    return false;
                }
                
                return true;
            }

            createFileItem(file, index) {
                const fileItem = document.createElement('div');
                fileItem.className = 'flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-lg fade-in';
                fileItem.setAttribute('data-file-index', index);
                
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                const fileExt = file.name.split('.').pop().toLowerCase();
                const fileIcon = this.getFileIcon(fileExt);
                
                fileItem.innerHTML = `
                    <span class="material-symbols-outlined text-2xl text-primary">${fileIcon}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate">${this.escapeHtml(file.name)}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${fileSize} MB</p>
                    </div>
                    <button type="button" class="remove-file-btn p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors" data-index="${index}">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                `;
                
                // Add remove functionality
                fileItem.querySelector('.remove-file-btn').addEventListener('click', () => {
                    this.removeFile(index);
                });
                
                return fileItem;
            }

            removeFile(index) {
                const dt = new DataTransfer();
                const files = Array.from(this.fileInput.files);
                
                files.forEach((file, i) => {
                    if (i !== index) dt.items.add(file);
                });
                
                this.fileInput.files = dt.files;
                this.handleFiles(this.fileInput.files);
                notifications.success('File removed', 2000);
            }

            getFileIcon(ext) {
                const iconMap = {
                    'pdf': 'picture_as_pdf',
                    'doc': 'description', 'docx': 'description', 'txt': 'description',
                    'xls': 'table_chart', 'xlsx': 'table_chart', 'csv': 'table_chart',
                    'ppt': 'slideshow', 'pptx': 'slideshow',
                    'jpg': 'image', 'jpeg': 'image', 'png': 'image', 'gif': 'image', 'webp': 'image',
                    'zip': 'folder_zip', 'rar': 'folder_zip', '7z': 'folder_zip',
                    'mp4': 'videocam', 'avi': 'videocam', 'mov': 'videocam',
                    'mp3': 'audio_file', 'wav': 'audio_file'
                };
                return iconMap[ext] || 'insert_drive_file';
            }

            escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
        }

        const fileManager = new FileManager();

        // ==================== CATEGORY MANAGER ====================
        class CategoryManager {
            constructor() {
                this.categorySelect = document.getElementById('categorySelect');
                this.selectedDisplay = document.getElementById('selectedCategoryDisplay');
                this.selectedNameSpan = document.getElementById('selectedCategoryName');
                this.selectedIconSpan = document.getElementById('selectedCategoryIcon');
                this.clearBtn = document.getElementById('clearCategoryBtn');
                this.setupEventListeners();
            }

            setupEventListeners() {
                if (!this.categorySelect) return;

                this.categorySelect.addEventListener('change', () => {
                    const selectedOption = this.categorySelect.options[this.categorySelect.selectedIndex];
                    const categoryId = this.categorySelect.value;
                    const categoryName = selectedOption.text;
                    const categoryIcon = selectedOption.getAttribute('data-icon') || 'folder';
                    
                    if (categoryId) {
                        this.showSelected(categoryName, categoryIcon);
                        notifications.success(`Category "${categoryName}" selected`, 2000);
                        editorState.markChanged();
                    } else {
                        this.hideSelected();
                    }
                });

                if (this.clearBtn) {
                    this.clearBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.categorySelect.selectedIndex = 0;
                        this.hideSelected();
                        notifications.info('Category selection cleared', 2000);
                    });
                }
            }

            showSelected(name, icon) {
                if (this.selectedDisplay && this.selectedNameSpan && this.selectedIconSpan) {
                    this.selectedNameSpan.textContent = name;
                    this.selectedIconSpan.textContent = icon;
                    this.selectedDisplay.classList.remove('hidden');
                    this.selectedDisplay.classList.add('bounce-animation');
                    setTimeout(() => {
                        this.selectedDisplay.classList.remove('bounce-animation');
                    }, 500);
                }
            }

            hideSelected() {
                if (this.selectedDisplay) {
                    this.selectedDisplay.classList.add('hidden');
                }
            }
        }

        const categoryManager = new CategoryManager();

        // ==================== AUTO-SAVE MANAGER ====================
        class AutoSaveManager {
            constructor() {
                this.saveInterval = 30000; // 30 seconds
                this.timeout = null;
                this.indicator = document.getElementById('autoSaveIndicator');
            }

            start() {
                const titleInput = document.getElementById('articleTitle');
                const contentInput = document.getElementById('articleContent');
                
                [titleInput, contentInput].forEach(input => {
                    input.addEventListener('input', () => {
                        editorState.markChanged();
                        clearTimeout(this.timeout);
                        this.timeout = setTimeout(() => {
                            this.save();
                        }, this.saveInterval);
                    });
                });
            }

            save() {
                const draft = {
                    id: editorState.newsId,
                    title: document.getElementById('articleTitle').value,
                    content: document.getElementById('articleContent').value,
                    category: document.getElementById('categorySelect').value,
                    timestamp: new Date().toISOString()
                };
                
                try {
                    localStorage.setItem(`article_draft_${editorState.newsId}`, JSON.stringify(draft));
                    this.showIndicator();
                } catch (e) {
                    console.error('Failed to save draft:', e);
                }
            }

            showIndicator() {
                if (!this.indicator) return;
                this.indicator.classList.remove('hidden');
                setTimeout(() => {
                    this.indicator.style.opacity = '0';
                    this.indicator.style.transition = 'opacity 0.3s';
                    setTimeout(() => {
                        this.indicator.classList.add('hidden');
                        this.indicator.style.opacity = '1';
                    }, 300);
                }, 2000);
            }

            loadDraft() {
                const saved = localStorage.getItem(`article_draft_${editorState.newsId}`);
                if (!saved) return;

                try {
                    const draft = JSON.parse(saved);
                    const savedTime = new Date(draft.timestamp);
                    const now = new Date();
                    const minutesAgo = Math.floor((now - savedTime) / 60000);
                    
                    if (minutesAgo < 30) {
                        if (confirm(`A draft was saved ${minutesAgo} minute(s) ago. Restore it?`)) {
                            if (draft.title) document.getElementById('articleTitle').value = draft.title;
                            if (draft.content) document.getElementById('articleContent').value = draft.content;
                            if (draft.category) document.getElementById('categorySelect').value = draft.category;
                            
                            statsTracker.update();
                            notifications.success('Draft restored successfully', 3000);
                        }
                    }
                } catch (e) {
                    console.error('Failed to load draft:', e);
                }
            }
        }

        const autoSaveManager = new AutoSaveManager();

        // ==================== FORM VALIDATOR ====================
        class FormValidator {
            validate() {
                const title = document.getElementById('articleTitle').value.trim();
                const content = document.getElementById('articleContent').value.trim();
                const category = document.getElementById('categorySelect').value;
                
                if (!title || !content) {
                    notifications.error('Please provide both title and content', 4000, {
                        title: 'Missing Information'
                    });
                    
                    if (!title) this.shakeElement(document.getElementById('articleTitle'));
                    if (!content) this.shakeElement(document.getElementById('articleContent'));
                    
                    return false;
                }

                if (!category) {
                    notifications.warning('Please select a category', 3000);
                    this.shakeElement(document.getElementById('categorySelect'));
                    return false;
                }

                if (title.length > 200) {
                    notifications.error('Title cannot exceed 200 characters', 4000);
                    this.shakeElement(document.getElementById('articleTitle'));
                    return false;
                }
                
                return true;
            }

            shakeElement(element) {
                if (!element) return;
                element.classList.add('shake-animation');
                setTimeout(() => {
                    element.classList.remove('shake-animation');
                }, 500);
            }
        }

        const formValidator = new FormValidator();

        // ==================== FORM SUBMISSION ====================
        function handleUpdate() {
            if (!formValidator.validate()) return;
            
            const loadingId = notifications.loading('Updating your article...');
            
            setTimeout(() => {
                notifications.dismiss(loadingId);
                notifications.success('Article updated successfully!', 5000, {
                    title: '🎉 Success!',
                    celebrate: true
                });
                
                editorState.resetChanged();
                
                setTimeout(() => {
                    document.getElementById('articleForm').submit();
                }, 1000);
            }, 800);
        }

        function handleSaveDraft() {
            autoSaveManager.save();
            notifications.success('Draft saved locally', 2000, {
                title: '💾 Saved'
            });
        }

        // ==================== KEYBOARD SHORTCUTS ====================
        function setupKeyboardShortcuts() {
            document.addEventListener('keydown', (e) => {
                // Ctrl/Cmd + S to save
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    handleUpdate();
                }
                
                // Ctrl/Cmd + D to save draft
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    handleSaveDraft();
                }
                
                // Escape to clear notifications
                if (e.key === 'Escape') {
                    notifications.clearAll();
                }
            });
        }

        // ==================== ATTACHMENT DELETE HANDLERS ====================
        function setupAttachmentDeletion() {
            const deleteCheckboxes = document.querySelectorAll('input[name="delete_attachments[]"]');
            deleteCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const fileItem = this.closest('.file-item');
                    if (!fileItem) return;
                    
                    if (this.checked) {
                        fileItem.style.opacity = '0.5';
                        fileItem.style.filter = 'grayscale(100%)';
                        notifications.info('File marked for deletion', 2000);
                        editorState.markChanged();
                    } else {
                        fileItem.style.opacity = '1';
                        fileItem.style.filter = 'none';
                        notifications.info('Deletion cancelled', 2000);
                    }
                });
            });
        }

        // ==================== UNSAVED CHANGES WARNING ====================
        function setupUnsavedWarning() {
            window.addEventListener('beforeunload', (e) => {
                if (editorState.hasChanges()) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });

            document.getElementById('articleForm').addEventListener('submit', () => {
                editorState.resetChanged();
            });
        }

        // ==================== INITIALIZATION ====================
        document.addEventListener('DOMContentLoaded', function() {
            // Setup thumbnail upload
            const thumbnailBtn = document.getElementById('thumbnailBtn');
            const thumbnailInput = document.getElementById('thumbnailInput');
            const thumbnailPreview = document.getElementById('thumbnailPreview');
            const removeThumbnail = document.getElementById('removeThumbnail');
            const removeExistingThumbnail = document.getElementById('removeExistingThumbnail');
            
            if (thumbnailBtn && thumbnailInput) {
                thumbnailBtn.addEventListener('click', function() {
                    thumbnailInput.click();
                });

                thumbnailInput.addEventListener('change', function(e) {
                    if (e.target.files && e.target.files[0]) {
                        const file = e.target.files[0];
                        
                        // Validate file size (max 5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            notifications.error('Image file size must be less than 5MB', 4000);
                            e.target.value = '';
                            return;
                        }
                        
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            if (thumbnailPreview) {
                                thumbnailPreview.querySelector('img').src = event.target.result;
                                thumbnailPreview.classList.remove('hidden');
                                
                                // Hide existing thumbnail if present
                                const existingContainer = document.getElementById('existingThumbnailContainer');
                                if (existingContainer) {
                                    existingContainer.style.opacity = '0.5';
                                }
                                
                                notifications.success('New thumbnail selected', 2000);
                                editorState.markChanged();
                            }
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (removeThumbnail) {
                removeThumbnail.addEventListener('click', function() {
                    thumbnailInput.value = '';
                    thumbnailPreview.classList.add('hidden');
                    
                    // Show existing thumbnail again if present
                    const existingContainer = document.getElementById('existingThumbnailContainer');
                    if (existingContainer) {
                        existingContainer.style.opacity = '1';
                    }
                    
                    notifications.info('New thumbnail removed', 2000);
                });
            }

            if (removeExistingThumbnail) {
                removeExistingThumbnail.addEventListener('click', function() {
                    if (confirm('Are you sure you want to remove the current thumbnail?')) {
                        document.getElementById('removeThumbnailFlag').value = '1';
                        document.getElementById('existingThumbnailContainer').style.display = 'none';
                        notifications.success('Thumbnail will be removed on save', 3000);
                        editorState.markChanged();
                    }
                });
            }

            // Setup statistics tracking
            const titleInput = document.getElementById('articleTitle');
            const contentInput = document.getElementById('articleContent');
            
            [titleInput, contentInput].forEach(input => {
                input.addEventListener('input', () => statsTracker.update());
            });

            // Initial stats update
            statsTracker.update();

            // Setup buttons
            document.getElementById('updateBtn').addEventListener('click', (e) => {
                e.preventDefault();
                handleUpdate();
            });

            document.getElementById('saveDraftBtn').addEventListener('click', (e) => {
                e.preventDefault();
                handleSaveDraft();
            });

            // Setup features
            setupAttachmentDeletion();
            setupKeyboardShortcuts();
            setupUnsavedWarning();
            autoSaveManager.start();
            autoSaveManager.loadDraft();

            // Show context-aware notification
            if (editorState.isUpdate) {
                notifications.info('You are editing an update article', 3000);
                console.log('📝 Editing update article');
            } else if (editorState.hasUpdates > 0) {
                notifications.info(`This article has ${editorState.hasUpdates} update(s)`, 3000);
                console.log(`📰 This article has ${editorState.hasUpdates} update(s)`);
            } else {
                setTimeout(() => {
                    notifications.info('💡 Tip: Press Ctrl+S to update, Ctrl+D to save draft', 5000);
                }, 1000);
            }

            console.log('✅ WriterAI Pro Editor initialized successfully');
        });
    </script>
</body>
</html>