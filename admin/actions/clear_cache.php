<?php
session_start();
require '../../db.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Clear PHP session cache
    session_regenerate_id(true);

    // Clear any cache files if they exist
    $cacheDir = '../../cache';
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Clear thumbnail cache
    $thumbDir = '../../thumbnails/cache';
    if (is_dir($thumbDir)) {
        $files = glob($thumbDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
        VALUES (?, 'system', ?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'],
        "Cleared system cache",
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    echo json_encode([
        'success' => true, 
        'message' => 'Cache cleared successfully'
    ]);

} catch (Exception $e) {
    error_log("Clear cache error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to clear cache']);
}
?>