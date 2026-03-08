<?php
require dirname(__DIR__, 2) . '/db.php';

$headline = $articles[0] ?? null;
?>
<!DOCTYPE html>

<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>All Programs - News Network</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Work+Sans:wght@400;500;600;700;800;900&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet"/>
    <style type="text/tailwindcss">
        @layer utilities {
            .text-shadow {
                text-shadow: 0 2px 4px rgba(0,0,0,0.3);
            }
            .text-shadow-lg {
                text-shadow: 0 4px 8px rgba(0,0,0,0.5);
            }
            .card-hover {
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .card-hover:hover {
                transform: translateY(-4px);
            }
            .image-overlay {
                position: relative;
                overflow: hidden;
            }
            .image-overlay::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(0deg, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0) 60%);
                transition: opacity 0.3s ease;
            }
            .image-overlay:hover::after {
                opacity: 0.8;
            }
            
            html {
                scroll-behavior: smooth;
            }
            
            @keyframes shimmer {
                0% { background-position: -1000px 0; }
                100% { background-position: 1000px 0; }
            }
            
            .skeleton {
                animation: shimmer 2s infinite linear;
                background: linear-gradient(to right, #f0f0f0 4%, #e0e0e0 25%, #f0f0f0 36%);
                background-size: 1000px 100%;
            }
            
            @keyframes ticker {
                0% { transform: translateX(100%); }
                100% { transform: translateX(-100%); }
            }
            
            .ticker-content {
                animation: ticker 30s linear infinite;
            }
            
            @keyframes pulse-ring {
                0% { transform: scale(0.95); opacity: 1; }
                50% { transform: scale(1.05); opacity: 0.7; }
                100% { transform: scale(0.95); opacity: 1; }
            }
            
            .pulse-ring {
                animation: pulse-ring 2s ease-in-out infinite;
            }
            
            @keyframes fadeIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            
            .fade-in {
                animation: fadeIn 0.6s ease-out forwards;
            }
            
            .stagger-1 { animation-delay: 0.1s; }
            .stagger-2 { animation-delay: 0.2s; }
            .stagger-3 { animation-delay: 0.3s; }
            .stagger-4 { animation-delay: 0.4s; }

            /* Floating animation for weather clouds - compact */
            @keyframes float {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-8px); }
            }
            
            @keyframes float-delayed {
                0%, 100% { transform: translateY(0px); }
                50% { transform: translateY(-10px); }
            }
            
            .animate-float {
                animation: float 5s ease-in-out infinite;
            }
            
            .animate-float-delayed {
                animation: float-delayed 6s ease-in-out infinite;
                animation-delay: 0.8s;
            }
            
            /* Slow spin for sun icon */
            @keyframes spin-slow {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            
            .animate-spin-slow {
                animation: spin-slow 30s linear infinite;
            }
            
            /* Weather widget hover effect */
            #weatherWidget:hover .animate-float {
                animation-duration: 3s;
            }
            
            #weatherWidget:hover .animate-float-delayed {
                animation-duration: 4s;
            }

            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }

            
        }
    </style>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#2563eb",
                        "primary-dark": "#1e40af",
                        "accent-yellow": "#eab308",
                        "accent-yellow-dark": "#ca8a04",
                        "accent-red": "#dc2626",
                        "accent-red-dark": "#b91c1c",
                        "background-light": "#fafafa",
                        "background-dark": "#1a1a1a",
                        "surface-light": "#f5f5f5",
                        "surface-dark": "#2a2a2a",
                        "text-light": "#1a1a1a",
                        "text-dark": "#e0e0e0",
                        "text-muted-light": "#666666",
                        "text-muted-dark": "#a0a0a0",
                    },
                    fontFamily: {
                        "display": ["Work Sans", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.375rem",
                        "lg": "0.625rem",
                        "xl": "0.875rem",
                        "2xl": "1.25rem",
                        "full": "9999px"
                    },
                    boxShadow: {
                        'card': '0 2px 8px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.05)',
                        'card-hover': '0 12px 32px rgba(0, 0, 0, 0.18), 0 4px 8px rgba(0, 0, 0, 0.12)',
                        'button': '0 4px 12px rgba(37, 99, 235, 0.3)',
                        'button-hover': '0 6px 20px rgba(37, 99, 235, 0.4)',
                        'button-yellow': '0 4px 12px rgba(234, 179, 8, 0.3)',
                        'button-yellow-hover': '0 6px 20px rgba(234, 179, 8, 0.4)',
                        'button-red': '0 4px 12px rgba(220, 38, 38, 0.3)',
                        'button-red-hover': '0 6px 20px rgba(220, 38, 38, 0.4)',
                        'inner-light': 'inset 0 2px 4px rgba(0, 0, 0, 0.06)',
                        'feature': '0 4px 16px rgba(0, 0, 0, 0.12)',
                        'sidebar': '0 2px 12px rgba(0, 0, 0, 0.08)',
                    },
                },
            },
        }
    </script>
