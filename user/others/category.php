<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $user = $_SESSION['user_id'];

    if (!empty($name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)");
            $stmt->execute([$name, $user]);
            
            // Return success response for AJAX
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Category created successfully']);
            exit;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Error creating category']);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit;
    }
}
?>