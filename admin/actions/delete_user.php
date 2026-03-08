<?php
session_start();
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

// Security: Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'superadmin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user ID
$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent self-deletion
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
    exit;
}

try {
    // Check if user exists
    $checkStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = :id");
    $checkStmt->execute([':id' => $userId]);
    $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Check if user has articles
        // Try different possible column names for author
        $articleCount = 0;
        $authorColumnName = null;
        
        // Check which column name is used in your news table
        $possibleColumns = ['author_id', 'user_id', 'created_by'];
        
        foreach ($possibleColumns as $column) {
            try {
                $articleCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE $column = :user_id");
                $articleCheckStmt->execute([':user_id' => $userId]);
                $articleCount = $articleCheckStmt->fetchColumn();
                $authorColumnName = $column;
                break; // Found the correct column
            } catch (PDOException $e) {
                // Column doesn't exist, try next one
                continue;
            }
        }
        
        // If user has articles, handle based on preference
        if ($articleCount > 0) {
            // CHOOSE ONE OF THE OPTIONS BELOW:
            
            // Option 1: Prevent deletion (RECOMMENDED - Currently Active)
            $pdo->rollBack();
            echo json_encode([
                'success' => false, 
                'message' => "Cannot delete user '{$user['username']}'. They have {$articleCount} article(s). Please reassign or delete their articles first."
            ]);
            exit;
            
            /* 
            // Option 2: Reassign articles to current admin (Uncomment to use)
            if ($authorColumnName) {
                $reassignStmt = $pdo->prepare("UPDATE news SET $authorColumnName = :admin_id WHERE $authorColumnName = :user_id");
                $reassignStmt->execute([
                    ':admin_id' => $_SESSION['user_id'], 
                    ':user_id' => $userId
                ]);
            }
            */
            
            /* 
            // Option 3: Delete user's articles (Uncomment to use - DANGEROUS)
            if ($authorColumnName) {
                $deleteArticlesStmt = $pdo->prepare("DELETE FROM news WHERE $authorColumnName = :user_id");
                $deleteArticlesStmt->execute([':user_id' => $userId]);
            }
            */
        }
        
        // Check for other potential foreign key constraints
        // Add checks for other tables if needed
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $result = $stmt->execute([':id' => $userId]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => "User '{$user['username']}' deleted successfully"
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        // Check if it's a foreign key constraint error
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'foreign key constraint') !== false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete user. They are referenced in other tables (foreign key constraint).',
                'debug' => $e->getMessage() // Remove in production
            ]);
        } else {
            throw $e;
        }
    }
    
} catch (PDOException $e) {
    error_log("Database error in delete_user.php: " . $e->getMessage());
    error_log("Error code: " . $e->getCode());
    
    // More detailed error for development (remove 'debug' in production)
    echo json_encode([
        'success' => false, 
        'message' => 'Database error occurred',
        'debug' => $e->getMessage(), // REMOVE THIS IN PRODUCTION
        'code' => $e->getCode()
    ]);
}