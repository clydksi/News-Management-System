<?php
require dirname(__DIR__, 2) . '/db.php';

$article_id = $_GET['id'] ?? null;

if (!$article_id) {
    header("Location: dzrh.php");
    exit;
}

// Get main article
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.is_update = 0
");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    header("Location: dzrh.php");
    exit;
}

// Get all updates for this article
$updatesStmt = $pdo->prepare("
    SELECT * FROM published_news
    WHERE parent_article_id = ?
    ORDER BY published_at DESC
");
$updatesStmt->execute([$article_id]);
$updates = $updatesStmt->fetchAll();

$has_updates = count($updates) > 0;

function getUpdateBadgeClass($type) {
    $badges = [
        'breaking' => 'bg-red-100 text-red-800',
        'developing' => 'bg-blue-100 text-blue-800',
        'update' => 'bg-green-100 text-green-800',
        'correction' => 'bg-yellow-100 text-yellow-800'
    ];
    return $badges[$type] ?? $badges['update'];
}

function timeAgo($timestamp) {
    $time = strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    elseif ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    elseif ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    else return date('M d, Y g:i A', $time);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($article['title']) ?> - DZRH News</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        .update-timeline { border-left: 3px solid #9333EA; }
        .update-item::before {
            content: '';
            position: absolute;
            left: -26px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #9333EA;
            border: 3px solid white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <!-- Back Button -->
        <div class="mb-6">
            <a href="dzrh.php" class="inline-flex items-center text-purple-600 hover:text-purple-800 font-medium transition-colors">
                <span class="material-icons mr-2">arrow_back</span>
                Back to Home
            </a>
        </div>

        <!-- Main Article -->
        <article class="bg-white rounded-2xl shadow-lg overflow-hidden mb-8">
            <div class="p-8">
                <?php if ($has_updates): ?>
                <div class="mb-4">
                    <span class="bg-red-100 text-red-800 px-4 py-2 rounded-full text-sm font-bold flex items-center inline-flex animate-pulse">
                        <span class="material-icons text-sm mr-2">fiber_manual_record</span>
                        Developing Story • <?= count($updates) ?> Update<?= count($updates) > 1 ? 's' : '' ?>
                    </span>
                </div>
                <?php endif; ?>

                <h1 class="text-4xl font-bold text-gray-900 mb-4 leading-tight">
                    <?= htmlspecialchars($article['title']) ?>
                </h1>

                <div class="flex flex-wrap gap-6 text-sm text-gray-600 mb-6 pb-6 border-b">
                    <div class="flex items-center">
                        <span class="material-icons text-purple-600 mr-2">schedule</span>
                        <span class="font-medium"><?= timeAgo($article['published_at']) ?></span>
                    </div>
                    <?php if (!empty($article['category_name'])): ?>
                    <div class="flex items-center">
                        <span class="material-icons text-blue-600 mr-2">label</span>
                        <span class="font-medium"><?= htmlspecialchars($article['category_name']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($article['thumbnail'])): ?>
                <div class="mb-6 rounded-xl overflow-hidden">
                    <img src="../<?= htmlspecialchars($article['thumbnail']) ?>" 
                         alt="<?= htmlspecialchars($article['title']) ?>"
                         class="w-full h-auto">
                </div>
                <?php endif; ?>

                <div class="prose max-w-none text-gray-700 leading-relaxed text-lg">
                    <?= nl2br(htmlspecialchars($article['content'])) ?>
                </div>
            </div>
        </article>

        <!-- Updates Timeline -->
        <?php if ($has_updates): ?>
        <div class="bg-white rounded-2xl shadow-lg p-8">
            <h2 class="text-3xl font-bold text-gray-900 mb-8 flex items-center">
                <span class="material-icons text-purple-600 mr-3 text-4xl">history</span>
                Story Updates
                <span class="ml-3 bg-purple-100 text-purple-800 px-4 py-1 rounded-full text-lg">
                    <?= count($updates) ?>
                </span>
            </h2>

            <div class="update-timeline pl-8 space-y-8">
                <?php foreach ($updates as $update): 
                    $badgeClass = getUpdateBadgeClass($update['update_type']);
                ?>
                <div class="update-item relative">
                    <div class="bg-gray-50 rounded-xl p-6 hover:shadow-md transition-all">
                        <div class="flex items-center gap-3 mb-4 flex-wrap">
                            <span class="<?= $badgeClass ?> px-4 py-2 rounded-full text-sm font-bold">
                                <?= strtoupper($update['update_type']) ?>
                            </span>
                            <span class="text-sm text-gray-500 font-medium">
                                <?= timeAgo($update['published_at']) ?>
                            </span>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-3">
                            <?= htmlspecialchars($update['title']) ?>
                        </h3>

                        <?php if (!empty($update['thumbnail'])): ?>
                        <div class="mb-4 rounded-lg overflow-hidden">
                            <img src="../<?= htmlspecialchars($update['thumbnail']) ?>" 
                                 alt="<?= htmlspecialchars($update['title']) ?>"
                                 class="w-full h-auto">
                        </div>
                        <?php endif; ?>

                        <div class="text-gray-700 leading-relaxed">
                            <?= nl2br(htmlspecialchars($update['content'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
