<?php
require dirname(__DIR__, 2) . '/db.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name, d.name AS department_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN departments d ON p.department_id = d.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    header('Location: index.php');
    exit;
}

// Get related articles (same category, excluding current article)
$relatedStmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM published_news p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.category_id = ? AND p.id != ?
    ORDER BY p.published_at DESC
    LIMIT 3
");
$relatedStmt->execute([$article['category_id'], $id]);
$relatedArticles = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// Helper function to format time ago
function timeAgo($timestamp) {
    if (!$timestamp) return 'Just now';
    $time = strtotime($timestamp);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
    return date('M d, Y', $time);
}

function getExcerpt($content, $length = 120) {
    $text = strip_tags($content);
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . '...';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($article['title']) ?> | DZRH News</title>
    <meta name="description" content="<?= htmlspecialchars(getExcerpt($article['content'], 160)) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">
    <style>
        body {
            font-family: 'Work Sans', sans-serif;
        }
        .article-content p {
            margin-bottom: 1.25rem;
            line-height: 1.8;
        }
        .article-content h2 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        .article-content h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .article-content ul, .article-content ol {
            margin-left: 2rem;
            margin-bottom: 1.25rem;
        }
        .article-content li {
            margin-bottom: 0.5rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#2563eb",
                        "accent-red": "#dc2626",
                    },
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">

<!--< ?php include 'navbar.php'; ?>-->

<!-- Breadcrumb -->
<div class="bg-white border-b border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-3">
        <nav class="flex items-center gap-2 text-sm text-gray-600">
            <a href="index.php" class="hover:text-primary transition-colors">Home</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <a href="index.php?category=<?= strtolower($article['category_name'] ?? 'general') ?>" class="hover:text-primary transition-colors">
                <?= htmlspecialchars($article['category_name'] ?? 'General') ?>
            </a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-gray-900 font-medium truncate"><?= htmlspecialchars(substr($article['title'], 0, 50)) ?>...</span>
        </nav>
    </div>
</div>

<!-- Article Container -->
<article class="min-h-screen py-8 md:py-12 fade-in">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Category & Time Badge -->
        <div class="flex items-center gap-3 mb-6">
            <span class="inline-flex items-center gap-1.5 bg-accent-red text-white text-sm font-bold px-4 py-2 rounded-full uppercase tracking-wide shadow-lg">
                <span class="material-symbols-outlined text-base">article</span>
                <?= htmlspecialchars($article['category_name'] ?? 'General') ?>
            </span>
            <span class="text-sm text-gray-500 font-medium">
                <?= timeAgo($article['published_at']) ?>
            </span>
        </div>

        <!-- Article Title -->
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight mb-6">
            <?= htmlspecialchars($article['title']) ?>
        </h1>

        <!-- Meta Information -->
        <div class="flex flex-wrap items-center gap-6 text-sm text-gray-600 mb-8 pb-8 border-b-2 border-gray-200">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">calendar_today</span>
                <time datetime="<?= $article['published_at'] ?>" class="font-medium">
                    <?= date('F j, Y', strtotime($article['published_at'])) ?>
                </time>
            </div>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">schedule</span>
                <span class="font-medium"><?= date('g:i A', strtotime($article['published_at'])) ?></span>
            </div>
            <?php if ($article['department_name']): ?>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg text-primary">corporate_fare</span>
                <span class="font-medium"><?= htmlspecialchars($article['department_name']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Featured Image -->
        <?php if ($article['thumbnail']): ?>
        <figure class="mb-10 rounded-2xl overflow-hidden shadow-2xl">
            <img 
                src="../<?= htmlspecialchars($article['thumbnail']) ?>" 
                alt="<?= htmlspecialchars($article['title']) ?>"
                class="w-full h-auto object-cover"
                style="max-height: 600px;"
                loading="eager"
            >
        </figure>
        <?php endif; ?>

        <!-- Article Content -->
        <div class="article-content prose prose-lg max-w-none">
            <div class="text-gray-800 leading-relaxed space-y-4 text-lg">
                <?= nl2br(htmlspecialchars($article['content'])) ?>
            </div>
        </div>

        <!-- Tags (Optional) -->
        <div class="mt-10 pt-8 border-t border-gray-200">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="material-symbols-outlined text-gray-600">label</span>
                <span class="text-sm font-semibold text-gray-700">Tags:</span>
                <a href="index.php?category=<?= strtolower($article['category_name'] ?? 'general') ?>" 
                   class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-full transition-colors">
                    <?= htmlspecialchars($article['category_name'] ?? 'General') ?>
                </a>
                <a href="index.php" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-full transition-colors">
                    DZRH News
                </a>
            </div>
        </div>

        <!-- Share Section -->
        <div class="mt-10 pt-8 border-t border-gray-200">
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center justify-between">
                <!-- Share Buttons -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <span class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="material-symbols-outlined">share</span>
                        Share this article:
                    </span>
                    <div class="flex items-center gap-2">
                        <button onclick="shareOnFacebook()" 
                                class="p-2.5 rounded-full bg-blue-600 text-white hover:bg-blue-700 transition-all hover:scale-110 shadow-lg" 
                                title="Share on Facebook">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </button>
                        <button onclick="shareOnTwitter()" 
                                class="p-2.5 rounded-full bg-sky-500 text-white hover:bg-sky-600 transition-all hover:scale-110 shadow-lg" 
                                title="Share on Twitter">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </button>
                        <button onclick="shareOnWhatsApp()" 
                                class="p-2.5 rounded-full bg-green-600 text-white hover:bg-green-700 transition-all hover:scale-110 shadow-lg" 
                                title="Share on WhatsApp">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
                            </svg>
                        </button>
                        <button onclick="copyLink()" 
                                class="p-2.5 rounded-full bg-gray-700 text-white hover:bg-gray-800 transition-all hover:scale-110 shadow-lg" 
                                title="Copy Link">
                            <span class="material-symbols-outlined text-lg">link</span>
                        </button>
                    </div>
                </div>

                <!-- Back to Home -->
                <a href="news.php" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-lg hover:bg-blue-700 transition-all font-semibold shadow-lg hover:shadow-xl">
                    <span class="material-symbols-outlined">arrow_back</span>
                    Back to News
                </a>
            </div>
        </div>

    </div>
</article>

<!-- Related Articles Section -->
<?php if (!empty($relatedArticles)): ?>
<section class="bg-white py-16 border-t-2 border-gray-200">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center gap-3 mb-8">
            <span class="material-symbols-outlined text-3xl text-primary">library_books</span>
            <h2 class="text-3xl font-bold text-gray-900">Related Articles</h2>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php foreach ($relatedArticles as $related): 
                $thumb = $related['thumbnail'] ? '../' . htmlspecialchars($related['thumbnail']) : 'https://via.placeholder.com/400x300?text=No+Image';
                $excerpt = getExcerpt($related['content']);
            ?>
            <article class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-all duration-300 hover:-translate-y-2">
                <a href="article.php?id=<?= $related['id'] ?>" class="block">
                    <div class="relative h-48 overflow-hidden">
                        <img src="<?= $thumb ?>" 
                             alt="<?= htmlspecialchars($related['title']) ?>"
                             class="w-full h-full object-cover transition-transform duration-300 hover:scale-110">
                        <?php if (!empty($related['category_name'])): ?>
                        <div class="absolute top-3 left-3 bg-accent-red text-white text-xs font-bold px-3 py-1 rounded-full">
                            <?= htmlspecialchars($related['category_name']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-5">
                        <div class="flex items-center gap-2 text-xs text-gray-500 mb-2">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <span><?= timeAgo($related['published_at']) ?></span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900 mb-2 leading-tight line-clamp-2 hover:text-primary transition-colors">
                            <?= htmlspecialchars($related['title']) ?>
                        </h3>
                        <p class="text-sm text-gray-600 line-clamp-3">
                            <?= $excerpt ?>
                        </p>
                        <div class="mt-4">
                            <span class="text-sm font-semibold text-primary hover:underline inline-flex items-center gap-1">
                                Read more
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </span>
                        </div>
                    </div>
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Share Functions -->
<script>
const pageUrl = encodeURIComponent(window.location.href);
const pageTitle = encodeURIComponent(<?= json_encode($article['title']) ?>);

function shareOnFacebook() {
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${pageUrl}`, '_blank', 'width=600,height=400');
}

function shareOnTwitter() {
    window.open(`https://twitter.com/intent/tweet?url=${pageUrl}&text=${pageTitle}`, '_blank', 'width=600,height=400');
}

function shareOnWhatsApp() {
    window.open(`https://wa.me/?text=${pageTitle}%20${pageUrl}`, '_blank');
}

function copyLink() {
    navigator.clipboard.writeText(window.location.href).then(() => {
        alert('Link copied to clipboard!');
    }).catch(err => {
        console.error('Failed to copy:', err);
    });
}
</script>

</body>
</html>