<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
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
    echo json_encode(['success' => false, 'error' => $errorMsg]);
    exit;
}

// Validate file size (10MB max)
$maxFileSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'File size exceeds 10MB limit']);
    exit;
}

// Validate file type
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar', 'txt', 'csv'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['success' => false, 'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedExtensions)]);
    exit;
}

// Create upload directory if it doesn't exist
$uploadDir = dirname(__DIR__) . '/uploads/attachments/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create upload directory']);
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
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    exit;
}

// Get mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $uploadPath);
finfo_close($finfo);

// Return relative path for database storage
$relativePath = 'uploads/attachments/' . $uniqueFilename;

echo json_encode([
    'success' => true,
    'fileName' => $file['name'],
    'filePath' => $relativePath,
    'fileSize' => $file['size'],
    'fileType' => $mimeType
]);
?>