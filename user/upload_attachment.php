<?php
require '../auth.php';
require '../db.php';

header('Content-Type: application/json');

// Check if attachments table exists
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'attachments'");
    if ($checkTable->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Attachments table does not exist. Please run the SQL setup first.']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate news_id
if (!isset($_POST['news_id']) || empty($_POST['news_id'])) {
    echo json_encode(['success' => false, 'message' => 'News ID is required']);
    exit;
}

$newsId = intval($_POST['news_id']);

// Verify news exists and user has permission
$newsStmt = $pdo->prepare("SELECT id, department_id FROM news WHERE id = ?");
$newsStmt->execute([$newsId]);
$news = $newsStmt->fetch();

if (!$news) {
    echo json_encode(['success' => false, 'message' => 'News article not found']);
    exit;
}

// Check permissions (non-admin can only upload to their department's news)
if ($_SESSION['role'] !== 'admin' && $news['department_id'] != $_SESSION['department_id']) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];
    $errorMsg = $errors[$file['error']] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'message' => $errorMsg]);
    exit;
}

// Validate file size (10MB max)
$maxFileSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 10MB limit']);
    exit;
}

// Validate file type
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'txt', 'csv'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions)]);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/attachments/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

// Generate unique filename
$originalName = pathinfo($file['name'], PATHINFO_FILENAME);
$sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalName);
$uniqueFilename = $sanitizedName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
$uploadPath = $uploadDir . $uniqueFilename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    exit;
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadPath);
finfo_close($finfo);

// Insert into database
try {
    $stmt = $pdo->prepare("INSERT INTO attachments (news_id, file_name, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $newsId,
        $file['name'],
        $uploadPath,
        $file['size'],
        $mimeType,
        $_SESSION['user_id']
    ]);
    
    $attachmentId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'attachment' => [
            'id' => $attachmentId,
            'filename' => $file['name'],
            'size' => $file['size'],
            'path' => $uploadPath
        ]
    ]);
} catch (PDOException $e) {
    // Remove uploaded file if database insert fails
    if (file_exists($uploadPath)) {
        unlink($uploadPath);
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>