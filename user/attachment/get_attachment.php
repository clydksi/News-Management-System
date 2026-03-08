<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

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
    showErrorPage('No Attachments Found', 'This article doesn\'t have any attachments.');
}

/**
 * Try to find file in multiple possible locations
 */
function findFile($storedPath) {
    // If file exists at stored path, return it
    if (file_exists($storedPath)) {
        return $storedPath;
    }
    
    $basename = basename($storedPath);
    
    // Try multiple possible locations
    $possiblePaths = [
        // Relative to current script directory
        __DIR__ . '/../uploads/' . $basename,
        __DIR__ . '/../uploads/attachments/' . $basename,
        __DIR__ . '/../../uploads/' . $basename,
        __DIR__ . '/../../uploads/attachments/' . $basename,
        
        // Relative to document root
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/attachments/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/uploads/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/uploads/attachments/' . $basename,
        
        // Try the stored path variations
        dirname(__DIR__) . '/' . $storedPath,
        dirname(__DIR__, 2) . '/' . $storedPath,
        $_SERVER['DOCUMENT_ROOT'] . '/' . $storedPath,
        
        // Absolute path variations
        str_replace('//', '/', '/var/www/html/' . $storedPath),
        str_replace('//', '/', '/var/www/html/uploads/' . $basename),
        str_replace('//', '/', '/var/www/html/uploads/attachments/' . $basename),
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            return $path;
        }
    }
    
    return false;
}

/**
 * Show error page with proper HTML
 */
function showErrorPage($title, $message, $showDebug = false, $debugInfo = []) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($title) . '</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    </head>
    <body class="bg-gray-100 font-[Inter]">
        <div class="min-h-screen flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl p-8 max-w-2xl w-full">
                <div class="text-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 bg-red-100 rounded-full mb-4">
                        <span class="material-symbols-outlined text-4xl text-red-600">error</span>
                    </div>
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">' . htmlspecialchars($title) . '</h1>
                    <p class="text-gray-600 text-lg">' . htmlspecialchars($message) . '</p>
                </div>';
    
    if ($showDebug && !empty($debugInfo)) {
        echo '<div class="mt-6 bg-gray-50 rounded-lg p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm">bug_report</span>
                    Debug Information
                </h3>
                <div class="text-xs text-gray-600 space-y-1 font-mono">';
        foreach ($debugInfo as $key => $value) {
            echo '<div><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</div>';
        }
        echo '</div></div>';
    }
    
    echo '  <div class="mt-6 flex gap-3 justify-center">
                    <button onclick="window.close()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-3 rounded-lg font-semibold transition-colors">
                        Close Window
                    </button>
                    <button onclick="window.history.back()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition-colors">
                        Go Back
                    </button>
                </div>
            </div>
        </div>
    </body>
    </html>';
    exit;
}

// If only one attachment, download it directly
if (count($attachments) === 1) {
    $attachment = $attachments[0];
    $filePath = findFile($attachment['file_path']);
    
    if (!$filePath) {
        showErrorPage(
            'File Not Found',
            'The file "' . htmlspecialchars($attachment['file_name']) . '" could not be found on the server.',
            true,
            [
                'Stored Path' => $attachment['file_path'],
                'File Name' => $attachment['file_name'],
                'Attachment ID' => $attachment['id'],
                'Upload Date' => $attachment['uploaded_at']
            ]
        );
    }
    
    // Set headers for file download
    header('Content-Type: ' . ($attachment['file_type'] ?: 'application/octet-stream'));
    header('Content-Disposition: attachment; filename="' . basename($attachment['file_name']) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    header('Pragma: public');
    
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Output file
    readfile($filePath);
    exit;
}

// Multiple attachments - create ZIP file
// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    showErrorPage(
        'ZIP Not Available',
        'ZIP functionality is not available on this server. Please contact your administrator.'
    );
}

// Create temporary ZIP file
$zipFileName = 'attachments_' . $newsId . '_' . time() . '.zip';
$zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

$zip = new ZipArchive();
if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
    showErrorPage(
        'ZIP Creation Failed',
        'Cannot create ZIP file. Please try again or contact support.'
    );
}

// Add each attachment to the ZIP
$filesAdded = 0;
$missingFiles = [];
$addedFiles = [];

foreach ($attachments as $attachment) {
    $filePath = findFile($attachment['file_path']);
    
    if ($filePath && file_exists($filePath)) {
        $fileName = basename($attachment['file_name']);
        
        // Handle duplicate filenames by adding a counter
        $counter = 1;
        $originalFileName = $fileName;
        while ($zip->locateName($fileName) !== false) {
            $fileInfo = pathinfo($originalFileName);
            $extension = isset($fileInfo['extension']) ? '.' . $fileInfo['extension'] : '';
            $fileName = $fileInfo['filename'] . '_' . $counter . $extension;
            $counter++;
        }
        
        if ($zip->addFile($filePath, $fileName)) {
            $filesAdded++;
            $addedFiles[] = $fileName;
        }
    } else {
        $missingFiles[] = $attachment['file_name'];
    }
}

// Add a README if there are missing files
if (!empty($missingFiles)) {
    $readmeContent = "Attachment Download Report\n";
    $readmeContent .= "=========================\n\n";
    $readmeContent .= "Article: " . $news['title'] . "\n";
    $readmeContent .= "Download Date: " . date('Y-m-d H:i:s') . "\n\n";
    $readmeContent .= "Successfully Downloaded Files (" . count($addedFiles) . "):\n";
    $readmeContent .= "-----------------------------------\n";
    foreach ($addedFiles as $file) {
        $readmeContent .= "✓ " . $file . "\n";
    }
    $readmeContent .= "\nMissing Files (" . count($missingFiles) . "):\n";
    $readmeContent .= "-----------------------------------\n";
    foreach ($missingFiles as $file) {
        $readmeContent .= "✗ " . $file . " (File not found on server)\n";
    }
    $readmeContent .= "\nPlease contact your administrator if you need the missing files.\n";
    
    $zip->addFromString('README.txt', $readmeContent);
}

$zip->close();

if ($filesAdded === 0) {
    unlink($zipFilePath);
    showErrorPage(
        'No Files Available',
        'None of the attachment files could be found on the server.',
        true,
        [
            'Total Attachments' => count($attachments),
            'Files Found' => '0',
            'Missing Files' => implode(', ', array_map(function($f) { 
                return basename($f); 
            }, $missingFiles))
        ]
    );
}

// Send ZIP file to browser
if (!file_exists($zipFilePath)) {
    showErrorPage(
        'ZIP Creation Failed',
        'Failed to create ZIP file. Please try again.'
    );
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
header('Pragma: public');

// Clear output buffer
if (ob_get_level()) {
    ob_end_clean();
}

// Output ZIP file
readfile($zipFilePath);

// Clean up temporary ZIP file
@unlink($zipFilePath);

exit;
?>