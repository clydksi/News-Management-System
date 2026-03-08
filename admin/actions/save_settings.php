<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

csrf_verify();

try {
    // Get all POST data
    $settings = [
        'site_name' => $_POST['site_name'] ?? '',
        'site_email' => $_POST['site_email'] ?? '',
        'site_description' => $_POST['site_description'] ?? '',
        'articles_per_page' => intval($_POST['articles_per_page'] ?? 10),
        'users_per_page' => intval($_POST['users_per_page'] ?? 10),
        'require_approval' => isset($_POST['require_approval']) ? 1 : 0,
        'enable_comments' => isset($_POST['enable_comments']) ? 1 : 0,
        'max_file_size' => intval($_POST['max_file_size'] ?? 10),
        'allowed_file_types' => $_POST['allowed_file_types'] ?? '',
        'email_new_user' => isset($_POST['email_new_user']) ? 1 : 0,
        'email_new_article' => isset($_POST['email_new_article']) ? 1 : 0,
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0
    ];

    // Check if system_settings table exists, if not create it
    $createTableQuery = "
        CREATE TABLE IF NOT EXISTS system_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ";
    $pdo->exec($createTableQuery);

    // Update or insert each setting
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at) 
        VALUES (?, ?, NOW()) 
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
    ");

    foreach ($settings as $key => $value) {
        $stmt->execute([$key, $value]);
    }

    // Log activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) 
        VALUES (?, 'update', ?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'],
        "Updated system settings",
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
    ]);

    // Maintenance mode: write / remove a flag file so auth.php can check it without a DB query
    $flagFile = dirname(__DIR__, 2) . '/maintenance.flag';
    if ($settings['maintenance_mode']) {
        file_put_contents($flagFile, date('Y-m-d H:i:s'));
    } else {
        if (file_exists($flagFile)) @unlink($flagFile);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully'
    ]);

} catch (PDOException $e) {
    error_log("Save settings error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
