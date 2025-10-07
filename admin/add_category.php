<?php
session_start();
require '../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form input safely
    $category_name = isset($_POST['category_name']) ? trim($_POST['category_name']) : null;
    $created_by = $_SESSION['user_id'] ?? null;

    if ($category_name && $created_by) {
        $stmt = $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)");
        $stmt->execute([$category_name, $created_by]);

        // ✅ Redirect back to categories_admin.php
        header("Location: categories_admin.php?success=1");
        exit;
    } else {
        // If something went wrong (missing data or no session user)
        header("Location: categories_admin.php?error=1");
        exit;
    }
}
