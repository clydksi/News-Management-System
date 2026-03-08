<?php
/**
 * Article Import Handler
 * Saves news articles to the database
 * NO DUPLICATE CHECKING - Allows all imports including duplicates
 */

require '../auth.php';
require '../db.php';

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

    // Get user info from session - with proper session handling
    $departmentId = $_SESSION['department_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Log session data for debugging (remove in production)
    error_log("Import Article - Department ID: " . ($departmentId ?? 'NULL') . ", User ID: " . ($userId ?? 'NULL'));
    error_log("Import Article - Title: " . ($data['title'] ?? 'NULL'));

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

    // Determine source type
    $sourceType = 'external_import';
    if (!empty($data['url'])) {
        if (strpos($data['url'], 'mediastack') !== false) {
            $sourceType = 'api_mediastack';
        } elseif (strpos($data['url'], 'google') !== false || strpos($data['url'], 'news.google') !== false) {
            $sourceType = 'google_news_rss';
        }
    }

    // Insert article - Don't save URL to avoid constraint issues
    $stmt = $pdo->prepare("
        INSERT INTO news (
            title, 
            content, 
            category_id, 
            department_id, 
            created_by, 
            source_type,
            is_pushed,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");

    $result = $stmt->execute([
        $data['title'],
        $fullContent,
        $categoryId,
        $departmentId,
        $userId,
        $sourceType
    ]);

    if ($result) {
        $newId = $pdo->lastInsertId();
        
        sendResponse(true, 'Article saved successfully', [
            'article_id' => $newId,
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'department_id' => $departmentId,
            'user_id' => $userId,
            'source_type' => $sourceType
        ]);
    } else {
        sendResponse(false, 'Failed to save article');
    }

} catch (PDOException $e) {
    // Log error (in production, log to file instead of returning to client)
    error_log("Database error: " . $e->getMessage());
    sendResponse(false, 'Database error: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    sendResponse(false, 'An error occurred. Please try again.');
}
?>