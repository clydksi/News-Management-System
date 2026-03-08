// DZRH News - Enhanced Cross-Browser Compatible JavaScript
// ============================================================================

// Philippine Date & Time Clock
function updatePhilippineDateTime() {
    const now = new Date();
    
    // Format time
    const phTime = now.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
    
    // Format date
    const phDate = now.toLocaleString('en-US', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
    
    // Update time element
    const phTimeElement = document.getElementById('phTime');
    if (phTimeElement) {
        phTimeElement.textContent = phTime + ' PHT';
    }
    
    // Update date element
    const phDateElement = document.getElementById('phDate');
    if (phDateElement) {
        phDateElement.textContent = phDate;
    }
}

// Initialize and update every second
updatePhilippineDateTime();
setInterval(updatePhilippineDateTime, 1000);

// ============================================================================
// YOUTUBE PLAYER MANAGEMENT - CROSS-BROWSER COMPATIBLE
// ============================================================================

// YouTube Player Configuration
const YouTubePlayerConfig = {
    // Your YouTube Live Stream Video ID
    videoId: 'Y4Q6DwEwQ5Q',
    
    // Build embed URL with parameters
    getEmbedUrl: function(autoplay = true, mute = false) {
        const params = new URLSearchParams({
            autoplay: autoplay ? '1' : '0',
            mute: mute ? '1' : '0',
            enablejsapi: '1',
            origin: window.location.origin,
            widget_referrer: window.location.href,
            rel: '0',
            modestbranding: '1',
            playsinline: '1'
        });
        return `https://www.youtube.com/embed/${this.videoId}?${params.toString()}`;
    }
};

// Safe element getter with null checks
function safeGetElement(id) {
    const element = document.getElementById(id);
    if (!element) {
        console.warn(`Element with id '${id}' not found`);
    }
    return element;
}

// Initialize YouTube Player Elements with null safety
const ytElements = {
    toggle: safeGetElement('ytToggle'),
    miniPlayer: safeGetElement('ytMiniPlayer'),
    modal: safeGetElement('ytModal'),
    minimize: safeGetElement('ytMinimize'),
    expand: safeGetElement('ytExpand'),
    modalClose: safeGetElement('ytModalClose'),
    pip: safeGetElement('ytPip'),
    backdrop: safeGetElement('ytBackdrop'),
    mainFrame: safeGetElement('ytMainFrame'),
    miniFrame: safeGetElement('ytMiniFrame')
};

// Player state management
let playerState = 'hidden'; // 'hidden', 'mini', 'modal'

// Device detection for responsive behavior
const deviceInfo = {
    isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
    isIOS: /iPad|iPhone|iPod/.test(navigator.userAgent),
    isAndroid: /Android/.test(navigator.userAgent),
    isSafari: /^((?!chrome|android).)*safari/i.test(navigator.userAgent),
    isFirefox: /Firefox/i.test(navigator.userAgent),
    isChrome: /Chrome/i.test(navigator.userAgent) && !/Edge/i.test(navigator.userAgent)
};

// Cross-browser safe iframe src setter
function setIframeSrc(iframe, url) {
    if (!iframe) return false;
    
    try {
        // Clear existing src first
        if (iframe.src) {
            iframe.src = '';
        }
        
        // Small delay for browser to process
        setTimeout(() => {
            iframe.src = url;
            
            // Force reload on some browsers
            if (deviceInfo.isSafari || deviceInfo.isFirefox) {
                try {
                    iframe.contentWindow?.location.replace(url);
                } catch (e) {
                    // Ignore cross-origin errors
                }
            }
        }, 50);
        
        return true;
    } catch (error) {
        console.error('Error setting iframe src:', error);
        return false;
    }
}

// Clear iframe content safely
function clearIframe(iframe) {
    if (!iframe) return;
    
    try {
        iframe.src = '';
        // Additional cleanup for certain browsers
        if (deviceInfo.isSafari) {
            iframe.src = 'about:blank';
        }
    } catch (error) {
        console.error('Error clearing iframe:', error);
    }
}

// Show mini player
function showMiniPlayer() {
    if (!ytElements.toggle || !ytElements.miniPlayer || !ytElements.miniFrame) {
        console.error('Required elements not found for mini player');
        return;
    }
    
    try {
        ytElements.toggle.classList.add('hidden');
        ytElements.miniPlayer.classList.remove('hidden');
        
        // Set video URL with autoplay
        const videoUrl = YouTubePlayerConfig.getEmbedUrl(true, false);
        setIframeSrc(ytElements.miniFrame, videoUrl);
        
        playerState = 'mini';
        
        // Mobile-specific adjustments
        if (deviceInfo.isMobile && window.innerWidth < 640) {
            ytElements.miniPlayer.classList.add('bottom-0', 'left-0', 'right-0');
            ytElements.miniPlayer.classList.remove('sm:absolute');
        }
        
        console.log('Mini player opened successfully');
    } catch (error) {
        console.error('Error showing mini player:', error);
        showToast('Could not open video player', 'error');
    }
}

// Hide mini player
function hideMiniPlayer() {
    if (!ytElements.miniPlayer || !ytElements.toggle || !ytElements.miniFrame) return;
    
    try {
        ytElements.miniPlayer.classList.add('hidden');
        ytElements.toggle.classList.remove('hidden');
        clearIframe(ytElements.miniFrame);
        playerState = 'hidden';
        
        console.log('Mini player closed');
    } catch (error) {
        console.error('Error hiding mini player:', error);
    }
}

// Show modal player
function showModalPlayer() {
    if (!ytElements.modal || !ytElements.mainFrame) {
        console.error('Required elements not found for modal player');
        return;
    }
    
    try {
        // Hide mini player if open
        if (ytElements.miniPlayer && !ytElements.miniPlayer.classList.contains('hidden')) {
            ytElements.miniPlayer.classList.add('hidden');
            clearIframe(ytElements.miniFrame);
        }
        
        // Show modal
        ytElements.modal.classList.remove('hidden');
        
        // Set video URL
        const videoUrl = YouTubePlayerConfig.getEmbedUrl(true, false);
        setIframeSrc(ytElements.mainFrame, videoUrl);
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Try to lock orientation on mobile (may not work in all browsers)
        if (deviceInfo.isMobile && screen.orientation?.lock) {
            screen.orientation.lock('landscape').catch(err => {
                console.log('Orientation lock not available:', err);
            });
        }
        
        playerState = 'modal';
        console.log('Modal player opened successfully');
    } catch (error) {
        console.error('Error showing modal player:', error);
        showToast('Could not open full screen player', 'error');
    }
}

// Close modal player
function closeModalPlayer() {
    if (!ytElements.modal) return;
    
    try {
        ytElements.modal.classList.add('hidden');
        
        if (ytElements.toggle) {
            ytElements.toggle.classList.remove('hidden');
        }
        
        clearIframe(ytElements.mainFrame);
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Unlock orientation
        if (deviceInfo.isMobile && screen.orientation?.unlock) {
            screen.orientation.unlock();
        }
        
        playerState = 'hidden';
        console.log('Modal player closed');
    } catch (error) {
        console.error('Error closing modal player:', error);
    }
}

// Switch from modal to mini player (Picture-in-Picture style)
function switchToMiniPlayer() {
    if (!ytElements.modal || !ytElements.miniPlayer) return;
    
    try {
        ytElements.modal.classList.add('hidden');
        ytElements.miniPlayer.classList.remove('hidden');
        
        // Transfer video to mini player
        const videoUrl = YouTubePlayerConfig.getEmbedUrl(true, false);
        setIframeSrc(ytElements.miniFrame, videoUrl);
        clearIframe(ytElements.mainFrame);
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Unlock orientation
        if (deviceInfo.isMobile && screen.orientation?.unlock) {
            screen.orientation.unlock();
        }
        
        playerState = 'mini';
        console.log('Switched to mini player');
    } catch (error) {
        console.error('Error switching to mini player:', error);
    }
}

// Event Listeners with null checks
if (ytElements.toggle) {
    ytElements.toggle.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        
        if (playerState === 'hidden') {
            showMiniPlayer();
        }
    });
}

