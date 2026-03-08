<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'superadmin') {
    echo json_encode(['success' => false, 'message' => 'Only superadmin can create backups.']); exit;
}

csrf_verify();

$_env   = parse_ini_file(dirname(__DIR__, 2) . '/.env') ?: [];
$host   = $_env['DB_HOST'] ?? 'localhost';
$dbname = $_env['DB_NAME'] ?? '';
$dbuser = $_env['DB_USER'] ?? 'root';
$dbpass = $_env['DB_PASS'] ?? '';

if (empty($dbname)) {
    echo json_encode(['success' => false, 'message' => 'Database name not configured in .env']); exit;
}

$backupDir = dirname(__DIR__, 2) . '/backups';
if (!is_dir($backupDir) && !mkdir($backupDir, 0750, true)) {
    echo json_encode(['success' => false, 'message' => 'Could not create backups directory']); exit;
}

$backupFile = $backupDir . '/backup_' . date('Y-m-d_H-i-s') . '.sql';

$cmd = sprintf(
    'mysqldump --host=%s --user=%s %s %s > %s 2>&1',
    escapeshellarg($host),
    escapeshellarg($dbuser),
    empty($dbpass) ? '' : '--password=' . escapeshellarg($dbpass),
    escapeshellarg($dbname),
    escapeshellarg($backupFile)
);

exec($cmd, $output, $returnVar);

if ($returnVar === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
    try {
        $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (?, 'backup', ?, ?, NOW())")
            ->execute([$_SESSION['user_id'], 'Created database backup: ' . basename($backupFile), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0']);
    } catch (PDOException $e) {}
    echo json_encode([
        'success'      => true,
        'message'      => 'Backup created: ' . basename($backupFile),
        'download_url' => 'actions/download_backup.php?file=' . urlencode(basename($backupFile)),
    ]);
} else {
    @unlink($backupFile);
    echo json_encode(['success' => false, 'message' => 'Backup failed. Ensure mysqldump is available on this server.']);
}