</head>
<body class="font-display bg-background-light dark:bg-background-dark text-[#1A1A1A] dark:text-[#F5F5F5]">
<div class="relative flex h-auto min-h-screen w-full flex-col group/design-root overflow-x-hidden">
<div class="layout-container flex h-full grow flex-col">
<div class="layout-content-container flex flex-col w-full">
                    
                    <!-- Breaking News Ticker with Philippine Time -->
                    <div class="bg-blue-800 text-black py-2.5 overflow-hidden">
                        <div class="max-w-[1600px] mx-auto px-8 flex items-center gap-4">
                            <span class="font-bold text-sm uppercase flex items-center gap-2 flex-shrink-0 bg-yellow-400 text-red-600 px-3 py-1 rounded-full shadow-md">
                                <span class="material-symbols-outlined text-lg pulse-ring text-red-600 p-1">campaign</span>
                                Breaking News
                            </span>
                            <div class="overflow-hidden flex-1">
                                <div class="ticker-content whitespace-nowrap">
                                    <?php if ($headline): ?>
                                        <span class="text-sm font-semibold text-white"><?= htmlspecialchars($headline['title']) ?></span>
                                        <span class="mx-4 text-white">|</span>
                                        <span class="text-sm font-medium text-white">Stay tuned for more updates</span>
                                    <?php else: ?>
                                        <span class="text-sm font-semibold text-white">Welcome to DZRH News - Your trusted source for breaking news</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 bg-white/90 px-3 py-1 rounded-sm">
                                <span class="material-symbols-outlined text-base">schedule</span>
                                <span id="phTime" class="text-sm font-bold whitespace-nowrap"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Enhanced TopNavBar -->
                    <header class="sticky top-0 z-40 border-b border-gray-200 dark:border-surface-dark bg-white/95 dark:bg-surface-dark/95 backdrop-blur-sm shadow-sm">
                        <div class="max-w-[1600px] mx-auto px-8 py-3 flex items-center justify-between whitespace-nowrap">
                            <div class="flex items-center gap-8">
                                <div class="flex items-center gap-3 text-text-light dark:text-text-dark">
                                    <a href="dzrh.php" class="inline-block">
                                        <img src="https://www.dzrh.com.ph/dzrh-logo.svg"
                                            alt="DZRH News"
                                            class="h-8 w-auto drop-shadow-md transition-transform hover:scale-110 cursor-pointer">
                                    </a>
                                </div>
                                <nav class="hidden lg:flex items-center gap-6">
                                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="news.php">
                                        News
                                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                                    </a>
                                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="programs.php">
                                        Programs
                                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                                    </a>
                                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="schedule.php">
                                        On-Air Schedule
                                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                                    </a>
                                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="about_us.php">
                                        About Us
                                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                                    </a>
                                    <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors relative group" href="contact.php">
                                        Contact
                                        <span class="absolute bottom-0 left-0 w-0 h-0.5 bg-primary transition-all group-hover:w-full"></span>
                                    </a>
                                </nav>
                            </div>
                            <div class="flex gap-4 items-center">
                                <label class="hidden md:flex flex-col min-w-40 !h-10 max-w-64">
                                    <div class="flex w-full flex-1 items-stretch rounded-xl h-full shadow-sm hover:shadow-md transition-shadow">
                                        <div class="text-text-muted-light dark:text-text-muted-dark flex bg-white dark:bg-surface-dark items-center justify-center pl-3 rounded-l-xl border border-r-0 border-gray-200">
                                            <span class="material-symbols-outlined text-xl">search</span>
                                        </div>
                                        <input id="searchInput" class="form-input flex w-full min-w-0 flex-1 resize-none overflow-hidden rounded-xl text-text-light dark:text-text-dark focus:outline-0 focus:ring-2 focus:ring-primary border border-l-0 border-gray-200 bg-white dark:bg-surface-dark focus:border-primary h-full placeholder:text-text-muted-light placeholder:dark:text-text-muted-dark px-4 rounded-l-none pl-2 text-sm font-normal leading-normal" placeholder="Search news...">
                                    </div>
                                </label>
                                
                                <button id="notificationBtn" class="relative flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                                    <span class="material-symbols-outlined">notifications</span>
                                    <span class="absolute top-1 right-1 w-2 h-2 bg-accent-red rounded-full"></span>
                                </button>
                                
                                <button id="darkModeToggle" class="flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                                    <span class="material-symbols-outlined dark:hidden">dark_mode</span>
                                    <span class="material-symbols-outlined hidden dark:inline">light_mode</span>
                                </button>
                                
                                <button id="mobileMenuToggle" class="lg:hidden flex items-center justify-center w-10 h-10 rounded-lg hover:bg-gray-100 dark:hover:bg-surface-dark transition-colors">
                                    <span class="material-symbols-outlined">menu</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Mobile Menu -->
                        <div id="mobileMenu" class="hidden lg:hidden border-t border-gray-200 dark:border-surface-dark bg-white dark:bg-surface-dark">
                            <nav class="max-w-[1600px] mx-auto px-8 py-4 flex flex-col gap-3">
                                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="index.php">News</a>
                                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">Programs</a>
                                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">On-Air Schedule</a>
                                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">About Us</a>
                                <a class="text-sm font-semibold leading-normal hover:text-primary transition-colors py-2" href="#">Contact</a>
                            </nav>
                        </div>
                    </header>
