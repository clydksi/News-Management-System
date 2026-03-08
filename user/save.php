<?php
/**
 * Article Import Handler
 * Saves news articles to the database
 * Matches the exact structure of your working MediaStack code
 */

// Start session to access user data
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors in JSON response

// Set JSON response header
header('Content-Type: application/json');

// Database configuration
$db_host = 'localhost';
$db_name = 'crud_news';
$db_user = 'root';
$db_pass = '';

// Response function
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendResponse(false, 'Method not allowed');
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (json_last_error() !== JSON_ERROR_NONE) {
    sendResponse(false, 'Invalid JSON data provided.');
}

// Required field
if (empty($data['title'])) {
    sendResponse(false, 'Title is required');
}

// Set content with fallback to title
if (empty($data['content'])) {
    $data['content'] = $data['title'];
}

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Get category_id from category name
    $categoryId = null;
    if (!empty($data['category'])) {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$data['category']]);
        $category = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($category) {
            $categoryId = $category['id'];
        } else {
            // Create category if it doesn't exist
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([ucfirst(strtolower($data['category']))]);
                $categoryId = $pdo->lastInsertId();
            } catch (PDOException $e) {
                // Category might already exist due to race condition
                $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
                $stmt->execute([$data['category']]);
                $category = $stmt->fetch(PDO::FETCH_ASSOC);
                $categoryId = $category['id'] ?? null;
            }
        }
    }

    // Check if article already exists by external_url
    if (!empty($data['url'])) {
        $stmt = $pdo->prepare("SELECT id FROM news WHERE external_url = ? LIMIT 1");
        $stmt->execute([$data['url']]);
        if ($stmt->fetch()) {
            sendResponse(false, 'This article already exists in the database');
        }
    }

    // Get user info from session
    $departmentId = $_SESSION['department_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Build full content with metadata
    $fullContent = $data['content'];
    if (!empty($data['author']) || !empty($data['source']) || !empty($data['published_at'])) {
        $fullContent .= "\n\n---\n";
        if (!empty($data['author'])) {
            $fullContent .= "Author: " . $data['author'] . "\n";
        }
        if (!empty($data['source'])) {
            $fullContent .= "Source: " . $data['source'] . "\n";
        }
        if (!empty($data['published_at'])) {
            $fullContent .= "Published: " . date('F d, Y', strtotime($data['published_at'])) . "\n";
        }
    }

    // Insert article into news table
    $stmt = $pdo->prepare("
        INSERT INTO news (
            title, 
            content, 
            category_id, 
            department_id, 
            created_by, 
            external_url,
            source_type,
            is_pushed,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'api_mediastack', 0, NOW(), NOW())
    ");

    $result = $stmt->execute([
        $data['title'],
        $fullContent,
        $categoryId,
        $departmentId,
        $userId,
        $data['url'] ?? null
    ]);

    if ($result) {
        sendResponse(true, 'Article saved successfully', [
            'article_id' => $pdo->lastInsertId(),
            'title' => $data['title'],
            'category' => $data['category'] ?? null
        ]);
    } else {
        sendResponse(false, 'Failed to save article');
    }

} catch (PDOException $e) {
    // Log error (in production, log to file instead of returning to client)
    error_log("Database error: " . $e->getMessage());
    
    // Check if it's a duplicate entry error
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        sendResponse(false, 'This article already exists in the database');
    }
    
    sendResponse(false, 'Database error. Please try again.');
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse(false, 'An error occurred. Please try again.');
}
?>