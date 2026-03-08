<?php
include 'db.php'; // your DB connection

// Read category id from URL
$categoryId = $_GET['id'] ?? null;

if (!$categoryId) {
    die("No category selected.");
}

// 1. Fetch category name
$categoryStmt = $pdo->prepare("SELECT name FROM categories WHERE id = ?");
$categoryStmt->execute([$categoryId]);
$category = $categoryStmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    die("Category not found.");
}

$categoryName = $category['name'];

// 2. Fetch all articles under this category
$articlesStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ?
    ORDER BY p.published_at DESC
");
$articlesStmt->execute([$categoryId]);
$articles = $articlesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title><?= htmlspecialchars($categoryName); ?> - News</title>
</head>
<body>

<h1>All News in: <?= htmlspecialchars($categoryName); ?></h1>

<?php foreach ($articles as $row): ?>
    <div class="p-4 border rounded mb-3">
        <h2><?= htmlspecialchars($row['title']); ?></h2>

        <small>Category: <?= htmlspecialchars($row['category_name']); ?></small><br>
        <small>Published: <?= $row['published_at']; ?></small>

        <p><?= nl2br(htmlspecialchars($row['content'])); ?></p>
    </div>
<?php endforeach; ?>

</body>
</html>
