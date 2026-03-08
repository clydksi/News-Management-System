<!-- ═══════════════════════════════════════════════════ FILTERS PARTIAL ══ -->
<div class="bg-white border-b border-gray-100 shadow-sm" id="filtersPanel">

    <!-- ── Search Bar ──────────────────────────────────────────────────── -->
    <div class="px-4 lg:px-8 pt-5 pb-4">
        <form method="GET" id="searchForm">
            <?php foreach (array_diff_key($baseParams, ['search' => '', 'page' => '']) as $key => $value): ?>
                <input type="hidden" name="<?= Helper::e($key) ?>" value="<?= Helper::e($value) ?>">
            <?php endforeach; ?>

            <div class="relative max-w-2xl mx-auto group">
                <span class="material-icons-round absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-purple-500 transition-colors pointer-events-none">
                    search
                </span>
                <input
                    type="text"
                    name="search"
                    id="searchInput"
                    value="<?= Helper::e($searchQuery) ?>"
                    placeholder="Search news by keyword, source, or topic..."
                    autocomplete="off"
                    class="w-full bg-gray-50 border border-gray-200 rounded-full py-3 pl-12 pr-12
                           text-sm text-gray-800 placeholder-gray-400
                           focus:outline-none focus:ring-2 focus:ring-purple-400 focus:border-transparent
                           transition-all"
                />
                <?php if (!empty($searchQuery)): ?>
                    <a href="?<?= buildUrlParams(array_diff_key($baseParams, ['search' => '', 'page' => ''])) ?>"
                       title="Clear search"
                       class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 transition-colors">
                        <span class="material-icons-round text-lg">cancel</span>
                    </a>
                <?php else: ?>
                    <button type="submit"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-purple-600 transition-colors">
                        <span class="material-icons-round text-lg">arrow_forward</span>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ── Divider ─────────────────────────────────────────────────────── -->
    <div class="border-t border-gray-100 mx-4 lg:mx-8"></div>

    <!-- ── Category Chips ──────────────────────────────────────────────── -->
    <div class="px-4 lg:px-8 py-3 overflow-x-auto scrollbar-hide">
        <div class="flex gap-2 min-w-max mx-auto justify-start lg:justify-center" id="categoryFilters">
            <?php foreach ($config['categories'] as $cat => $meta): ?>
                <?php
                $isActive  = isActiveCategory($category, $cat);
                $catParams = array_merge($baseParams, ['category' => $cat, 'page' => 1]);
                ?>
                <a href="?<?= buildUrlParams($catParams) ?>"
                   class="filter-chip flex items-center gap-1.5 px-4 py-2 rounded-full text-sm font-medium 
                          border transition-all select-none
                          <?= $isActive
                              ? 'text-white shadow-md border-transparent'
                              : 'bg-gray-50 text-gray-600 border-gray-200 hover:border-gray-300 hover:bg-gray-100' ?>"
                   style="<?= $isActive ? "background-color: {$meta['color']}; border-color: {$meta['color']};" : '' ?>">
                    <span class="material-icons-round" style="font-size: 16px;"><?= $meta['icon'] ?></span>
                    <?= Helper::e($meta['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Divider ─────────────────────────────────────────────────────── -->
    <div class="border-t border-gray-100 mx-4 lg:mx-8"></div>

    <!-- ── Country + Sort + Language Row ───────────────────────────────── -->
    <div class="px-4 lg:px-8 py-3 flex flex-wrap items-center gap-3">

        <!-- Countries -->
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wide mr-1">Country</span>
                <?php foreach ($config['countries'] as $code => $meta): ?>
                    <?php
                    $isActive      = isActiveCountry($country, $code);
                    $isSupported   = $meta['supported'] ?? true;
                    $countryParams = array_merge($baseParams, ['country' => $code, 'page' => 1]);
                    ?>
                    <a href="?<?= buildUrlParams($countryParams) ?>"
                    title="<?= Helper::e($meta['name']) ?><?= !$isSupported ? ' (via search fallback)' : '' ?>"
                    class="flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium border transition-all
                            <?= $isActive
                                ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
                                : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' ?>">
                        <span><?= $meta['flag'] ?></span>
                        <span class="hidden sm:inline"><?= Helper::e($meta['name']) ?></span>
                        <?php if (!$isSupported): ?>
                            <span title="Uses search fallback" class="opacity-50">~</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
        </div>

        <!-- Spacer -->
        <div class="flex-1 hidden lg:block"></div>

        <!-- Sort By -->
        <?php if (!empty($config['sort_options'])): ?>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Sort</span>
            <div class="flex gap-1">
                <?php foreach ($config['sort_options'] as $value => $label): ?>
                    <?php
                    $isActive   = ($sortBy === $value);
                    $sortParams = array_merge($baseParams, ['sortBy' => $value, 'page' => 1]);
                    ?>
                    <a href="?<?= buildUrlParams($sortParams) ?>"
                       class="px-3 py-1 rounded-full text-xs font-medium border transition-all
                              <?= $isActive
                                  ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm'
                                  : 'bg-gray-50 text-gray-600 border-gray-200 hover:bg-gray-100' ?>">
                        <?= Helper::e($label) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Language -->
        <?php if (!empty($config['languages'])): ?>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-400 font-medium uppercase tracking-wide">Lang</span>
            <select
                onchange="applyLanguage(this.value)"
                class="bg-gray-50 border border-gray-200 text-gray-700 text-xs rounded-lg px-2 py-1.5
                       focus:ring-2 focus:ring-purple-400 focus:outline-none transition">
                <?php foreach ($config['languages'] as $code => $name): ?>
                    <option value="<?= $code ?>" <?= $language === $code ? 'selected' : '' ?>>
                        <?= Helper::e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Divider ─────────────────────────────────────────────────────── -->
    <div class="border-t border-gray-100 mx-4 lg:mx-8"></div>

    <!-- ── Cache Info Banner ────────────────────────────────────────────── -->
    <?php if ($fromCache && !empty($newsData['cached_at'])): ?>
    <div class="mx-4 lg:mx-8 my-3 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5
                flex items-center justify-between gap-3 text-sm">
        <div class="flex items-center gap-2 text-emerald-800">
            <span class="material-icons-round text-emerald-500" style="font-size:16px">inventory_2</span>
            <span>
                Cached at <strong><?= date('g:i A', strtotime($newsData['cached_at'])) ?></strong>
                <span class="text-emerald-500 mx-1">•</span>
                Expires <strong><?= date('g:i A', strtotime($newsData['expires_at'])) ?></strong>
            </span>
        </div>
        <a href="?<?= buildUrlParams(array_merge($baseParams, ['refresh' => '1', 'page' => 1])) ?>"
           class="flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-900
                  bg-emerald-100 hover:bg-emerald-200 px-3 py-1.5 rounded-full transition-all">
            <span class="material-icons-round" style="font-size:14px">refresh</span>
            Refresh Now
        </a>
    </div>
    <?php endif; ?>

    <!-- ── Bottom Toolbar ───────────────────────────────────────────────── -->
    <div class="px-4 lg:px-8 py-3 flex flex-col sm:flex-row justify-between items-center gap-3">

        <!-- Items Per Page -->
        <div class="flex items-center gap-2 text-sm text-gray-600">
            <span class="material-icons-round text-gray-400 text-sm">layers</span>
            <label for="itemsPerPageSelect" class="text-sm">Show</label>
            <select
                id="itemsPerPageSelect"
                onchange="changeItemsPerPage(this.value)"
                class="bg-gray-50 border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm
                       focus:ring-2 focus:ring-purple-400 focus:outline-none transition">
                <?php foreach ($config['pagination']['allowed_items_per_page'] as $size): ?>
                    <option value="<?= $size ?>" <?= $itemsPerPage == $size ? 'selected' : '' ?>>
                        <?= $size === 100 ? '100 (All)' : $size ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span>per page</span>
            <?php if (isset($paginator)): ?>
                <span class="text-gray-400">
                    — Page <strong class="text-gray-700"><?= $currentPage ?></strong>
                    of <strong class="text-gray-700"><?= $totalPages ?></strong>
                </span>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center flex-wrap gap-2 justify-end">

            <!-- Import All -->
            <?php if (!empty($paginatedArticles)): ?>
            <button type="button"
                    onclick="saveAllVisibleArticles()"
                    class="flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-green-600
                           hover:from-emerald-600 hover:to-green-700 text-white
                           px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-95">
                <span class="material-icons-round text-sm">cloud_download</span>
                Import All
                <span class="bg-white/20 text-white text-xs px-1.5 py-0.5 rounded-full font-bold">
                    <?= count($paginatedArticles) ?>
                </span>
            </button>
            <?php endif; ?>

            <!-- Go to Dashboard -->
            <a href="user_dashboard.php"
               class="flex items-center gap-2 bg-gradient-to-r from-purple-600 to-violet-600
                      hover:from-purple-700 hover:to-violet-700 text-white
                      px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-all active:scale-95">
                <span class="material-icons-round text-sm">dashboard</span>
                Dashboard
            </a>

            <!-- Refresh -->
            <button type="button"
                    onclick="refreshData()"
                    title="Refresh from cache or API"
                    class="flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-600
                           px-4 py-2 rounded-lg text-sm font-medium transition-all active:scale-95">
                <span class="material-icons-round text-sm">refresh</span>
                <span class="hidden sm:inline">Refresh</span>
            </button>

            <!-- Mobile Filters Toggle -->
            <button type="button"
                    onclick="toggleFilters()"
                    class="lg:hidden flex items-center gap-2 bg-gray-100 hover:bg-gray-200
                           text-gray-600 px-4 py-2 rounded-lg text-sm font-medium transition-all">
                <span class="material-icons-round text-sm">tune</span>
                Filters
            </button>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════ FILTERS JS ══ -->
<script>
function changeItemsPerPage(value) {
    const params = new URLSearchParams(window.location.search);
    params.set('itemsPerPage', value);
    params.set('page', '1');
    window.location.href = '?' + params.toString();
}

function applyLanguage(value) {
    const params = new URLSearchParams(window.location.search);
    params.set('language', value);
    params.set('page', '1');
    window.location.href = '?' + params.toString();
}

function refreshData() {
    const params = new URLSearchParams(window.location.search);
    params.set('refresh', '1');
    params.delete('page');
    window.location.href = '?' + params.toString();
}

function toggleFilters() {
    const panel = document.getElementById('filtersPanel');
    if (!panel) return;
    panel.classList.toggle('hidden');
}

// Submit search on Enter (already handled by form, this enhances UX)
document.getElementById('searchInput')?.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        this.value = '';
        this.form.submit();
    }
});
</script>