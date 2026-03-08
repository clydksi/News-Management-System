/**
 * ═══════════════════════════════════════════════════════════
 * DZRH News - Category Filter System v2.1 (Debugged)
 * Enhanced with dropdown sync, expandable panels, and debugging
 * ═══════════════════════════════════════════════════════════
 */

console.log('🚀 Category Filter System Loading...');

// State management
const FilterState = {
    currentCategory: 'all',
    currentPage: 1,
    isLoading: false,
    totalPages: 1
};

// DOM Elements Cache
const Elements = {
    // Filter controls
    dropdown: null,
    filterButtons: [],
    showAllBtn: null,
    allCategoriesPanel: null,
    
    // Content areas
    articlesGrid: null,
    loadingIndicator: null,
    emptyState: null,
    articlesSection: null,
    
    // Info displays
    categoryTitle: null,
    articleCount: null,
    paginationContainer: null
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

/**
 * Main initialization function
 */
function init() {
    console.log('🔧 Initializing Category Filter System...');
    
    // Cache DOM elements
    cacheElements();
    
    // Verify required elements
    if (!verifyElements()) {
        console.error('❌ Critical elements missing! Filter system disabled.');
        return;
    }
    
    // Setup event listeners
    setupEventListeners();
    
    // Set initial active state for "All" category
    updateUIState('all');
    
    console.log('✅ Category Filter System initialized successfully!');
    console.log('📊 Initial state:', FilterState);
}

/**
 * Cache all DOM elements
 */
function cacheElements() {
    Elements.dropdown = document.getElementById('categoryDropdown');
    Elements.filterButtons = Array.from(document.querySelectorAll('.category-filter'));
    Elements.showAllBtn = document.getElementById('showAllCategoriesBtn');
    Elements.allCategoriesPanel = document.getElementById('allCategoriesPanel');
    
    Elements.articlesGrid = document.getElementById('articlesGrid');
    Elements.loadingIndicator = document.getElementById('loadingIndicator');
    Elements.emptyState = document.getElementById('emptyState');
    Elements.articlesSection = document.getElementById('articlesSection');
    
    Elements.categoryTitle = document.getElementById('categoryTitle');
    Elements.articleCount = document.getElementById('articleCount');
    Elements.paginationContainer = document.getElementById('paginationContainer');
    
    console.log('📦 Cached elements:', {
        dropdown: !!Elements.dropdown,
        filterButtons: Elements.filterButtons.length,
        articlesGrid: !!Elements.articlesGrid,
        hasExpandablePanel: !!Elements.allCategoriesPanel,
        showAllBtn: !!Elements.showAllBtn
    });
}

/**
 * Verify critical elements exist
 */
function verifyElements() {
    const critical = [
        { name: 'articlesGrid', element: Elements.articlesGrid },
        { name: 'filterButtons', element: Elements.filterButtons.length > 0 }
    ];
    
    const missing = critical.filter(item => !item.element);
    
    if (missing.length > 0) {
        console.error('❌ Missing critical elements:', missing.map(m => m.name));
        return false;
    }
    
    return true;
}

/**
 * Setup all event listeners
 */
function setupEventListeners() {
    // Dropdown change handler
    if (Elements.dropdown) {
        Elements.dropdown.addEventListener('change', handleDropdownChange);
        console.log('✓ Dropdown listener attached');
    }
    
    // Filter button click handlers (including initial visible buttons)
    Elements.filterButtons.forEach((button) => {
        const category = button.getAttribute('data-category');
        if (category) {
            button.addEventListener('click', (e) => handleButtonClick(e, category));
        }
    });
    console.log(`✓ ${Elements.filterButtons.length} button listeners attached`);
    
    // Show/Hide all categories button
    if (Elements.showAllBtn && Elements.allCategoriesPanel) {
        Elements.showAllBtn.addEventListener('click', toggleAllCategories);
        console.log('✓ Expandable panel listener attached');
    }
    
    // Event delegation for dynamically added buttons in panel
    if (Elements.allCategoriesPanel) {
        Elements.allCategoriesPanel.addEventListener('click', handlePanelClick);
        console.log('✓ Panel delegation listener attached');
    }
}

/**
 * Handle dropdown selection change
 */
function handleDropdownChange(e) {
    const category = e.target.value;
    console.log('📋 Dropdown changed to:', category);
    
    updateUIState(category);
    loadArticles(category, 1);
}

/**
 * Handle filter button click
 */
function handleButtonClick(e, category) {
    e.preventDefault();
    console.log('🔘 Button clicked:', category);
    
    // Sync dropdown
    if (Elements.dropdown && category) {
        Elements.dropdown.value = category;
    }
    
    updateUIState(category);
    loadArticles(category, 1);
}

/**
 * Handle clicks in expandable panel
 */
function handlePanelClick(e) {
    const button = e.target.closest('.category-filter');
    if (button) {
        e.preventDefault();
        const category = button.getAttribute('data-category');
        console.log('🔘 Panel button clicked:', category);
        
        if (!category) {
            console.warn('⚠️ Button missing data-category attribute');
            return;
        }
        
        // Sync dropdown
        if (Elements.dropdown) {
            Elements.dropdown.value = category;
        }
        
        updateUIState(category);
        loadArticles(category, 1);
    }
}

/**
 * Toggle expandable categories panel
 */
function toggleAllCategories(e) {
    e.preventDefault();
    
    if (!Elements.allCategoriesPanel) {
        console.warn('⚠️ Categories panel not found');
        return;
    }
    
    const isHidden = Elements.allCategoriesPanel.classList.contains('hidden');
    const panelButtons = Elements.allCategoriesPanel.querySelectorAll('.category-filter');
    const remainingCount = panelButtons.length;
    
    if (isHidden) {
        Elements.allCategoriesPanel.classList.remove('hidden');
        Elements.showAllBtn.innerHTML = `
            <span>Show less</span>
            <span class="material-symbols-outlined text-sm">expand_less</span>
        `;
        console.log(`📂 Expanded categories panel (${remainingCount} additional categories)`);
    } else {
        Elements.allCategoriesPanel.classList.add('hidden');
        Elements.showAllBtn.innerHTML = `
            <span>+${remainingCount} more</span>
            <span class="material-symbols-outlined text-sm">expand_more</span>
        `;
        console.log('📁 Collapsed categories panel');
    }
}

/**
 * Update UI state (active buttons, etc.)
 */
function updateUIState(category) {
    if (!category) {
        console.warn('⚠️ updateUIState called with invalid category');
        return;
    }
    
    FilterState.currentCategory = category;
    
    // Get all buttons (including those in expandable panel)
    const allButtons = document.querySelectorAll('.category-filter');
    console.log(`🎨 Updating UI state for "${category}" (${allButtons.length} buttons found)`);
    
    // Reset all buttons
    allButtons.forEach(btn => {
        // Remove active states
        btn.classList.remove('active', 'bg-primary', 'text-white', 'shadow-button');
        
        // Restore default styles based on location
        if (btn.closest('#allCategoriesPanel')) {
            // Buttons in expandable panel
            btn.classList.remove('bg-gray-100', 'dark:bg-gray-700');
            btn.classList.add('bg-gray-50', 'dark:bg-gray-800', 'hover:bg-primary', 'hover:text-white');
        } else {
            // Regular filter buttons
            btn.classList.remove('bg-gray-50', 'dark:bg-gray-800');
            btn.classList.add('bg-gray-100', 'dark:bg-gray-700');
        }
        btn.classList.add('text-text-light', 'dark:text-text-dark');
    });
    
    // Activate selected button(s) - handle both exact match and case-insensitive
    const activeButtons = Array.from(allButtons).filter(btn => {
        const btnCategory = btn.getAttribute('data-category');
        return btnCategory && btnCategory.toLowerCase() === category.toLowerCase();
    });
    
    console.log(`✓ Found ${activeButtons.length} button(s) to activate`);
    
    activeButtons.forEach(btn => {
        btn.classList.add('active', 'bg-primary', 'text-white', 'shadow-button');
        btn.classList.remove(
            'bg-gray-100', 'dark:bg-gray-700', 
            'bg-gray-50', 'dark:bg-gray-800',
            'text-text-light', 'dark:text-text-dark',
            'hover:bg-primary', 'hover:text-white'
        );
    });
    
    // Sync dropdown value
    if (Elements.dropdown && Elements.dropdown.value !== category) {
        Elements.dropdown.value = category;
    }
}

/**
 * Load articles via AJAX
 */
async function loadArticles(category = 'all', page = 1) {
    if (FilterState.isLoading) {
        console.log('⏳ Already loading, skipping request');
        return;
    }
    
    console.log(`📡 Loading articles: category="${category}", page=${page}`);
    
    FilterState.isLoading = true;
    FilterState.currentPage = page;
    
    // Show loading state
    showLoading();
    
    try {
        // Build URL with proper encoding
        const url = `?ajax=filter&category=${encodeURIComponent(category)}&page=${page}`;
        console.log('🌐 Fetching:', url);
        
        // Fetch data
        const response = await fetch(url);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Server returned non-JSON response');
        }
        
        const data = await response.json();
        console.log('📦 Received data:', {
            success: data.success,
            articleCount: data.articles?.length || 0,
            total: data.total,
            page: data.page,
            totalPages: data.totalPages,
            category: data.category
        });
        
        // Handle response
        if (data.success) {
            if (data.articles && data.articles.length > 0) {
                renderArticles(data.articles);
                updateCategoryInfo(category, data.total);
                renderPagination(data.page, data.totalPages, category);
                FilterState.totalPages = data.totalPages;
                
                // Scroll to articles (only if not first page)
                if (page > 1 && Elements.articlesSection) {
                    setTimeout(() => {
                        Elements.articlesSection.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }, 100);
                }
                
                console.log('✅ Articles rendered successfully');
            } else {
                showEmptyState(category);
                console.log('📭 No articles found for this category');
            }
        } else {
            throw new Error(data.error || 'Unknown error occurred');
        }
        
    } catch (error) {
        console.error('❌ Error loading articles:', error);
        showError(error.message);
    } finally {
        hideLoading();
        FilterState.isLoading = false;
    }
}