if (ytElements.minimize) {
    ytElements.minimize.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        hideMiniPlayer();
    });
}

if (ytElements.expand) {
    ytElements.expand.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        showModalPlayer();
    });
}

if (ytElements.modalClose) {
    ytElements.modalClose.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeModalPlayer();
    });
}

if (ytElements.pip) {
    ytElements.pip.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        switchToMiniPlayer();
    });
}

if (ytElements.backdrop) {
    ytElements.backdrop.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        closeModalPlayer();
    });
}

// Keyboard controls
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && playerState === 'modal') {
        closeModalPlayer();
    }
});

// Responsive handling for window resize
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (playerState === 'mini' && ytElements.miniPlayer) {
            if (window.innerWidth < 640) {
                ytElements.miniPlayer.classList.add('bottom-0', 'left-0', 'right-0');
                ytElements.miniPlayer.classList.remove('sm:absolute');
            } else {
                ytElements.miniPlayer.classList.remove('bottom-0', 'left-0', 'right-0');
                ytElements.miniPlayer.classList.add('sm:absolute');
            }
        }
    }, 250);
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (ytElements.mainFrame) clearIframe(ytElements.mainFrame);
    if (ytElements.miniFrame) clearIframe(ytElements.miniFrame);
});

// Log initialization
console.log('YouTube Player initialized:', {
    playerState,
    deviceInfo,
    elementsFound: Object.entries(ytElements).filter(([, el]) => el !== null).length
});

