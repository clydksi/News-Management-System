<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    // If no user session, redirect to login
    header("Location: dashboard.php");
    exit;
}

if ($_SESSION['role'] === 'superadmin') {
    // If admin, redirect to admin dashboard
    header("Location: admin_dashboard.php");
    exit;
}

?>