/**
 * Show loading state
 */
function showLoading() {
    if (Elements.loadingIndicator) {
        Elements.loadingIndicator.classList.remove('hidden');
    }
    if (Elements.articlesGrid) {
        Elements.articlesGrid.classList.add('hidden');
    }
    if (Elements.emptyState) {
        Elements.emptyState.classList.add('hidden');
    }
    if (Elements.paginationContainer) {
        Elements.paginationContainer.innerHTML = '';
    }
}

/**
 * Hide loading state
 */
function hideLoading() {
    if (Elements.loadingIndicator) {
        Elements.loadingIndicator.classList.add('hidden');
    }
}

/**
 * Render articles to grid
 */
function renderArticles(articles) {
    if (!Elements.articlesGrid) {
        console.error('❌ articlesGrid element not found');
        return;
    }
    
    if (!Array.isArray(articles)) {
        console.error('❌ articles is not an array:', typeof articles);
        return;
    }
    
    console.log(`📝 Rendering ${articles.length} articles`);
    
    Elements.articlesGrid.innerHTML = articles.map(article => renderArticleCard(article)).join('');
    Elements.articlesGrid.classList.remove('hidden');
    
    if (Elements.emptyState) {
        Elements.emptyState.classList.add('hidden');
    }
}

/**
 * Render single article card
 */
