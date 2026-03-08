<?php
/**
 * AJAX Category Filter Endpoint
 * Add this code at the top of your index.php file, right after the database connection
 */

// Handle AJAX request for category filtering
if (isset($_GET['ajax']) && $_GET['ajax'] === 'filter') {
    header('Content-Type: application/json');
    
    $category = $_GET['category'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 12; // Articles per page
    $offset = ($page - 1) * $limit;
    
    try {
        if ($category === 'all') {
            // Fetch all articles
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name
                FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                ORDER BY p.published_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            // Get total count
            $countStmt = $pdo->query("SELECT COUNT(*) FROM published_news");
            $totalArticles = $countStmt->fetchColumn();
        } else {
            // Fetch articles by category
            $stmt = $pdo->prepare("
                SELECT p.*, c.name AS category_name
                FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE LOWER(c.name) = LOWER(:category)
                ORDER BY p.published_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':category', $category, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            // Get total count for category
            $countStmt = $pdo->prepare("
                SELECT COUNT(*) FROM published_news p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE LOWER(c.name) = LOWER(:category)
            ");
            $countStmt->execute([':category' => $category]);
            $totalArticles = $countStmt->fetchColumn();
        }
        
        $stmt->execute();
        $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Return JSON response
        echo json_encode([
            'success' => true,
            'articles' => $articles,
            'total' => (int)$totalArticles,
            'page' => $page,
            'totalPages' => ceil($totalArticles / $limit),
            'category' => $category,
            'limit' => $limit
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred',
            'message' => $e->getMessage() // Remove in production
        ]);
    }
    exit;
}

// Continue with normal page rendering below...
?>