// ============================================================================
// HERO CAROUSEL FUNCTIONALITY
// ============================================================================

let currentSlide = 0;
const slides = document.querySelectorAll('.carousel-slide');
const indicators = document.querySelectorAll('.carousel-indicator');
const totalSlides = slides.length;
let autoPlayInterval;
let isCarouselFocused = false;

function showSlide(index) {
    slides.forEach((slide, i) => {
        if (i === index) {
            slide.classList.remove('opacity-0');
            slide.classList.add('opacity-100');
            slide.setAttribute('aria-hidden', 'false');
        } else {
            slide.classList.add('opacity-0');
            slide.classList.remove('opacity-100');
            slide.setAttribute('aria-hidden', 'true');
        }
    });
    
    indicators.forEach((indicator, i) => {
        if (i === index) {
            indicator.classList.add('bg-primary', 'w-8');
            indicator.classList.remove('bg-white/50', 'w-2');
            indicator.setAttribute('aria-current', 'true');
        } else {
            indicator.classList.remove('bg-primary', 'w-8');
            indicator.classList.add('bg-white/50', 'w-2');
            indicator.setAttribute('aria-current', 'false');
        }
    });
    
    currentSlide = index;
}

function nextSlide() {
    let next = (currentSlide + 1) % totalSlides;
    showSlide(next);
}

function prevSlide() {
    let prev = (currentSlide - 1 + totalSlides) % totalSlides;
    showSlide(prev);
}

function startAutoPlay() {
    if (totalSlides > 1 && !isCarouselFocused) {
        autoPlayInterval = setInterval(nextSlide, 5000);
    }
}

function stopAutoPlay() {
    if (autoPlayInterval) {
        clearInterval(autoPlayInterval);
        autoPlayInterval = null;
    }
}

const nextButton = document.getElementById('nextSlide');
const prevButton = document.getElementById('prevSlide');
const carousel = document.getElementById('heroCarousel');

if (nextButton) {
    nextButton.addEventListener('click', () => {
        stopAutoPlay();
        nextSlide();
        startAutoPlay();
    });
}

if (prevButton) {
    prevButton.addEventListener('click', () => {
        stopAutoPlay();
        prevSlide();
        startAutoPlay();
    });
}