function renderArticleCard(article) {
    if (!article) {
        console.warn('⚠️ Received null/undefined article');
        return '';
    }
    
    const thumb = article.thumbnail 
        ? `../${escapeHtml(article.thumbnail)}` 
        : 'https://via.placeholder.com/400x300?text=No+Image';
    
    const excerpt = getExcerpt(article.content, 120);
    
    const categoryBadge = article.category_name ? `
        <div class="absolute top-3 left-3 bg-primary text-white text-xs font-bold uppercase px-3 py-1.5 rounded-lg shadow-lg z-10">
            ${escapeHtml(article.category_name)}
        </div>
    ` : '';
    
    return `
        <article class="article-card bg-white dark:bg-surface-dark rounded-2xl overflow-hidden shadow-card hover:shadow-card-hover card-hover transition-all duration-300 fade-in">
            <a href="article.php?id=${article.id}" class="block">
                <div class="relative aspect-video image-overlay">
                    <img src="${thumb}" 
                         alt="${escapeHtml(article.title || 'News Article')}" 
                         class="w-full h-full object-cover"
                         loading="lazy"
                         onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    ${categoryBadge}
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-2 text-xs text-text-muted-light dark:text-text-muted-dark mb-3">
                        <span class="material-symbols-outlined text-sm">schedule</span>
                        <span>${timeAgo(article.published_at)}</span>
                    </div>
                    <h3 class="text-lg font-bold text-text-light dark:text-text-dark mb-2 leading-tight line-clamp-2 hover:text-primary transition-colors">
                        ${escapeHtml(article.title || 'Untitled')}
                    </h3>
                    <p class="text-sm text-text-muted-light dark:text-text-muted-dark line-clamp-3">
                        ${escapeHtml(excerpt)}
                    </p>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="text-sm font-semibold text-primary hover:underline">
                            Read more →
                        </span>
                    </div>
                </div>
            </a>
        </article>
    `;
}

