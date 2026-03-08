<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

// Only superadmin can download backups
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    http_response_code(403);
    exit('Unauthorized');
}

$file      = basename($_GET['file'] ?? '');
$backupDir = dirname(__DIR__, 2) . '/backups';
$filePath  = $backupDir . '/' . $file;

// Validate: must be a .sql file inside the backups directory
if (
    empty($file) ||
    !str_ends_with($file, '.sql') ||
    !file_exists($filePath) ||
    strpos(realpath($filePath), realpath($backupDir)) !== 0
) {
    http_response_code(404);
    exit('Backup file not found.');
}

// Stream the file as a download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
readfile($filePath);
exit;
