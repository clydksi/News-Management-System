<?php
session_start();
require '../../db.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only superadmin can create backups.']);
    exit;
}

try {
    // Database credentials from db.php
    $host = 'localhost'; // Adjust as needed
    $dbname = 'your_database_name'; // Adjust as needed
    $username = 'your_username'; // Adjust as needed
    $password = 'your_password'; // Adjust as needed

    // Create backups directory if it doesn't exist
    $backupDir = '../../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    // Generate backup filename
    $backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

    // Execute mysqldump command
    $command = "mysqldump --host=$host --user=$username --password=$password $dbname > $backupFile 2>&1";
    exec($command, $output, $returnVar);

    if ($returnVar === 0 && file_exists($backupFile)) {
        // Log activity
        $logStmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
            VALUES (?, 'backup', ?, ?, NOW())
        ");
        $logStmt->execute([
            $_SESSION['user_id'],
            "Created database backup: " . basename($backupFile),
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
        ]);

        echo json_encode([
            'success' => true, 
            'message' => 'Database backup created successfully',
            'download_url' => 'actions/download_backup.php?file=' . urlencode(basename($backupFile))
        ]);
    } else {
        throw new Exception('Backup failed');
    }

} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to create backup. Please check server configuration.'
    ]);
}
?>