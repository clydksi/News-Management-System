<?php
require 'config.php';
require 'functions.php';

// Get the article ID from the URL
$itemId = $_GET['id'] ?? '';
if (!$itemId) {
    echo "Article ID missing. <a href='index.php'>Back to front page</a>";
    exit;
}

// GraphQL query to fetch the full article
$itemQuery = 'query {
    item(id:"'.$itemId.'") {
        headLine
        bodyXhtml
        fragment
        byLine
        contentTimestamp
        thumbnailUrl
        type
        profile
        slug
        usageTerms

    }
}';


$data = reutersQuery($itemQuery, $accessToken, $endpoint);
$item = $data['data']['item'] ?? null;

if (!$item) {
    echo "Article not found. <a href='index.php'>Back to front page</a>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($item['headLine']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-6">

<div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow">
    <!-- Headline -->
    <h1 class="text-3xl font-bold mb-4"><?= htmlspecialchars($item['headLine']) ?></h1>

    <!-- Byline and Date -->
    <p class="text-gray-600 mb-4">
        Published: <?= date("F j, Y g:i A", strtotime($item['contentTimestamp'])) ?>
        <?php if (!empty($item['byLine'])): ?> | By: <?= htmlspecialchars($item['byLine']) ?> <?php endif; ?>
    </p>

    <!-- Thumbnail -->
    <?php if (!empty($item['thumbnailUrl'])): ?>
        <img src="image.php?url=<?= urlencode($item['thumbnailUrl']) ?>" class="w-full rounded mb-4">
    <?php endif; ?>


    <!-- Fragment / Intro -->
    <?php if (!empty($item['fragment'])): ?>
        <p class="text-gray-700 mb-4"><?= htmlspecialchars($item['fragment']) ?></p>
    <?php endif; ?>

    <!-- Info Source -->
    <?php if (!empty($item['infosource'])): ?>
        <div class="text-gray-700 mb-4">
            <?php if (isset($item['infosource'][0])): // multiple sources ?>
                <p class="font-semibold mb-1">Sources:</p>
                <ul class="list-disc ml-6">
                    <?php foreach ($item['infosource'] as $src): ?>
                        <li>
                            <?= htmlspecialchars($src['literal'] ?? 'Unknown Source') ?>
                            <?php if (!empty($src['code'])): ?>
                                (<?= htmlspecialchars($src['code']) ?>)
                            <?php endif; ?>
                            <?php if (!empty($src['role'])): ?>
                                – <?= htmlspecialchars($src['role']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: // single source ?>
                <p>
                    <strong>Source:</strong>
                    <?= htmlspecialchars($item['infosource']['literal'] ?? 'Unknown Source') ?>
                    <?php if (!empty($item['infosource']['code'])): ?>
                        (<?= htmlspecialchars($item['infosource']['code']) ?>)
                    <?php endif; ?>
                    <?php if (!empty($item['infosource']['role'])): ?>
                        – <?= htmlspecialchars($item['infosource']['role']) ?>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>


    <!-- Full article body -->
    <div class="prose prose-lg max-w-none">
        <?= $item['bodyXhtml'] ?? '' ?>
    </div>

    <!-- Back to Front Page -->
    <p class="mt-6">
        <a href="../reuters.php" class="text-blue-600 font-semibold">← Back to Reuters Front Page</a>
    </p>
</div>

</body>
</html>
