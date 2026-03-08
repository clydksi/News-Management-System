<?php
require '../auth.php';
require '../db.php';

// Check if news_id is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die('News ID is required');
}

$newsId = intval($_GET['id']);

// Verify the news article exists and user has permission
$newsStmt = $pdo->prepare("SELECT n.*, d.name as dept_name FROM news n 
                           JOIN departments d ON n.department_id = d.id 
                           WHERE n.id = ?");
$newsStmt->execute([$newsId]);
$news = $newsStmt->fetch();

if (!$news) {
    die('News article not found');
}

// Check permissions (non-admin can only access their department's news)
if ($_SESSION['role'] !== 'admin' && $news['department_id'] != $_SESSION['department_id']) {
    die('Permission denied');
}

// Check if attachments table exists
try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'attachments'");
    if ($checkTable->rowCount() === 0) {
        die('Attachments feature is not available');
    }
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// Get all attachments for this news article
try {
    $attachStmt = $pdo->prepare("SELECT * FROM attachments WHERE news_id = ? ORDER BY id ASC");
    $attachStmt->execute([$newsId]);
    $attachments = $attachStmt->fetchAll();
} catch (PDOException $e) {
    die('Error fetching attachments: ' . $e->getMessage());
}

if (empty($attachments)) {
    die('No attachments found for this article');
}

// If only one attachment, download it directly
if (count($attachments) === 1) {
    $attachment = $attachments[0];
    $filePath = $attachment['file_path'];
    
    if (!file_exists($filePath)) {
        die('File not found: ' . htmlspecialchars($attachment['file_name']));
    }
    
    // Set headers for file download
    header('Content-Type: ' . ($attachment['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($attachment['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Output file
    readfile($filePath);
    exit;
}

// Multiple attachments - create ZIP file
// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    die('ZIP functionality is not available on this server');
}

// Create temporary ZIP file
$zipFileName = 'attachments_' . $newsId . '_' . time() . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    die('Cannot create ZIP file');
}

// Add each attachment to the ZIP
$filesAdded = 0;
foreach ($attachments as $attachment) {
    $filePath = $attachment['file_path'];
    
    if (file_exists($filePath)) {
        $fileName = basename($attachment['file_name']);
        
        // Handle duplicate filenames by adding a counter
        $counter = 1;
        $originalFileName = $fileName;
        while ($zip->locateName($fileName) !== false) {
            $fileInfo = pathinfo($originalFileName);
            $fileName = $fileInfo['filename'] . '_' . $counter . '.' . $fileInfo['extension'];
            $counter++;
        }
        
        $zip->addFile($filePath, $fileName);
        $filesAdded++;
    }
}

$zip->close();

if ($filesAdded === 0) {
    unlink($zipFilePath);
    die('No valid files found to download');
}

// Send ZIP file to browser
if (!file_exists($zipFilePath)) {
    die('Failed to create ZIP file');
}

// Clean filename for download
$downloadFileName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $news['title']);
$downloadFileName = substr($downloadFileName, 0, 50) . '_attachments.zip';

// Set headers for ZIP download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
header('Content-Length: ' . filesize($zipFilePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// Output ZIP file
readfile($zipFilePath);

// Clean up temporary ZIP file
unlink($zipFilePath);

exit;
?>