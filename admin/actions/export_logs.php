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
    // Build filtered query from GET params (same filters as the logs tab)
    $logSearch   = trim($_GET['lq']      ?? '');
    $logAction   = in_array($_GET['laction'] ?? '', ['create','update','delete','login','logout']) ? $_GET['laction'] : '';
    $logDateFrom = $_GET['lfrom'] ?? '';
    $logDateTo   = $_GET['lto']   ?? '';
    $where  = ['1=1']; $params = [];
    if ($logSearch)   { $where[] = '(l.description LIKE ? OR u.username LIKE ?)'; $params[] = "%$logSearch%"; $params[] = "%$logSearch%"; }
    if ($logAction)   { $where[] = 'l.action = ?';              $params[] = $logAction; }
    if ($logDateFrom) { $where[] = 'DATE(l.created_at) >= ?';   $params[] = $logDateFrom; }
    if ($logDateTo)   { $where[] = 'DATE(l.created_at) <= ?';   $params[] = $logDateTo; }
    $whereSQL = implode(' AND ', $where);
    $stmt = $pdo->prepare("SELECT l.*, u.username FROM activity_logs l LEFT JOIN users u ON l.user_id = u.id WHERE $whereSQL ORDER BY l.created_at DESC");
    $stmt->execute($params);
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
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Export failed: ' . $e->getMessage()]);
}
?>
