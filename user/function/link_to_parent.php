<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

$article_id = $_GET['id'] ?? null;
if (!$article_id) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Get the article to be linked
$stmt = $pdo->prepare("
    SELECT n.*, 
           c.name as category_name,
           d.name as dept_name,
           u.username as author_name
    FROM news n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN departments d ON n.department_id = d.id
    LEFT JOIN users u ON n.created_by = u.id
    WHERE n.id = ?
");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    header("Location: ../user_dashboard.php");
    exit;
}

// Check if already linked
if ($article['is_update'] == 1) {
    header("Location: ../user_dashboard.php?status=error&message=" . urlencode("This article is already linked as an update"));
    exit;
}

// Check if user has permission
if ($_SESSION['role'] !== 'admin' && $_SESSION['department_id'] != $article['department_id']) {
    die("Unauthorized access");
}

// Get potential parent articles with enhanced info
$parentQuery = "
    SELECT n.id, 
           n.title, 
           n.created_at, 
           n.category_id,
           n.is_pushed,
           c.name as category_name,
           (SELECT COUNT(*) FROM news WHERE parent_article_id = n.id) as update_count,
           published.id as is_published,
           published.published_at
    FROM news n
    LEFT JOIN categories c ON n.category_id = c.id
    LEFT JOIN published_news published ON n.id = published.original_id
    WHERE n.is_update = 0 
    AND n.id != ?
";

$parentParams = [$article_id];

// Add department filter for non-admin users
if ($_SESSION['role'] !== 'admin') {
    $parentQuery .= " AND n.department_id = ?";
    $parentParams[] = $_SESSION['department_id'];
}

$parentQuery .= " ORDER BY n.created_at DESC LIMIT 100";

$stmt = $pdo->prepare($parentQuery);
$stmt->execute($parentParams);
$potentialParents = $stmt->fetchAll();

// Get categories for filtering
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Get status counts
$statusCounts = [
    'total' => count($potentialParents),
    'published' => 0,
    'headline' => 0,
    'with_updates' => 0
];

