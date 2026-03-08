<?php
require dirname(__DIR__, 2) . '/db.php';

// change_password.php
session_start();


// Set header for JSON response
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Please login first.'
    ]);
    exit;
}

// Get JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

// Debug: log what was received
error_log('Raw input: ' . $rawInput);
error_log('Decoded input: ' . print_r($input, true));

// Check if json_decode failed
if ($input === null && $rawInput !== 'null') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data',
        'debug' => 'Raw input: ' . $rawInput
    ]);
    exit;
}

// Validate input
if (empty($input['current_password']) || empty($input['new_password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required fields',
        'debug' => 'Received data: ' . json_encode($input),
        'raw' => $rawInput
    ]);
    exit;
}

$currentPassword = $input['current_password'];
$newPassword = $input['new_password'];
$userId = $_SESSION['user_id'];

// Validate new password strength
if (strlen($newPassword) < 8) {
    echo json_encode([
        'success' => false,
        'message' => 'New password must be at least 8 characters long'
    ]);
    exit;
}

try {
    // Use existing database connection from db.php ($pdo)
    
    // Get current password hash from database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Verify current password
    if (!password_verify($currentPassword, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Current password is incorrect'
        ]);
        exit;
    }
    
    // Check if new password is same as current password
    if (password_verify($newPassword, $user['password'])) {
        echo json_encode([
            'success' => false,
            'message' => 'New password must be different from current password'
        ]);
        exit;
    }
    
    // Hash new password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password in database
    $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->execute([$newPasswordHash, $userId]);
    
    // Optional: Log the password change (comment out if table doesn't exist)
    // $logStmt = $pdo->prepare("INSERT INTO password_change_log (user_id, changed_at, ip_address) VALUES (?, NOW(), ?)");
    // $logStmt->execute([$userId, $_SERVER['REMOTE_ADDR']]);
    
    // Optional: Send email notification
    // mail($user['email'], 'Password Changed', 'Your password has been changed successfully.');
    
    echo json_encode([
        'success' => true,
        'message' => 'Password changed successfully'
    ]);
    
} catch (PDOException $e) {
    // Log error for debugging (don't expose to user in production)
    error_log('Password change error: ' . $e->getMessage());
    
    // For debugging - show actual error (REMOVE THIS IN PRODUCTION!)
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.',
        'debug' => $e->getMessage(), // Remove this line in production
        'line' => $e->getLine() // Remove this line in production
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred.',
        'debug' => $e->getMessage() // Remove this line in production
    ]);
}
?>