<?php
/**
 * View Article API
 * Returns formatted HTML for viewing a single published article
 */

header('Content-Type: application/json');

require dirname(__DIR__, 2) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid article ID']);
    exit;
}

$articleId = (int) $_GET['id'];

try {
    // Get article - only published headlines
    $stmt = $pdo->prepare("
        SELECT n.*, 
               u.username as author_username,
               c.name as category_name
        FROM news n
        JOIN users u ON n.created_by = u.id
        LEFT JOIN categories c ON n.category_id = c.id
        WHERE n.id = ? 
        AND n.is_pushed = 2 
        AND n.published_at IS NOT NULL
    ");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch();
    
    if (!$article) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Article not found']);
        exit;
    }
    
    // Generate HTML
    $html = generateArticleHtml($article);
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (PDOException $e) {
    error_log("View article error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
}

function generateArticleHtml($article) {
    // Escape function
    $escapeHtml = function($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    };
    
    // Pre-escape all variables
    $categoryName = $escapeHtml($article['category_name'] ?: 'Uncategorized');
    $publishedDate = date('F d, Y', strtotime($article['published_at']));
    $title = $escapeHtml($article['title']);
    $authorUsername = $escapeHtml($article['author_username']);
    $publishedTime = date('g:i A', strtotime($article['published_at']));
    $content = $escapeHtml($article['content']);
    $publishedFull = date('F d, Y \a\t g:i A', strtotime($article['published_at']));
    $articleId = $article['id'];
    
    $currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    
    // Use double quotes to allow variable interpolation
    return "
    <div class=\"article-view\">
        <!-- Header -->
        <div class=\"relative\">
            <button onclick=\"closeArticle()\" 
                    class=\"absolute top-4 right-4 bg-white rounded-full p-2 shadow-lg hover:bg-gray-100 transition z-10\">
                <span class=\"material-icons text-gray-600\">close</span>
            </button>
            
            <div class=\"bg-gradient-to-r from-purple-600 to-purple-800 text-white p-6 md:p-8\">
                <div class=\"max-w-4xl mx-auto\">
                    <div class=\"flex items-center gap-3 mb-4\">
                        <span class=\"inline-block px-3 py-1 bg-white bg-opacity-20 rounded-full text-sm font-medium\">
                            $categoryName
                        </span>
                        <span class=\"text-purple-200 text-sm\">
                            $publishedDate
                        </span>
                    </div>
                    <h1 class=\"text-3xl md:text-4xl font-bold mb-4\">
                        $title
                    </h1>
                    <div class=\"flex items-center text-purple-200\">
                        <span class=\"material-icons text-sm mr-2\">person</span>
                        <span>By $authorUsername</span>
                        <span class=\"mx-3\">•</span>
                        <span class=\"material-icons text-sm mr-2\">schedule</span>
                        <span>$publishedTime</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content -->
        <div class=\"max-w-4xl mx-auto px-6 md:px-8 py-8\">
            <article class=\"prose prose-lg max-w-none\">
                <div class=\"text-gray-700 leading-relaxed whitespace-pre-wrap text-base md:text-lg\">
                    $content
                </div>
            </article>
            
            <!-- Article Footer -->
            <div class=\"mt-12 pt-6 border-t border-gray-200\">
                <div class=\"flex flex-wrap items-center justify-between gap-4\">
                    <div class=\"text-sm text-gray-500\">
                        <p>Published on $publishedFull</p>
                        <p>Article ID: #$articleId</p>
                    </div>
                    
                    <div class=\"flex gap-2\">
                        <button onclick=\"printArticle()\" 
                                class=\"px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg transition flex items-center gap-2\">
                            <span class=\"material-icons text-sm\">print</span>
                            Print
                        </button>
                        
                        <button onclick=\"shareArticle('$title', '$currentUrl')\" 
                                class=\"px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition flex items-center gap-2\">
                            <span class=\"material-icons text-sm\">share</span>
                            Share
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Related Articles Section (Optional) -->
            <div class=\"mt-12 pt-6 border-t border-gray-200\">
                <h3 class=\"text-xl font-bold text-gray-900 mb-4\">More Articles</h3>
                <a href=\"public_news.php\" class=\"inline-flex items-center text-purple-600 hover:text-purple-800 font-medium\">
                    <span class=\"material-icons text-sm mr-1\">arrow_back</span>
                    Back to All News
                </a>
            </div>
        </div>
    </div>
";
}