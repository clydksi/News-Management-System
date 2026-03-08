<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($data['title'])) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

if (empty($data['content'])) {
    $data['content'] = $data['title'];
}

try {
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
            echo json_encode(['success' => false, 'message' => 'This article already exists in the database']);
            exit;
        }
    }

    // Get user info from session
    $departmentId = $_SESSION['department_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    // Build full content with metadata (NO IMAGE DATA)
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

    // Insert article - NO IMAGE/THUMBNAIL field
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
        echo json_encode([
            'success' => true, 
            'message' => 'Article saved successfully (text only, no images)',
            'article_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save article']);
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    
    if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
        echo json_encode(['success' => false, 'message' => 'This article already exists in the database']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}
?>