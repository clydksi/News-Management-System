<!-- Pagination -->
<div class="flex flex-col sm:flex-row justify-between items-center mt-8 space-y-4 sm:space-y-0 bg-white rounded-lg p-4 shadow-sm">
    <div class="text-sm text-gray-600">
        Showing <strong><?= $paginator->getOffset() + 1 ?></strong> to 
        <strong><?= min($paginator->getOffset() + $paginator->getItemsPerPage(), $paginator->getTotalItems()) ?></strong> of 
        <strong><?= number_format($paginator->getTotalItems()) ?></strong> articles
    </div>

    <div class="flex items-center space-x-1">
        <?php if ($paginator->hasPrevious()): ?>
            <?php $prevParams = array_merge($baseParams, ['page' => $paginator->getCurrentPage() - 1]); ?>
            <a href="?<?= buildUrlParams($prevParams) ?>" 
               class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                <span class="material-icons text-sm">chevron_left</span>
            </a>
        <?php endif; ?>

        <span class="px-4 py-2 bg-yellow-600 text-white rounded-lg font-medium">
            Page <?= $paginator->getCurrentPage() ?> of <?= $paginator->getTotalPages() ?>
        </span>

        <?php if ($paginator->hasNext()): ?>
            <?php $nextParams = array_merge($baseParams, ['page' => $paginator->getCurrentPage() + 1]); ?>
            <a href="?<?= buildUrlParams($nextParams) ?>" 
               class="bg-white hover:bg-gray-50 border border-gray-300 text-gray-600 px-3 py-2 rounded-lg transition-colors flex items-center">
                <span class="material-icons text-sm">chevron_right</span>
            </a>
        <?php endif; ?>
    </div>
</div>