<main class="px-4 sm:px-6 md:px-10 lg:px-20 flex flex-1 justify-center py-10">
    <div class="layout-content-container flex flex-col w-full max-w-7xl flex-1 gap-8">

        <!-- Page Header -->
        <div class="flex flex-wrap justify-between items-center gap-4 pb-2 border-b border-gray-200 dark:border-gray-700">
            <div class="flex flex-col gap-1">
                <h1 class="text-4xl font-black tracking-tight leading-tight">On-Air Schedule</h1>
                <p class="text-base font-medium text-gray-600 dark:text-gray-400 flex items-center gap-2">
                    Showing schedule for:
                    <span id="scheduleDate" class="font-semibold text-primary"></span>
                    <span class="text-xs bg-gray-200 dark:bg-gray-800 px-2 py-0.5 rounded">PHT</span>
                </p>
            </div>

            <!-- Date Controls -->
            <div class="flex items-center gap-2">
                <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition" id="prevDay">
                    <span class="material-symbols-outlined">chevron_left</span>
                </button>
                <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition" id="resetToday">
                    <span class="material-symbols-outlined">calendar_today</span>
                </button>
                <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 transition" id="nextDay">
                    <span class="material-symbols-outlined">chevron_right</span>
                </button>
            </div>
        </div>

        <!-- Filters Row -->
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 p-4 bg-white dark:bg-[#101010] border border-gray-200 dark:border-gray-800 rounded-xl shadow-sm">
            <div class="flex h-10 items-center rounded-lg bg-gray-100 dark:bg-gray-800 p-1">
                <label class="flex cursor-pointer h-full items-center px-4 rounded-lg has-[:checked]:bg-white dark:has-[:checked]:bg-gray-900 has-[:checked]:shadow text-gray-500 dark:text-gray-400 text-sm font-medium">
                    Daily View
                    <input class="invisible w-0" type="radio" name="view-toggle" checked>
                </label>
                <label class="flex cursor-pointer h-full items-center px-4 rounded-lg has-[:checked]:bg-white dark:has-[:checked]:bg-gray-900 has-[:checked]:shadow text-gray-500 dark:text-gray-400 text-sm font-medium">
                    Weekly View
                    <input class="invisible w-0" type="radio" name="view-toggle">
                </label>
            </div>

            <div class="flex w-full md:w-auto items-center gap-2">
                <label class="flex-1">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 dark:text-gray-400">
                            <span class="material-symbols-outlined">search</span>
                        </span>
                        <input class="form-input w-full rounded-lg border-gray-300 dark:border-gray-700 bg-transparent pl-10 pr-3 py-2 text-sm focus:ring-primary focus:border-primary placeholder:text-gray-500 dark:placeholder:text-gray-400"
                            placeholder="Filter by program...">
                    </div>
                </label>

                <button class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition">
                    <span class="material-symbols-outlined">filter_list</span>
                </button>
            </div>
        </div>

        <!-- Improved Table -->
        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-[#101010] shadow-md">
            <table class="w-full text-left">
                <thead class="bg-gray-100 dark:bg-gray-900/40 backdrop-blur">
                    <tr>
                        <th class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Time</th>
                        <th class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Program</th>
                        <th class="px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Description</th>
                    </tr>
                </thead>
                <tbody>

                    <!-- 7 PM -->
                    <tr class="border-t border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">7:00 PM</td>
                        <td class="px-4 py-3 text-sm font-semibold">Evening News Report</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">A comprehensive look at today’s top stories.</td>
                    </tr>

                    <!-- LIVE row -->
                    <tr class="border-t border-gray-200 dark:border-gray-800 bg-blue-50 dark:bg-blue-900/20 hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">8:00 PM</td>
                        <td class="px-4 py-3 text-sm font-bold flex items-center gap-2">
                            Prime Time LIVE
                            <span class="flex items-center gap-1 rounded-full bg-primary text-white px-2 py-0.5 text-xs font-bold">
                                <span class="relative flex h-2 w-2">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span>
                                </span>
                                LIVE
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Live interviews and in-depth analysis.</td>
                    </tr>

                    <!-- 9 PM -->
                    <tr class="border-t border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">9:00 PM</td>
                        <td class="px-4 py-3 text-sm font-semibold">World In Focus</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">A deep-dive documentary on world affairs.</td>
                    </tr>

                    <!-- 10 PM -->
                    <tr class="border-t border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">10:00 PM</td>
                        <td class="px-4 py-3 text-sm font-semibold">The Late Edition</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Recap of the day with commentary.</td>
                    </tr>

                    <!-- 11 PM -->
                    <tr class="border-t border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-900/30 transition">
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">11:00 PM</td>
                        <td class="px-4 py-3 text-sm font-semibold">Nightly Debrief</td>
                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">Experts discuss political & economic updates.</td>
                    </tr>

                </tbody>
            </table>
        </div>

    </div>
</main>

                    <!-- Enhanced Footer -->
                    <footer class="bg-gradient-to-b from-gray-900 to-black mt-8 py-10 px-8 shadow-2xl">
                        <div class="max-w-[1600px] mx-auto grid grid-cols-1 md:grid-cols-4 gap-8">
                            <div class="md:col-span-1">
                                <div class="flex items-center gap-3 text-white mb-4">
                                    <div class="size-10 text-primary drop-shadow-md">
                                        <img src="https://www.dzrh.com.ph/dzrh-logo.svg" alt="DZRH" />
                                    </div>
                                    <h2 class="text-xl font-bold">DZRH News</h2>
                                </div>
                                <p class="text-sm text-gray-300 leading-relaxed mb-4">
                                    Your trusted source for breaking news, in-depth analysis, and live radio broadcasts since 1950.
                                </p>
                                <div class="flex space-x-3">
                                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M22.675 0h-21.35C.59 0 0 .59 0 1.325v21.35C0 23.41.59 24 1.325 24H12.82v-9.29H9.692v-3.622h3.128V8.413c0-3.1 1.893-4.788 4.659-4.788 1.325 0 2.463.099 2.795.143v3.24l-1.918.001c-1.504 0-1.795.715-1.795 1.763v2.313h3.587l-.467 3.622h-3.12V24h6.116c.735 0 1.325-.59 1.325-1.325V1.325C24 .59 23.409 0 22.675 0z"></path>
                                        </svg>
                                    </a>
                                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.223.085c.645 1.956 2.52 3.379 4.738 3.419-1.914 1.493-4.32 2.387-6.94 2.387-.452 0-.898-.027-1.336-.079a13.97 13.97 0 007.548 2.212c9.058 0 14.01-7.502 14.01-14.01 0-.213 0-.425-.015-.636A10.016 10.016 0 0024 4.59z"></path>
                                        </svg>
                                    </a>
                                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-accent-red hover:text-white transition-all transform hover:scale-110" href="#">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm4.441 16.892c-2.102.144-6.784.144-8.883 0C5.282 16.736 5.017 15.622 5 12c.017-3.629.285-4.736 2.558-4.892 2.099-.144 6.782-.144 8.883 0C18.718 7.264 18.982 8.378 19 12c-.018 3.629-.285 4.736-2.559 4.892zM10 9.658l4.917 2.338L10 14.342z"/>
                                        </svg>
                                    </a>
                                    <a class="flex items-center justify-center w-10 h-10 rounded-full bg-gray-800 text-gray-300 hover:bg-primary hover:text-white transition-all transform hover:scale-110" href="#">
                                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold mb-4 text-white">Quick Links</h4>
                                <ul class="space-y-2 text-sm">
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        About Us
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Our Team
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Careers
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Advertise
                                    </a></li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold mb-4 text-white">Categories</h4>
                                <ul class="space-y-2 text-sm">
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Politics
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Business
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Sports
                                    </a></li>
                                    <li><a class="text-gray-300 hover:text-primary transition-colors flex items-center gap-2" href="#">
                                        <span class="material-symbols-outlined text-sm">chevron_right</span>
                                        Entertainment
                                    </a></li>
                                </ul>
                            </div>
                            <div>
                                <h4 class="text-lg font-bold mb-4 text-white">Contact</h4>
                                <ul class="space-y-3 text-sm text-gray-300">
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-primary text-lg">location_on</span>
                                        <span>MBC Building, Star City, CCP Complex, Roxas Boulevard, Manila</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-lg">call</span>
                                        <span>(632) 8527-8515</span>
                                    </li>
                                    <li class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary text-lg">email</span>
                                        <span>info@dzrhnews.com.ph</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="mt-8 pt-6 border-t border-gray-800">
                            <div class="max-w-[1600px] mx-auto flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-400">
                                <p>© 2024 DZRH News. All Rights Reserved.</p>
                                <div class="flex items-center gap-4">
                                    <a href="#" class="hover:text-primary transition-colors">Privacy Policy</a>
                                    <span>•</span>
                                    <a href="#" class="hover:text-primary transition-colors">Terms of Service</a>
                                    <span>•</span>
                                    <a href="#" class="hover:text-primary transition-colors">Cookie Policy</a>
                                </div>
                            </div>
                        </div>
                    </footer>
</div>
</div>
<script src="script.js"></script>
<script>
    function updateScheduleDate() {
        const date = new Date().toLocaleDateString("en-PH", {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric",
            timeZone: "Asia/Manila"
        });
        document.getElementById("scheduleDate").textContent = date;
    }
    updateScheduleDate();
</script>

</body>
</html>