/**
 * Update category title and count
 */
function updateCategoryInfo(category, total) {
    if (Elements.categoryTitle) {
        const titleText = category === 'all' 
            ? 'Latest News' 
            : `${capitalizeFirst(category)} News`;
        Elements.categoryTitle.textContent = titleText;
    }
    
    if (Elements.articleCount) {
        const count = parseInt(total) || 0;
        Elements.articleCount.textContent = `${count} article${count !== 1 ? 's' : ''}`;
    }
}

/**
 * Show empty state
 */
function showEmptyState(category) {
    if (Elements.emptyState) {
        Elements.emptyState.classList.remove('hidden');
    }
    if (Elements.articlesGrid) {
        Elements.articlesGrid.classList.add('hidden');
    }
    if (Elements.paginationContainer) {
        Elements.paginationContainer.innerHTML = '';
    }
    
    updateCategoryInfo(category, 0);
    
    console.log('📭 Showing empty state for category:', category);
}

/**
 * Show error state
 */
function showError(message) {
    console.error('💥 Showing error:', message);
    
    if (Elements.articlesGrid) {
        Elements.articlesGrid.classList.add('hidden');
    }
    
    if (Elements.emptyState) {
        Elements.emptyState.classList.remove('hidden');
        Elements.emptyState.innerHTML = `
            <div class="text-center py-12">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-red-100 dark:bg-red-900/20 mb-4">
                    <span class="material-symbols-outlined text-4xl text-red-600">error</span>
                </div>
                <h3 class="text-xl font-bold text-text-light dark:text-text-dark mb-2">Error Loading Articles</h3>
                <p class="text-text-muted-light dark:text-text-muted-dark mb-4">${escapeHtml(message || 'An unexpected error occurred')}</p>
                <button onclick="location.reload()" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors font-semibold">
                    Reload Page
                </button>
            </div>
        `;
    }
    
    if (Elements.paginationContainer) {
        Elements.paginationContainer.innerHTML = '';
    }
}

/**
 * Render pagination controls
 */
