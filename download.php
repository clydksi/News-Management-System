<?php
require 'auth.php'; // ✅ ensure user is logged in

if (!isset($_GET['file'])) {
    die("No file specified.");
}

$file = basename($_GET['file']); // ✅ prevent directory traversal
$path = __DIR__ . "/uploads/" . $file;

// ✅ Ensure file exists
if (!file_exists($path)) {
    die("File not found.");
}

// ✅ Force download headers
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($path));
flush();

// ✅ Output file content
readfile($path);
exit;