indicators.forEach((indicator, index) => {
    indicator.addEventListener('click', () => {
        stopAutoPlay();
        showSlide(index);
        startAutoPlay();
    });
});

if (carousel) {
    carousel.addEventListener('mouseenter', () => stopAutoPlay());
    carousel.addEventListener('mouseleave', () => {
        if (!isCarouselFocused) startAutoPlay();
    });
}

if (totalSlides > 0) {
    showSlide(0);
    startAutoPlay();
}

// ============================================================================
// DARK MODE TOGGLE
// ============================================================================

const darkModeToggle = document.getElementById('darkModeToggle');
const html = document.documentElement;

const currentTheme = localStorage.getItem('theme') || 'light';
html.classList.toggle('dark', currentTheme === 'dark');

if (darkModeToggle) {
    darkModeToggle.addEventListener('click', () => {
        html.classList.toggle('dark');
        const newTheme = html.classList.contains('dark') ? 'dark' : 'light';
        localStorage.setItem('theme', newTheme);
    });
}

// ============================================================================
// MOBILE MENU TOGGLE
// ============================================================================

const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const mobileMenu = document.getElementById('mobileMenu');

if (mobileMenuToggle && mobileMenu) {
    mobileMenuToggle.addEventListener('click', () => {
        mobileMenu.classList.toggle('hidden');
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!mobileMenu.classList.contains('hidden') && 
            !mobileMenuToggle.contains(e.target) && !mobileMenu.contains(e.target)) {
            mobileMenu.classList.add('hidden');
        }
    });
}

// ============================================================================
// SEARCH FUNCTIONALITY
// ============================================================================

const searchInput = document.getElementById('searchInput');

if (searchInput) {
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase();
        if (query.length > 2) {
            console.log('Searching for:', query);
        }
    });
}

// ============================================================================
// NOTIFICATION SYSTEM
// ============================================================================

const notificationBtn = document.getElementById('notificationBtn');
let notificationPanel = null;