function renderPagination(currentPage, totalPages, category) {
    if (!Elements.paginationContainer) return;
    
    if (totalPages <= 1) {
        Elements.paginationContainer.innerHTML = '';
        return;
    }
    
    console.log(`📄 Rendering pagination: page ${currentPage} of ${totalPages}`);
    
    let html = '';
    
    // Previous button
    const prevDisabled = currentPage === 1;
    html += `
        <button data-page="${currentPage - 1}" 
                class="pagination-btn px-4 py-2 rounded-lg bg-white dark:bg-surface-dark text-text-light dark:text-text-dark border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors ${prevDisabled ? 'opacity-50 cursor-not-allowed' : ''}"
                ${prevDisabled ? 'disabled' : ''}>
            <span class="material-symbols-outlined text-sm">chevron_left</span>
        </button>
    `;
    
    // Page numbers with smart display
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, currentPage + 2);
    
    // First page + ellipsis
    if (startPage > 1) {
        html += `<button data-page="1" class="pagination-btn px-4 py-2 rounded-lg bg-white dark:bg-surface-dark text-text-light dark:text-text-dark border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors font-semibold">1</button>`;
        if (startPage > 2) {
            html += `<span class="px-2 text-text-muted-light dark:text-text-muted-dark">...</span>`;
        }
    }
    
    // Visible page range
    for (let i = startPage; i <= endPage; i++) {
        const isActive = i === currentPage;
        html += `
            <button data-page="${i}" 
                    class="pagination-btn px-4 py-2 rounded-lg font-semibold ${isActive ? 'bg-primary text-white shadow-button' : 'bg-white dark:bg-surface-dark text-text-light dark:text-text-dark border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800'} transition-all">
                ${i}
            </button>
        `;
    }
    
    // Ellipsis + last page
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += `<span class="px-2 text-text-muted-light dark:text-text-muted-dark">...</span>`;
        }
        html += `<button data-page="${totalPages}" class="pagination-btn px-4 py-2 rounded-lg bg-white dark:bg-surface-dark text-text-light dark:text-text-dark border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors font-semibold">${totalPages}</button>`;
    }
    
    // Next button
    const nextDisabled = currentPage === totalPages;
    html += `
        <button data-page="${currentPage + 1}" 
                class="pagination-btn px-4 py-2 rounded-lg bg-white dark:bg-surface-dark text-text-light dark:text-text-dark border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors ${nextDisabled ? 'opacity-50 cursor-not-allowed' : ''}"
                ${nextDisabled ? 'disabled' : ''}>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
        </button>
    `;
    
    Elements.paginationContainer.innerHTML = html;
    
    // Attach event listeners using delegation
    Elements.paginationContainer.addEventListener('click', (e) => {
        const button = e.target.closest('.pagination-btn');
        if (button && !button.disabled) {
            const page = parseInt(button.getAttribute('data-page'));
            if (page > 0 && page <= totalPages && page !== currentPage) {
                console.log(`📄 Pagination: Going to page ${page}`);
                loadArticles(category, page);
            }
        }
    }, { once: true }); // Use once to prevent duplicate listeners
}

/**
 * Helper: Format time ago
 */
function timeAgo(timestamp) {
    if (!timestamp) return 'Just now';
    
    try {
        const time = new Date(timestamp).getTime();
        const diff = Date.now() - time;
        
        if (isNaN(diff) || diff < 0) return 'Just now';
        
        if (diff < 60000) return 'Just now';
        if (diff < 3600000) {
            const minutes = Math.floor(diff / 60000);
            return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago';
        }
        if (diff < 86400000) {
            const hours = Math.floor(diff / 3600000);
            return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
        }
        if (diff < 604800000) {
            const days = Math.floor(diff / 86400000);
            return days + ' day' + (days > 1 ? 's' : '') + ' ago';
        }
        
        const date = new Date(time);
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
    } catch (error) {
        console.error('Error formatting timestamp:', error);
        return 'Recently';
    }
}

/**
 * Helper: Get excerpt
 */
function getExcerpt(content, length = 120) {
    if (!content) return '';
    try {
        const text = content.replace(/<[^>]*>/g, '').trim();
        if (text.length <= length) return text;
        return text.substring(0, length).trim() + '...';
    } catch (error) {
        console.error('Error creating excerpt:', error);
        return '';
    }
}

/**
 * Helper: Capitalize first letter
 */
function capitalizeFirst(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

/**
 * Helper: Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Expose for debugging in console
window.FilterDebug = {
    state: FilterState,
    elements: Elements,
    loadArticles: loadArticles,
    updateUIState: updateUIState
};

console.log('✅ Category Filter System loaded successfully!');
console.log('🐛 Debug interface available at: window.FilterDebug');