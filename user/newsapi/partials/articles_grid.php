<!-- News Articles Section -->
<section class="mb-6 lg:mb-8">
    <?php if (empty($paginatedArticles)): ?>
        <div class="bg-white rounded-xl lg:rounded-2xl shadow-md p-12 text-center">
            <span class="material-icons text-8xl text-gray-300 mb-4">sentiment_dissatisfied</span>
            <h4 class="text-2xl font-semibold text-gray-600 mb-2">No Articles Found</h4>
            <p class="text-gray-500 mb-4">
                <?= $searchQuery ? "No articles match your search for \"" . Helper::e($searchQuery) . "\"" : "No articles available in this section yet." ?>
            </p>
            <?php if ($searchQuery): ?>
                <a href="?<?= buildUrlParams(array_diff_key($baseParams, ['search' => ''])) ?>" 
                   class="inline-block bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition-colors">
                    Clear Search
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div id="articlesGrid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 transition-opacity duration-300">
            <?php foreach ($paginatedArticles as $index => $article): ?>
                <div class="news-card bg-white rounded-xl shadow-md overflow-hidden border border-gray-100" 
                     data-article-id="<?= $index ?>">
                    <!-- Image -->
                    <?php if (!empty($article['urlToImage'])): ?>
                        <div class="relative h-48 bg-gray-200 overflow-hidden">
                            <img src="<?= Helper::e($article['urlToImage']) ?>" 
                                 alt="<?= Helper::e($article['title']) ?>"
                                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                 onerror="this.parentElement.innerHTML='<div class=\'w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-100 to-purple-200\'><span class=\'material-icons text-6xl text-purple-400\'>image_not_supported</span></div>'">
                        </div>
                    <?php else: ?>
                        <div class="h-48 bg-gradient-to-br from-purple-100 to-purple-200 flex items-center justify-center">
                            <span class="material-icons text-6xl text-purple-400">article</span>
                        </div>
                    <?php endif; ?>

                    <div class="p-5">
                        <!-- Header -->
                        <div class="flex items-center justify-between mb-3">
                            <span class="badge bg-purple-100 text-purple-700">
                                <?= Helper::e($article['source']['name'] ?? 'News') ?>
                            </span>
                            <span class="text-xs text-gray-500">
                                <?= Helper::timeAgo($article['publishedAt']) ?>
                            </span>
                        </div>

                        <!-- Title -->
                        <h4 class="font-bold text-gray-800 mb-2 text-lg leading-tight hover:text-purple-600 transition-colors cursor-pointer line-clamp-2"
                            onclick="window.open('<?= Helper::e($article['url']) ?>', '_blank')"
                            title="<?= Helper::e($article['title']) ?>">
                            <?= Helper::e($article['title']) ?>
                        </h4>

                        <!-- Description -->
                        <p class="text-gray-600 text-sm leading-relaxed truncate-text mb-4">
                            <?= Helper::e($article['description'] ?: $article['content'] ?: 'No description available') ?>
                        </p>

                        <!-- Metadata -->
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-4 pb-4 border-b">
                            <div class="flex items-center" title="Source">
                                <span class="material-icons text-xs mr-1">business</span>
                                <span class="truncate max-w-[120px]"><?= Helper::e($article['source']['name'] ?? 'Unknown') ?></span>
                            </div>
                            <?php if (!empty($article['author'])): ?>
                                <div class="flex items-center" title="Author">
                                    <span class="material-icons text-xs mr-1">person</span>
                                    <span class="truncate max-w-[120px]"><?= Helper::e($article['author']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Actions -->
                        <div class="grid grid-cols-2 gap-2">
                            <button onclick="window.open('<?= Helper::e($article['url']) ?>', '_blank');"
                                class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm font-medium">
                                <span class="material-icons text-sm mr-1">open_in_new</span>
                                Read
                            </button>
                            <button type="button" 
                                class="save-article-btn bg-green-50 hover:bg-green-100 text-green-600 px-3 py-2 rounded-lg transition-colors flex items-center justify-center text-sm font-medium"
                                data-article='<?= htmlspecialchars(json_encode($article), ENT_QUOTES, 'UTF-8') ?>'
                                data-index="<?= $index ?>">
                                <span class="material-icons text-sm mr-1">cloud_download</span>
                                Import
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>