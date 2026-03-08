<script>
// Global configuration
const Config = {
    category: '<?= Helper::e($category) ?>',
    articlesData: <?= json_encode($paginatedArticles) ?>,
    baseParams: <?= json_encode($baseParams) ?>
};

// Toast notification system
const Toast = {
    container: null,
    
    init() {
        this.container = document.getElementById('toastContainer');
    },
    
    show(message, type = 'info') {
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500',
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        
        const icons = {
            success: 'check_circle',
            error: 'error',
            warning: 'warning',
            info: 'info'
        };
        
        const toast = document.createElement('div');
        toast.className = `${colors[type]} text-white px-6 py-4 rounded-lg shadow-lg flex items-center space-x-3 slide-in min-w-[300px]`;
        toast.innerHTML = `
            <span class="material-icons">${icons[type]}</span>
            <span class="flex-1">${message}</span>
            <button onclick="this.parentElement.remove()" class="text-white hover:text-gray-200">
                <span class="material-icons text-sm">close</span>
            </button>
        `;
        
        this.container.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
};

// Article management
const ArticleManager = {
    saveArticle(article, btnElement, index) {
        const originalContent = btnElement.innerHTML;
        
        btnElement.disabled = true;
        btnElement.innerHTML = '<span class="material-icons text-sm mr-1 animate-spin">refresh</span>Importing...';
        
        fetch('news_import/save_article.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: article.title,
                content: article.description || article.content || article.title,
                category: Config.category,
                source: article.source?.name || 'NewsAPI',
                author: article.author,
                published_at: article.publishedAt,
                url: article.url,
                image: article.urlToImage
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Toast.show(data.message, 'success');
                btnElement.innerHTML = '<span class="material-icons text-sm mr-1">check_circle</span>Imported';
                btnElement.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
                btnElement.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                
                const card = document.querySelector(`[data-article-id="${index}"]`);
                if (card) {
                    card.classList.add('ring-2', 'ring-green-500');
                    setTimeout(() => card.classList.remove('ring-2', 'ring-green-500'), 2000);
                }
            } else {
                Toast.show(data.message, 'error');
                btnElement.disabled = false;
                btnElement.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Toast.show('Failed to import article', 'error');
            btnElement.disabled = false;
            btnElement.innerHTML = originalContent;
        });
    },
    
    saveAllArticles() {
        if (Config.articlesData.length === 0) {
            Toast.show('No articles to import', 'warning');
            return;
        }

        if (!confirm(`Import all ${Config.articlesData.length} articles to your database?`)) {
            return;
        }

        let saved = 0, failed = 0, exists = 0;
        Toast.show(`Importing ${Config.articlesData.length} articles...`, 'info');
        
        const buttons = document.querySelectorAll('.save-article-btn');
        buttons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<span class="material-icons text-sm mr-1 animate-spin">refresh</span>Wait...';
        });

        Config.articlesData.forEach((article, index) => {
            setTimeout(() => {
                fetch('news_import/save_article.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        title: article.title,
                        content: article.description || article.content || article.title,
                        category: Config.category,
                        source: article.source?.name || 'NewsAPI',
                        author: article.author,
                        published_at: article.publishedAt,
                        url: article.url,
                        image: article.urlToImage
                    })
                })
                .then(response => response.json())
                .then(data => {
                    const btn = buttons[index];
                    if (data.success) {
                        saved++;
                        btn.innerHTML = '<span class="material-icons text-sm mr-1">check_circle</span>Imported';
                        btn.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
                        btn.classList.add('bg-gray-100', 'text-gray-500', 'cursor-not-allowed');
                    } else {
                        if (data.message.includes('already exists')) {
                            exists++;
                            btn.innerHTML = '<span class="material-icons text-sm mr-1">info</span>Exists';
                            btn.classList.remove('bg-green-50', 'hover:bg-green-100', 'text-green-600');
                            btn.classList.add('bg-yellow-50', 'text-yellow-600', 'cursor-not-allowed');
                        } else {
                            failed++;
                            btn.disabled = false;
                            btn.innerHTML = '<span class="material-icons text-sm mr-1">cloud_download</span>Import';
                        }
                    }

                    if (saved + failed + exists === Config.articlesData.length) {
                        let message = `✓ ${saved} imported`;
                        if (exists > 0) message += `, ${exists} already existed`;
                        if (failed > 0) message += `, ${failed} failed`;
                        Toast.show(message, failed === 0 ? 'success' : 'warning');
                    }
                })
                .catch(() => {
                    failed++;
                    buttons[index].disabled = false;
                    buttons[index].innerHTML = '<span class="material-icons text-sm mr-1">cloud_download</span>Import';
                });
            }, index * 200);
        });
    }
};

// UI utilities
function changeItemsPerPage(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('itemsPerPage', value);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

function refreshData() {
    Toast.show('Fetching fresh data from API...', 'info');
    const url = new URL(window.location.href);
    url.searchParams.set('refresh', '1');
    setTimeout(() => window.location.href = url.toString(), 500);
}

function toggleFilters() {
    document.getElementById('categoryFilters').classList.toggle('hidden');
}

function saveAllVisibleArticles() {
    ArticleManager.saveAllArticles();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize toast system
    Toast.init();
    
    // Attach save article event listeners
    const saveButtons = document.querySelectorAll('.save-article-btn');
    saveButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const article = JSON.parse(this.getAttribute('data-article'));
            const index = this.getAttribute('data-index');
            ArticleManager.saveArticle(article, this, index);
        });
    });
    
    // Scroll to top button
    const scrollBtn = document.getElementById('scrollTopBtn');
    if (scrollBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollBtn.classList.remove('hidden');
            } else {
                scrollBtn.classList.add('hidden');
            }
        });
    }
});
</script>