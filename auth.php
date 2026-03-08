<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // If no user session, redirect to login
    header("Location: /crud/login.php");
    exit;
}

// Maintenance mode: block regular users when flag file is present
$_maintenanceFlag = __DIR__ . '/maintenance.flag';
if (
    file_exists($_maintenanceFlag) &&
    !in_array($_SESSION['role'] ?? '', ['admin', 'superadmin'])
) {
    // Allow AJAX/API endpoints to return JSON
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
    if ($isAjax) {
        http_response_code(503);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'System is under maintenance. Please try again later.']);
        exit;
    }
    // Show maintenance page
    http_response_code(503);
    include __DIR__ . '/maintenance.php';
    exit;
}

?>