foreach ($potentialParents as $parent) {
    if ($parent['is_published']) $statusCounts['published']++;
    if ($parent['is_pushed'] == 2) $statusCounts['headline']++;
    if ($parent['update_count'] > 0) $statusCounts['with_updates']++;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $parent_id = $_POST['parent_id'];
        $update_type = $_POST['update_type'];
        
        // Verify parent article exists
        $stmt = $pdo->prepare("SELECT id, is_pushed, title FROM news WHERE id = ? AND is_update = 0");
        $stmt->execute([$parent_id]);
        $parent = $stmt->fetch();
        
        if (!$parent) {
            throw new Exception("Invalid parent article selected");
        }
        
        // Get next update number
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(update_number), 0) + 1 as next_number 
            FROM news 
            WHERE parent_article_id = ?
        ");
        $stmt->execute([$parent_id]);
        $next_number = $stmt->fetchColumn();
        
        // Update the article to become an update
        $stmt = $pdo->prepare("
            UPDATE news 
            SET parent_article_id = ?,
                is_update = 1,
                update_type = ?,
                update_number = ?
            WHERE id = ?
        ");
        
        $stmt->execute([
            $parent_id,
            $update_type,
            $next_number,
            $article_id
        ]);
        
        $success = "Article successfully linked as Update #{$next_number} to '{$parent['title']}'!";
        
        // Determine redirect based on parent article's status
        $section = '';
        switch($parent['is_pushed']) {
            case 0: $section = 'regular'; break;
            case 1: $section = 'edited'; break;
            case 2: $section = 'headline'; break;
            case 3: $section = 'archive'; break;
        }
        
        // Redirect to dashboard with success message
        header("refresh:3;url=../user_dashboard.php?section=" . $section . "&status=success&message=" . urlencode($success));
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

function getStatusBadge($isPushed, $isPublished) {
    if ($isPublished) {
        return [
            'label' => 'Published',
            'class' => 'bg-gradient-to-r from-green-500 to-emerald-500 text-white',
            'icon' => 'cloud_done'
        ];
    }
    
    $statusLabels = [
        0 => ['label' => 'Regular', 'class' => 'bg-yellow-100 text-yellow-800', 'icon' => 'article'],
        1 => ['label' => 'Edited', 'class' => 'bg-green-100 text-green-800', 'icon' => 'edit'],
        2 => ['label' => 'Headline', 'class' => 'bg-blue-100 text-blue-800', 'icon' => 'star'],
        3 => ['label' => 'Archive', 'class' => 'bg-red-100 text-red-800', 'icon' => 'archive']
    ];
    return $statusLabels[$isPushed] ?? $statusLabels[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Link to Parent Article - MBC News</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
    <style>
        body { font-family: 'Poppins', sans-serif; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s ease-out; }
        .parent-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .parent-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(109, 40, 217, 0.15);
        }
        .parent-card.selected {
            border-color: #7C3AED;
            background: linear-gradient(135deg, rgba(109, 40, 217, 0.05) 0%, rgba(147, 51, 234, 0.05) 100%);
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        .success-enter { animation: slideInRight 0.5s ease-out; }
        @keyframes pulse-warning {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
        .pulse-warning { animation: pulse-warning 2s ease-in-out infinite; }
        @keyframes shine {
            0% { background-position: -100% 0; }
            100% { background-position: 200% 0; }
        }
        .published-shine {
            background: linear-gradient(90deg, #10b981 0%, #34d399 50%, #10b981 100%);
            background-size: 200% auto;
            animation: shine 3s linear infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 to-blue-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Header -->
        <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 fade-in">
            <div class="flex items-center mb-4">
                <a href="../user_dashboard.php" class="mr-4 text-purple-600 hover:text-purple-800 transition-colors">
                    <span class="material-icons text-3xl">arrow_back</span>
                </a>
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons text-purple-600 mr-2 text-4xl">link</span>
                        Link Article to Parent
                    </h1>
                    <p class="text-gray-500 text-sm mt-1">Create a developing news relationship between articles</p>
                </div>
            </div>
            
            <!-- Current Article Info -->
            <div class="bg-gradient-to-r from-blue-50 to-purple-50 p-5 rounded-xl border-l-4 border-blue-600">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <p class="text-sm text-gray-600 font-medium mb-2 flex items-center">
                            <span class="material-icons text-sm mr-1">article</span>
                            Article to be linked as an update:
                        </p>
                        <h3 class="text-lg font-bold text-gray-800 mb-2"><?= htmlspecialchars($article['title']) ?></h3>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="bg-white px-3 py-1 rounded-full text-gray-600 font-medium">
                                <span class="material-icons text-xs mr-1">label</span>
                                <?= htmlspecialchars($article['category_name'] ?: 'Uncategorized') ?>
                            </span>
                            <span class="bg-white px-3 py-1 rounded-full text-gray-600 font-medium">
                                <span class="material-icons text-xs mr-1">person</span>
                                <?= htmlspecialchars($article['author_name']) ?>
                            </span>
                            <span class="bg-white px-3 py-1 rounded-full text-gray-600 font-medium">
                                <span class="material-icons text-xs mr-1">schedule</span>
                                <?= date('M d, Y g:i A', strtotime($article['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
        <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 p-6 mb-6 rounded-xl shadow-lg success-enter">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center">
                        <span class="material-icons text-white text-2xl">check_circle</span>
                    </div>
                </div>
                <div class="ml-4 flex-1">
                    <h3 class="text-lg font-bold text-green-800 mb-2">Successfully Linked!</h3>
                    <p class="text-green-700 font-medium mb-3"><?= htmlspecialchars($success) ?></p>
                    
                    <div class="bg-white bg-opacity-50 rounded-lg p-3 mb-3">
                        <p class="text-sm text-green-700 font-medium mb-2">
                            <span class="material-icons text-sm mr-1">info</span>
                            What happens next:
                        </p>
                        <ul class="text-sm text-green-600 space-y-1">
                            <li>✓ Article now appears with "Developing News" badge</li>
                            <li>✓ Visible in dashboard alongside parent article</li>
                            <li>✓ Both articles maintain their individual status</li>
                            <li>⚠️ <strong>Important:</strong> Parent must be published before this update can be published</li>
                        </ul>
                    </div>
                    
                    <p class="text-green-600 text-sm flex items-center">
                        <span class="material-icons text-sm mr-1 animate-spin">refresh</span>
                        Redirecting to dashboard...
                    </p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
        <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 p-6 mb-6 rounded-xl shadow-lg fade-in">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-red-500 rounded-full flex items-center justify-center">
                        <span class="material-icons text-white text-2xl">error</span>
                    </div>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-bold text-red-800 mb-1">Error</h3>
                    <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" id="linkForm">
            <!-- Publishing Warning Banner -->
            <div class="bg-gradient-to-r from-orange-50 to-red-50 border-l-4 border-orange-500 rounded-xl p-5 mb-6 fade-in pulse-warning">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-orange-500 rounded-full flex items-center justify-center">
                            <span class="material-icons text-white text-2xl">warning</span>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-bold text-orange-800 mb-2">Important: Publishing Requirements</h3>
                        <div class="bg-white bg-opacity-50 rounded-lg p-4">
                            <p class="text-sm text-orange-700 font-semibold mb-2">Before you can publish this update:</p>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-orange-600">
                                <li><strong>Parent must be published first</strong> - The parent article needs to be live before any updates can be published</li>
                                <li><strong>Both articles follow their own workflow</strong> - Push to Edited → Push to Headlines → Publish</li>
                                <li><strong>Updates are blocked until parent is published</strong> - You'll see a clear message if you try to publish too early</li>
                            </ol>
                            <p class="text-xs text-orange-600 mt-3 flex items-center">
                                <span class="material-icons text-xs mr-1">lightbulb</span>
                                <strong>Tip:</strong> For best results, publish the parent article to Headlines first, then work on publishing updates.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- How It Works -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-5 mb-6 fade-in">
                <div class="flex items-start">
                    <span class="material-icons text-blue-600 mr-3 mt-0.5 text-2xl">info</span>
                    <div class="flex-1">
                        <h3 class="font-bold text-blue-800 mb-2 text-lg">How Developing News Works</h3>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div class="bg-white bg-opacity-50 rounded-lg p-3">
                                <p class="font-semibold text-blue-800 text-sm mb-2">✓ What Happens:</p>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Update gets special "Developing" badge</li>
                                    <li>• Both articles stay in dashboard</li>
                                    <li>• Parent shows update count</li>
                                    <li>• Updates numbered automatically</li>
                                    <li>• Clear parent-child relationship</li>
                                </ul>
                            </div>
                            <div class="bg-white bg-opacity-50 rounded-lg p-3">
                                <p class="font-semibold text-blue-800 text-sm mb-2">⚡ Publishing Order:</p>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>1️⃣ Publish parent article first</li>
                                    <li>2️⃣ Then publish updates in sequence</li>
                                    <li>3️⃣ Each update references parent</li>
                                    <li>4️⃣ Readers see full story timeline</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl p-4 shadow-md border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-800"><?= $statusCounts['total'] ?></p>
                            <p class="text-xs text-gray-500">Total Available</p>
                        </div>
                        <span class="material-icons text-purple-500 text-3xl">article</span>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-md border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-800"><?= $statusCounts['published'] ?></p>
                            <p class="text-xs text-gray-500">Published</p>
                        </div>
                        <span class="material-icons text-green-500 text-3xl">cloud_done</span>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-md border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-800"><?= $statusCounts['headline'] ?></p>
                            <p class="text-xs text-gray-500">Headlines</p>
                        </div>
                        <span class="material-icons text-blue-500 text-3xl">star</span>
                    </div>
                </div>
                
                <div class="bg-white rounded-xl p-4 shadow-md border-l-4 border-orange-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-2xl font-bold text-gray-800"><?= $statusCounts['with_updates'] ?></p>
                            <p class="text-xs text-gray-500">With Updates</p>
                        </div>
                        <span class="material-icons text-orange-500 text-3xl">account_tree</span>
                    </div>
                </div>
            </div>

            <!-- Update Type Selection -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 fade-in">
                <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-purple-600 mr-2">label</span>
                    Step 1: Select Update Type
                </h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <label class="relative flex items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all group">
                        <input type="radio" name="update_type" value="developing" required class="sr-only peer">
                        <div class="text-center peer-checked:text-purple-600">
                            <span class="material-icons text-4xl mb-2 group-hover:scale-110 transition-transform">sync</span>
                            <p class="font-bold text-sm">Developing</p>
                            <p class="text-xs text-gray-500 mt-1">Ongoing story</p>
                        </div>
                        <div class="absolute inset-0 border-2 border-purple-600 rounded-xl opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                    </label>
                    
                    <label class="relative flex items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-red-500 hover:bg-red-50 transition-all group">
                        <input type="radio" name="update_type" value="breaking" class="sr-only peer">
                        <div class="text-center peer-checked:text-red-600">
                            <span class="material-icons text-4xl mb-2 group-hover:scale-110 transition-transform">notification_important</span>
                            <p class="font-bold text-sm">Breaking</p>
                            <p class="text-xs text-gray-500 mt-1">Urgent update</p>
                        </div>
                        <div class="absolute inset-0 border-2 border-red-600 rounded-xl opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                    </label>
                    
                    <label class="relative flex items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-blue-500 hover:bg-blue-50 transition-all group">
                        <input type="radio" name="update_type" value="update" class="sr-only peer">
                        <div class="text-center peer-checked:text-blue-600">
                            <span class="material-icons text-4xl mb-2 group-hover:scale-110 transition-transform">update</span>
                            <p class="font-bold text-sm">Update</p>
                            <p class="text-xs text-gray-500 mt-1">New info</p>
                        </div>
                        <div class="absolute inset-0 border-2 border-blue-600 rounded-xl opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                    </label>
                    
                    <label class="relative flex items-center justify-center p-4 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-yellow-500 hover:bg-yellow-50 transition-all group">
                        <input type="radio" name="update_type" value="correction" class="sr-only peer">
                        <div class="text-center peer-checked:text-yellow-600">
                            <span class="material-icons text-4xl mb-2 group-hover:scale-110 transition-transform">edit</span>
                            <p class="font-bold text-sm">Correction</p>
                            <p class="text-xs text-gray-500 mt-1">Fix errors</p>
                        </div>
                        <div class="absolute inset-0 border-2 border-yellow-600 rounded-xl opacity-0 peer-checked:opacity-100 transition-opacity"></div>
                    </label>
                </div>
                
                <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-xs text-gray-600 flex items-center">
                        <span class="material-icons text-xs mr-1">lightbulb</span>
                        <strong class="mr-1">Tip:</strong> Choose "Developing" for ongoing stories, "Breaking" for urgent updates, "Update" for new information, or "Correction" for fixing errors.
                    </p>
                </div>
            </div>

            <!-- Parent Article Selection -->
            <div class="bg-white rounded-2xl shadow-lg p-6 mb-6 fade-in">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center">
                        <span class="material-icons text-purple-600 mr-2">article</span>
                        Step 2: Select Parent Article
                    </h2>
                    
                    <!-- Filters -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- Status Filter -->
                        <select id="statusFilter" class="bg-gray-50 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">All Statuses</option>
                            <option value="published">✓ Published Only</option>
                            <option value="headline">★ Headlines Only</option>
                            <option value="regular">Regular</option>
                            <option value="edited">Edited</option>
                        </select>
                        
                        <!-- Category Filter -->
                        <select id="categoryFilter" class="bg-gray-50 border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Search Box -->
                <div class="mb-4">
                    <div class="relative">
                        <input type="text" id="searchParent" 
                               class="w-full p-4 pl-12 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-all text-base"
                               placeholder="Search parent articles by title...">
                        <span class="material-icons absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-2xl">search</span>
                    </div>
                </div>

                <?php if (empty($potentialParents)): ?>
                <div class="text-center py-12">
                    <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                        <span class="material-icons text-6xl text-gray-400">article</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-700 mb-2">No Parent Articles Available</h3>
                    <p class="text-gray-500 mb-6">There are no eligible articles to link to at the moment.</p>
                    <a href="../user_dashboard.php" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-purple-600 to-purple-700 text-white rounded-xl font-medium hover:from-purple-700 hover:to-purple-800 transition-all shadow-lg">
                        <span class="material-icons mr-2">arrow_back</span>
                        Return to Dashboard
                    </a>
                </div>
                <?php else: ?>
                <!-- Results Info -->
                <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-200">
                    <p class="text-sm text-gray-600 font-medium flex items-center">
                        <span class="material-icons text-sm mr-1">filter_list</span>
                        Showing <span id="resultCount" class="font-bold text-purple-600 mx-1"><?= count($potentialParents) ?></span> of <?= count($potentialParents) ?> articles
                    </p>
                    <button type="button" onclick="clearSelection()" class="text-sm text-purple-600 hover:text-purple-800 font-medium hidden" id="clearBtn">
                        <span class="material-icons text-sm mr-1">clear</span>
                        Clear Selection
                    </button>
                </div>

                <div id="parentArticlesList" class="space-y-3 max-h-[500px] overflow-y-auto pr-2">
                    <?php foreach ($potentialParents as $parent): 
                        $statusBadge = getStatusBadge($parent['is_pushed'], $parent['is_published']);
                    ?>
                    <div class="parent-card border-2 border-gray-200 rounded-xl p-4 hover:border-purple-300" 
                         data-category-id="<?= $parent['category_id'] ?>"
                         data-title="<?= htmlspecialchars(strtolower($parent['title'])) ?>"
                         data-status="<?= $parent['is_published'] ? 'published' : ($parent['is_pushed'] == 2 ? 'headline' : ($parent['is_pushed'] == 1 ? 'edited' : 'regular')) ?>"
                         onclick="selectParent(this, <?= $parent['id'] ?>)">
                        <input type="radio" name="parent_id" value="<?= $parent['id'] ?>" required class="hidden">
                        
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0 mr-4">
                                <!-- Title -->
                                <h4 class="font-bold text-gray-800 mb-3 leading-tight text-base">
                                    <?= htmlspecialchars($parent['title']) ?>
                                </h4>
                                
                                <!-- Badges -->
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <!-- Status Badge -->
                                    <span class="<?= $statusBadge['class'] ?> px-3 py-1 rounded-full text-xs font-bold flex items-center <?= $parent['is_published'] ? 'published-shine' : '' ?>">
                                        <span class="material-icons text-xs mr-1"><?= $statusBadge['icon'] ?></span>
                                        <?= $statusBadge['label'] ?>
                                    </span>
                                    
                                    <!-- Category Badge -->
                                    <?php if ($parent['category_name']): ?>
                                    <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-full text-xs font-semibold">
                                        <span class="material-icons text-xs mr-1">label</span>
                                        <?= htmlspecialchars($parent['category_name']) ?>
                                    </span>
                                    <?php endif; ?>
                                    
                                    <!-- Update Count Badge -->
                                    <?php if ($parent['update_count'] > 0): ?>
                                    <span class="bg-orange-50 text-orange-700 px-3 py-1 rounded-full text-xs font-bold border border-orange-200">
                                        <span class="material-icons text-xs mr-1">account_tree</span>
                                        <?= $parent['update_count'] ?> update<?= $parent['update_count'] > 1 ? 's' : '' ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Date -->
                                <div class="flex items-center text-xs text-gray-500">
                                    <span class="material-icons text-sm mr-1">schedule</span>
                                    <?= date('M d, Y g:i A', strtotime($parent['created_at'])) ?>
                                    <?php if ($parent['is_published']): ?>
                                    <span class="mx-2">•</span>
                                    <span class="material-icons text-sm mr-1 text-green-600">cloud_done</span>
                                    <span class="text-green-600 font-medium">Published <?= date('M d', strtotime($parent['published_at'])) ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Publishing Info -->
                                <?php if ($parent['is_published']): ?>
                                <div class="mt-2 p-2 bg-green-50 border border-green-200 rounded-lg">
                                    <p class="text-xs text-green-700 font-semibold flex items-center">
                                        <span class="material-icons text-xs mr-1">check_circle</span>
                                        ✓ Ready to link - Parent is published and can accept updates
                                    </p>
                                </div>
                                <?php else: ?>
                                <div class="mt-2 p-2 bg-orange-50 border border-orange-200 rounded-lg">
                                    <p class="text-xs text-orange-700 font-semibold flex items-center">
                                        <span class="material-icons text-xs mr-1">warning</span>
                                        ⚠️ Parent not yet published - You can link now, but publish parent first before publishing updates
                                    </p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Selection Icon -->
                            <div class="flex-shrink-0">
                                <span class="material-icons text-gray-300 select-icon transition-all text-3xl">radio_button_unchecked</span>
                                <span class="material-icons text-purple-600 select-icon-checked hidden transition-all text-3xl">check_circle</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- No Results Message -->
                <div id="noResults" class="hidden text-center py-8">
                    <span class="material-icons text-6xl text-gray-300 mb-3">search_off</span>
                    <p class="text-gray-500 font-medium">No articles match your filters</p>
                    <button type="button" onclick="resetFilters()" class="mt-3 text-purple-600 hover:text-purple-800 font-medium text-sm">
                        Clear all filters
                    </button>
                </div>

                <p class="text-xs text-gray-500 mt-4 flex items-center bg-gray-50 p-3 rounded-lg">
                    <span class="material-icons text-sm mr-2">info</span>
                    Showing up to 100 most recent articles. Use search and filters to find specific articles. Articles marked as "Published" (✓) are ready to accept updates immediately.
                </p>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-3 sticky bottom-4 z-10">
                <button type="submit"
                    class="flex-1 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white px-8 py-5 rounded-xl font-bold transition-all shadow-lg hover:shadow-xl flex items-center justify-center text-lg">
                    <span class="material-icons mr-2 text-2xl">link</span>
                    Link as Developing News Update
                </button>
                <a href="../user_dashboard.php"
                    class="px-8 py-5 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-xl font-bold transition-all flex items-center justify-center text-lg">
                    <span class="material-icons mr-2 text-2xl">cancel</span>
                    Cancel
                </a>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <script>
    // Parent article selection
    function selectParent(card, parentId) {
        document.querySelectorAll('.parent-card').forEach(c => {
            c.classList.remove('selected');
            c.querySelector('.select-icon').classList.remove('hidden');
            c.querySelector('.select-icon-checked').classList.add('hidden');
        });
        
        card.classList.add('selected');
        card.querySelector('.select-icon').classList.add('hidden');
        card.querySelector('.select-icon-checked').classList.remove('hidden');
        card.querySelector('input[type="radio"]').checked = true;
        
        document.getElementById('clearBtn')?.classList.remove('hidden');
        
        // Scroll into view
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function clearSelection() {
        document.querySelectorAll('.parent-card').forEach(c => {
            c.classList.remove('selected');
            c.querySelector('.select-icon').classList.remove('hidden');
            c.querySelector('.select-icon-checked').classList.add('hidden');
            c.querySelector('input[type="radio"]').checked = false;
        });
        document.getElementById('clearBtn')?.classList.add('hidden');
    }

    function filterArticles() {
        const searchTerm = document.getElementById('searchParent')?.value.toLowerCase() || '';
        const categoryId = document.getElementById('categoryFilter')?.value || '';
        const statusFilter = document.getElementById('statusFilter')?.value || '';
        const cards = document.querySelectorAll('.parent-card');
        let visibleCount = 0;
        
        cards.forEach(card => {
            const title = card.getAttribute('data-title');
            const cardCategoryId = card.getAttribute('data-category-id');
            const cardStatus = card.getAttribute('data-status');
            
            const matchesSearch = title.includes(searchTerm);
            const matchesCategory = !categoryId || cardCategoryId === categoryId;
            const matchesStatus = !statusFilter || cardStatus === statusFilter;
            const shouldShow = matchesSearch && matchesCategory && matchesStatus;
            
            card.style.display = shouldShow ? '' : 'none';
            if (shouldShow) visibleCount++;
        });
        
        document.getElementById('resultCount').textContent = visibleCount;
        
        const noResults = document.getElementById('noResults');
        const articlesList = document.getElementById('parentArticlesList');
        if (visibleCount === 0) {
            articlesList?.classList.add('hidden');
            noResults?.classList.remove('hidden');
        } else {
            articlesList?.classList.remove('hidden');
            noResults?.classList.add('hidden');
        }
    }

    function resetFilters() {
        document.getElementById('searchParent').value = '';
        document.getElementById('categoryFilter').value = '';
        document.getElementById('statusFilter').value = '';
        filterArticles();
    }

    document.getElementById('searchParent')?.addEventListener('input', filterArticles);
    document.getElementById('categoryFilter')?.addEventListener('change', filterArticles);
    document.getElementById('statusFilter')?.addEventListener('change', filterArticles);

    document.getElementById('linkForm')?.addEventListener('submit', function(e) {
        const updateType = document.querySelector('input[name="update_type"]:checked');
        const parentId = document.querySelector('input[name="parent_id"]:checked');
        
        if (!updateType) {
            e.preventDefault();
            alert('⚠️ Please select an update type');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        
        if (!parentId) {
            e.preventDefault();
            alert('⚠️ Please select a parent article');
            document.getElementById('searchParent')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        
        const submitBtn = e.target.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="material-icons mr-2 animate-spin text-2xl">sync</span>Linking Article...';
        }
    });
    </script>
</body>
</html>