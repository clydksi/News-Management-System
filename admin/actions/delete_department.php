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

// Get department ID
$deptId = filter_input(INPUT_POST, 'department_id', FILTER_VALIDATE_INT);

if (!$deptId) {
    echo json_encode(['success' => false, 'message' => 'Invalid department ID']);
    exit;
}

csrf_verify();

try {
    // Check if department exists
    $checkStmt = $pdo->prepare("SELECT id, name FROM departments WHERE id = ?");
    $checkStmt->execute([$deptId]);
    $department = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$department) {
        echo json_encode(['success' => false, 'message' => 'Department not found']);
        exit;
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Check if department has users
        $userCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE department_id = ?");
        $userCheckStmt->execute([$deptId]);
        $userCount = $userCheckStmt->fetchColumn();
        
        // Check if department has articles
        $articleCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM news WHERE department_id = ?");
        $articleCheckStmt->execute([$deptId]);
        $articleCount = $articleCheckStmt->fetchColumn();
        
        // Prevent deletion if department has users or articles
        if ($userCount > 0 || $articleCount > 0) {
            $pdo->rollBack();
            $message = "Cannot delete department '" . $department['name'] . "'.";
            
            if ($userCount > 0 && $articleCount > 0) {
                $message .= " It has {$userCount} user(s) and {$articleCount} article(s).";
            } elseif ($userCount > 0) {
                $message .= " It has {$userCount} user(s).";
            } else {
                $message .= " It has {$articleCount} article(s).";
            }
            
            $message .= " Please reassign or delete them first.";
            
            echo json_encode([
                'success' => false, 
                'message' => $message
            ]);
            exit;
        }
        
        // Delete the department
        $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
        $result = $stmt->execute([$deptId]);
        
        if ($result) {
            $pdo->commit();
            echo json_encode([
                'success' => true,
                'message' => "Department '" . $department['name'] . "' deleted successfully"
            ]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Failed to delete department']);
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        
        // Check if it's a foreign key constraint error
        if ($e->getCode() == '23000' || strpos($e->getMessage(), 'foreign key constraint') !== false) {
            echo json_encode([
                'success' => false, 
                'message' => 'Cannot delete department. It is referenced in other tables.'
            ]);
        } else {
            throw $e;
        }
    }
    
} catch (PDOException $e) {
    error_log("Delete department error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