if (notificationBtn) {
    notificationBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        
        if (!notificationPanel) {
            notificationPanel = document.createElement('div');
            notificationPanel.className = 'absolute top-full right-0 mt-2 w-80 bg-white dark:bg-surface-dark rounded-xl shadow-card-hover border border-gray-200 dark:border-gray-700 overflow-hidden z-50';
            notificationPanel.innerHTML = `
                <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-lg">Notifications</h3>
                        <button class="text-xs text-primary font-semibold">Mark all read</button>
                    </div>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <div class="p-4 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors border-b border-gray-100 dark:border-gray-700">
                        <div class="flex gap-3">
                            <div class="w-2 h-2 bg-primary rounded-full mt-1 flex-shrink-0"></div>
                            <div class="flex-1">
                                <p class="text-sm font-semibold">Breaking: New Political Development</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">2 minutes ago</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            notificationBtn.parentElement.appendChild(notificationPanel);
        } else {
            notificationPanel.classList.toggle('hidden');
        }
    });

    document.addEventListener('click', (e) => {
        if (notificationPanel && !notificationBtn.contains(e.target) && !notificationPanel.contains(e.target)) {
            notificationPanel.classList.add('hidden');
        }
    });
}

// ============================================================================
// VIEW MODE TOGGLE
// ============================================================================

const viewModeGrid = document.getElementById('viewModeGrid');
const viewModeList = document.getElementById('viewModeList');
const articlesContainer = document.getElementById('articlesContainer');

if (viewModeGrid && viewModeList && articlesContainer) {
    viewModeGrid.addEventListener('click', () => {
        viewModeGrid.classList.add('bg-primary', 'text-white');
        viewModeList.classList.remove('bg-primary', 'text-white');
        articlesContainer.className = 'grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-5';
    });

    viewModeList.addEventListener('click', () => {
        viewModeList.classList.add('bg-primary', 'text-white');
        viewModeGrid.classList.remove('bg-primary', 'text-white');
        articlesContainer.className = 'flex flex-col gap-4';
    });
}

// ============================================================================
// NEWSLETTER FORM
// ============================================================================

const newsletterForm = document.getElementById('newsletterForm');
const newsletterEmail = document.getElementById('newsletterEmail');

if (newsletterForm) {
    newsletterForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const email = newsletterEmail.value;
        if (email) {
            showToast('Thank you for subscribing!', 'success');
            newsletterEmail.value = '';
        }
    });
}

// ============================================================================
// TOAST NOTIFICATION SYSTEM
// ============================================================================

function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-green-600' : type === 'error' ? 'bg-red-600' : 'bg-primary';
    
    toast.className = `fixed bottom-20 right-5 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-[300] flex items-center gap-2 transform translate-x-0 opacity-100 transition-all duration-300`;
    toast.innerHTML = `
        <span class="material-symbols-outlined text-lg">${type === 'success' ? 'check_circle' : type === 'error' ? 'error' : 'info'}</span>
        <span class="text-sm font-semibold">${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(400px)';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================================================
// SCROLL TO TOP
// ============================================================================

const scrollToTopBtn = document.getElementById('scrollToTop');

if (scrollToTopBtn) {
    window.addEventListener('scroll', () => {
        if (window.pageYOffset > 300) {
            scrollToTopBtn.classList.remove('hidden');
            scrollToTopBtn.classList.add('flex');
        } else {
            scrollToTopBtn.classList.add('hidden');
            scrollToTopBtn.classList.remove('flex');
        }
    });

    scrollToTopBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// ============================================================================
// RADIO PLAYER
// ============================================================================

const audio = document.getElementById("radioPlayer");
const playBtn = document.getElementById("playBtn");
const playIcon = document.getElementById("playIcon");
const progressBar = document.getElementById("progressBar");
const currentTime = document.getElementById("currentTime");
const muteBtn = document.getElementById("muteBtn");
const volumeIcon = document.getElementById("volumeIcon");

if (playBtn && audio) {
    playBtn.addEventListener("click", () => {
        if (audio.paused) {
            audio.play().then(() => {
                playIcon.textContent = "pause";
                showToast('Radio playing', 'success');
            }).catch(err => {
                console.error('Playback error:', err);
                showToast('Could not play radio', 'error');
            });
        } else {
            audio.pause();
            playIcon.textContent = "play_arrow";
        }
    });
}

if (audio && currentTime && progressBar) {
    audio.addEventListener("timeupdate", () => {
        progressBar.value = (audio.currentTime % 100);
        currentTime.textContent = formatTime(audio.currentTime);
    });
}

function formatTime(sec) {
    let m = Math.floor(sec / 60);
    let s = Math.floor(sec % 60);
    return m + ":" + (s < 10 ? "0" + s : s);
}

if (muteBtn && audio && volumeIcon) {
    muteBtn.addEventListener("click", () => {
        audio.muted = !audio.muted;
        volumeIcon.textContent = audio.muted ? "volume_off" : "volume_up";
    });
}

// ============================================================================
// WEATHER FUNCTIONS
// ============================================================================

function refreshWeather() {
    const weatherWidget = document.getElementById('weatherWidget');
    if (!weatherWidget) return;
    
    const refreshBtn = weatherWidget.querySelector('button');
    const icon = refreshBtn?.querySelector('.material-symbols-outlined');
    
    if (icon) {
        icon.style.animation = 'spin-slow 1s linear';
        setTimeout(() => {
            icon.style.animation = '';
            showToast('Weather updated!', 'success');
        }, 1000);
    }
}

// ============================================================================
// SMOOTH SCROLLING
// ============================================================================

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (href !== '#') {
            e.preventDefault();
            const target = document.querySelector(href);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    });
});

// ============================================================================
// FADE-IN ANIMATIONS
// ============================================================================

const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.card-hover').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
    observer.observe(card);
});

console.log('DZRH News - All systems loaded successfully!');