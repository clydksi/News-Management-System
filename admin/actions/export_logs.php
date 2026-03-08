<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/csrf.php';

// Check authentication and permissions
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    http_response_code(403);
    exit('Unauthorized');
}

csrf_verify();

try {
    // Fetch all logs
    $stmt = $pdo->query("
        SELECT l.*, u.username
        FROM activity_logs l
        LEFT JOIN users u ON l.user_id = u.id
        ORDER BY l.created_at DESC
    ");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add CSV headers
    fputcsv($output, ['ID', 'User', 'Action', 'Description', 'IP Address', 'Date & Time']);

    // Add data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['username'] ?? 'System',
            $log['action'],
            $log['description'],
            $log['ip_address'] ?? '-',
            date('Y-m-d H:i:s', strtotime($log['created_at']))
        ]);
    }

    fclose($output);

} catch (PDOException $e) {
    error_log("Export logs error: " . $e->getMessage());
    http_response_code(500);
    exit('Export failed');
}
?>
