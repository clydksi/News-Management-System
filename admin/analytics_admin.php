<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>News Admin Dashboard - Analytics</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>
<style>
    html {scroll-behavior: smooth;}
    body { font-family: 'Poppins', sans-serif; background-color: #F0F2F5; }
    .sidebar { background-color: #6D28D9; transition: transform 0.3s ease-in-out; }
    .main-content { background-color: #EDE9FE; }
    .sidebar-hidden { transform: translateX(-100%); }
    @media (min-width: 1024px) {
        .sidebar-hidden { transform: translateX(0); }
    }
    .modal-backdrop { backdrop-filter: blur(4px); }
    .modal-enter { animation: modalEnter 0.3s ease-out; }
    .modal-exit { animation: modalExit 0.3s ease-in; }
    @keyframes modalEnter {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    @keyframes modalExit {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
    .chart-container { position: relative; height: 300px; }
    .stat-card { transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
</style>
</head>
<body class="flex flex-col h-screen overflow-hidden">

<!-- Mobile Header -->
<header class="lg:hidden bg-purple-600 text-white p-4 flex items-center justify-between">
    <button id="sidebarToggle" class="text-white">
        <span class="material-icons text-2xl">menu</span>
    </button>
    <div class="flex items-center">
        <span class="material-icons text-2xl mr-2">feed</span>
        <h1 class="text-lg font-bold">News Admin</h1>
    </div>
    <div class="flex items-center space-x-2">
        <button class="text-white">
            <span class="material-icons">notifications</span>
        </button>
        <div class="w-8 h-8 bg-purple-400 rounded-full flex items-center justify-center text-white font-bold text-sm">A</div>
    </div>
</header>

<div class="flex flex-1 relative overflow-hidden">
    <!-- Sidebar Overlay for Mobile -->
    <div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar fixed lg:relative w-64 h-full text-white flex flex-col p-4 z-30 lg:translate-x-0 -translate-x-full transition-transform duration-300">
        <div class="flex items-center mb-6 lg:mb-10">
            <span class="material-icons text-2xl lg:text-3xl mr-2">feed</span>
            <h1 class="text-xl lg:text-2xl font-bold">News Admin</h1>
            <button id="sidebarClose" class="ml-auto lg:hidden text-white">
                <span class="material-icons">close</span>
            </button>
        </div>
        <nav class="flex-1 overflow-y-auto">
            <ul class="space-y-2">
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="../admin_dashboard.php">
                        <span class="material-icons mr-3 text-xl">dashboard</span> Dashboard
                    </a>
                </li>
                <li>
                    <a  class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors cursor-pointer"  href="https://project.mbcradio.net/saas/chat.php">
                        <span class="material-icons mr-3 text-xl">people</span> Chat Community
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="articles_admin.php">
                        <span class="material-icons mr-3 text-xl">article</span> Articles
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="categories_admin.php">
                        <span class="material-icons mr-3 text-xl">category</span> Categories
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 bg-purple-700 rounded-lg transition-colors" href="#">
                        <span class="material-icons mr-3 text-xl">analytics</span> Analytics
                    </a>
                </li>
            </ul>
        </nav>
        <div class="border-t border-purple-500 pt-4 mt-4">
            <ul class="space-y-2">
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="#">
                        <span class="material-icons mr-3 text-xl">settings</span> Settings
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="../logout.php">
                        <span class="material-icons mr-3 text-xl">logout</span> Logout
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content flex-1 flex flex-col overflow-hidden">
        <!-- Desktop Header -->
        <header class="hidden lg:flex justify-between items-center p-6 lg:p-8">
            <div class="flex items-center">
                <span class="material-icons text-3xl lg:text-4xl text-purple-600 mr-3">analytics</span>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-800">Analytics Dashboard</h2>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <input class="bg-white rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-64" placeholder="Search analytics..." type="text"/>
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                </div>
                <select class="bg-white border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option>Last 7 Days</option>
                    <option>Last 30 Days</option>
                    <option>Last 3 Months</option>
                    <option>Last Year</option>
                </select>
                <button class="text-gray-500 hover:text-purple-600">
                    <span class="material-icons">notifications</span>
                </button>
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold mr-2">A</div>
                    <div>
                        <p class="font-semibold text-gray-800">Admin User</p>
                        <p class="text-sm text-gray-500">Super Admin</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Mobile Search Bar -->
        <div class="lg:hidden p-4 bg-white border-b">
            <div class="relative mb-3">
                <input class="bg-gray-100 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full" placeholder="Search analytics..." type="text"/>
                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            </div>
            <select class="bg-gray-100 border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full">
                <option>Last 7 Days</option>
                <option>Last 30 Days</option>
                <option>Last 3 Months</option>
                <option>Last Year</option>
            </select>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            
            <!-- Key Metrics Cards -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-6 mb-6 lg:mb-8">
                <div class="stat-card bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="bg-blue-100 p-2 lg:p-3 rounded-lg">
                            <span class="material-icons text-lg lg:text-xl text-blue-600">visibility</span>
                        </div>
                        <span class="text-green-500 text-xs lg:text-sm font-semibold">+12.5%</span>
                    </div>
                    <h3 class="text-lg lg:text-2xl font-bold text-gray-800">2.4M</h3>
                    <p class="text-gray-500 text-xs lg:text-sm">Page Views</p>
                </div>
                
                <div class="stat-card bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="bg-green-100 p-2 lg:p-3 rounded-lg">
                            <span class="material-icons text-lg lg:text-xl text-green-600">person</span>
                        </div>
                        <span class="text-green-500 text-xs lg:text-sm font-semibold">+8.2%</span>
                    </div>
                    <h3 class="text-lg lg:text-2xl font-bold text-gray-800">45.2K</h3>
                    <p class="text-gray-500 text-xs lg:text-sm">Unique Visitors</p>
                </div>
                
                <div class="stat-card bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="bg-purple-100 p-2 lg:p-3 rounded-lg">
                            <span class="material-icons text-lg lg:text-xl text-purple-600">schedule</span>
                        </div>
                        <span class="text-red-500 text-xs lg:text-sm font-semibold">-2.1%</span>
                    </div>
                    <h3 class="text-lg lg:text-2xl font-bold text-gray-800">3:24</h3>
                    <p class="text-gray-500 text-xs lg:text-sm">Avg. Session</p>
                </div>
                
                <div class="stat-card bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-2">
                        <div class="bg-orange-100 p-2 lg:p-3 rounded-lg">
                            <span class="material-icons text-lg lg:text-xl text-orange-600">thumb_up</span>
                        </div>
                        <span class="text-green-500 text-xs lg:text-sm font-semibold">+15.3%</span>
                    </div>
                    <h3 class="text-lg lg:text-2xl font-bold text-gray-800">89.5%</h3>
                    <p class="text-gray-500 text-xs lg:text-sm">Engagement Rate</p>
                </div>
            </section>

            <!-- Charts Section -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <!-- Traffic Overview -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Traffic Overview</h3>
                        <div class="flex space-x-2">
                            <button class="text-xs px-3 py-1 bg-purple-100 text-purple-600 rounded-full">Views</button>
                            <button class="text-xs px-3 py-1 bg-gray-100 text-gray-600 rounded-full">Visitors</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="trafficChart"></canvas>
                    </div>
                </div>

                <!-- Article Performance -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Article Performance</h3>
                        <span class="material-icons text-gray-400 cursor-pointer">more_vert</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="articleChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Additional Analytics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                <!-- Device Analytics -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Device Breakdown</h3>
                    <div class="chart-container h-48">
                        <canvas id="deviceChart"></canvas>
                    </div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                                <span class="text-sm text-gray-600">Desktop</span>
                            </div>
                            <span class="text-sm font-semibold">64.2%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2"></div>
                                <span class="text-sm text-gray-600">Mobile</span>
                            </div>
                            <span class="text-sm font-semibold">28.5%</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-purple-500 rounded-full mr-2"></div>
                                <span class="text-sm text-gray-600">Tablet</span>
                            </div>
                            <span class="text-sm font-semibold">7.3%</span>
                        </div>
                    </div>
                </div>

                <!-- Top Countries -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Top Countries</h3>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">🇺🇸</span>
                                <span class="text-sm font-medium">United States</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">24.5K</p>
                                <p class="text-xs text-gray-500">32%</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">🇬🇧</span>
                                <span class="text-sm font-medium">United Kingdom</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">18.2K</p>
                                <p class="text-xs text-gray-500">24%</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">🇨🇦</span>
                                <span class="text-sm font-medium">Canada</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">12.8K</p>
                                <p class="text-xs text-gray-500">17%</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">🇦🇺</span>
                                <span class="text-sm font-medium">Australia</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">8.9K</p>
                                <p class="text-xs text-gray-500">12%</p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <span class="text-2xl mr-2">🇩🇪</span>
                                <span class="text-sm font-medium">Germany</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">6.4K</p>
                                <p class="text-xs text-gray-500">8%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Social Media Traffic -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Social Media Traffic</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-xs font-bold">f</span>
                                </div>
                                <span class="text-sm font-medium">Facebook</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">45.2%</p>
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-blue-600 h-1.5 rounded-full" style="width: 45.2%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-sky-400 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-xs font-bold">t</span>
                                </div>
                                <span class="text-sm font-medium">Twitter</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">28.7%</p>
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-sky-400 h-1.5 rounded-full" style="width: 28.7%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-xs font-bold">▶</span>
                                </div>
                                <span class="text-sm font-medium">YouTube</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">16.3%</p>
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-red-500 h-1.5 rounded-full" style="width: 16.3%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gradient-to-r from-purple-400 to-pink-400 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-white text-xs font-bold">📷</span>
                                </div>
                                <span class="text-sm font-medium">Instagram</span>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-bold">9.8%</p>
                                <div class="w-16 bg-gray-200 rounded-full h-1.5">
                                    <div class="bg-gradient-to-r from-purple-400 to-pink-400 h-1.5 rounded-full" style="width: 9.8%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performing Articles -->
            <div class="bg-white p-6 rounded-2xl shadow-md mb-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-bold text-gray-800">Top Performing Articles</h3>
                    <button class="text-purple-600 text-sm font-semibold hover:text-purple-700">View All</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="text-left border-b">
                                <th class="pb-3 text-sm font-semibold text-gray-600">Article Title</th>
                                <th class="pb-3 text-sm font-semibold text-gray-600">Category</th>
                                <th class="pb-3 text-sm font-semibold text-gray-600">Views</th>
                                <th class="pb-3 text-sm font-semibold text-gray-600">Engagement</th>
                                <th class="pb-3 text-sm font-semibold text-gray-600">Published</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr class="hover:bg-gray-50">
                                <td class="py-4">
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Breaking: Major Tech Company Announces New Product</h4>
                                        <p class="text-xs text-gray-500 mt-1">Latest technology breakthrough...</p>
                                    </div>
                                </td>
                                <td class="py-4">
                                    <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full">Technology</span>
                                </td>
                                <td class="py-4 text-sm font-semibold text-gray-800">245.3K</td>
                                <td class="py-4">
                                    <div class="flex items-center">
                                        <span class="text-sm font-semibold text-green-600 mr-2">92.5%</span>
                                        <div class="w-12 bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-green-500 h-1.5 rounded-full" style="width: 92.5%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-sm text-gray-500">2 hours ago</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="py-4">
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Market Analysis: Economic Trends for 2024</h4>
                                        <p class="text-xs text-gray-500 mt-1">Comprehensive market overview...</p>
                                    </div>
                                </td>
                                <td class="py-4">
                                    <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Business</span>
                                </td>
                                <td class="py-4 text-sm font-semibold text-gray-800">189.7K</td>
                                <td class="py-4">
                                    <div class="flex items-center">
                                        <span class="text-sm font-semibold text-green-600 mr-2">87.3%</span>
                                        <div class="w-12 bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-green-500 h-1.5 rounded-full" style="width: 87.3%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-sm text-gray-500">5 hours ago</td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="py-4">
                                    <div>
                                        <h4 class="font-medium text-gray-800 text-sm">Health Update: New Research on Wellness Trends</h4>
                                        <p class="text-xs text-gray-500 mt-1">Important health discoveries...</p>
                                    </div>
                                </td>
                                <td class="py-4">
                                    <span class="inline-block bg-purple-100 text-purple-800 text-xs px-2 py-1 rounded-full">Health</span>
                                </td>
                                <td class="py-4 text-sm font-semibold text-gray-800">156.2K</td>
                                <td class="py-4">
                                    <div class="flex items-center">
                                        <span class="text-sm font-semibold text-yellow-600 mr-2">78.9%</span>
                                        <div class="w-12 bg-gray-200 rounded-full h-1.5">
                                            <div class="bg-yellow-500 h-1.5 rounded-full" style="width: 78.9%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 text-sm text-gray-500">1 day ago</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Real-time Activity -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800">Real-time Activity</h3>
                        <div class="flex items-center">
                            <div class="w-2 h-2 bg-green-500 rounded-full mr-2"></div>
                            <span class="text-sm text-gray-500">Live</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="material-icons text-green-600 text-sm">person_add</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">New user registration</p>
                                    <p class="text-xs text-gray-500">From United States</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400">2 min ago</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="material-icons text-purple-600 text-sm">comment</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">New comment posted</p>
                                    <p class="text-xs text-gray-500">On Business article</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400">5 min ago</span>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center mr-3">
                                    <span class="material-icons text-orange-600 text-sm">share</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium">Article shared</p>
                                    <p class="text-xs text-gray-500">On social media</p>
                                </div>
                            </div>
                            <span class="text-xs text-gray-400">8 min ago</span>
                        </div>
                    </div>
                </div>

                <!-- Revenue Analytics -->
                <div class="bg-white p-6 rounded-2xl shadow-md">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">Revenue Analytics</h3>
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-green-100 rounded-lg">
                            <div>
                                <p class="text-2xl font-bold text-green-700">$24,580</p>
                                <p class="text-sm text-green-600">This Month</p>
                            </div>
                            <div class="text-right">
                                <span class="inline-block bg-green-200 text-green-800 text-xs px-2 py-1 rounded-full">+18.5%</span>
                                <p class="text-xs text-green-600 mt-1">vs last month</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4">
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-lg font-bold text-gray-800">$8,240</p>
                                <p class="text-xs text-gray-500">Ad Revenue</p>
                            </div>
                            <div class="text-center p-3 bg-gray-50 rounded-lg">
                                <p class="text-lg font-bold text-gray-800">$16,340</p>
                                <p class="text-xs text-gray-500">Subscriptions</p>
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm text-gray-600">Monthly Goal</span>
                                <span class="text-sm font-semibold">82% Complete</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-purple-500 to-purple-600 h-2 rounded-full" style="width: 82%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Sidebar Toggle
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebarOverlay.classList.remove('hidden');
}

function closeSidebar() {
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
}

sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

// Chart.js Configurations
document.addEventListener('DOMContentLoaded', function() {
    
    // Traffic Chart
    const trafficCtx = document.getElementById('trafficChart');
    if (trafficCtx) {
        new Chart(trafficCtx, {
            type: 'line',
            data: {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                datasets: [{
                    label: 'Page Views',
                    data: [12000, 15000, 13000, 18000, 16000, 22000, 19000],
                    borderColor: '#8B5CF6',
                    backgroundColor: 'rgba(139, 92, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Article Performance Chart
    const articleCtx = document.getElementById('articleChart');
    if (articleCtx) {
        new Chart(articleCtx, {
            type: 'bar',
            data: {
                labels: ['Tech', 'Business', 'Health', 'Sports', 'Politics'],
                datasets: [{
                    label: 'Articles Published',
                    data: [45, 32, 28, 22, 18],
                    backgroundColor: ['#3B82F6', '#10B981', '#8B5CF6', '#F59E0B', '#EF4444'],
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }

    // Device Chart
    const deviceCtx = document.getElementById('deviceChart');
    if (deviceCtx) {
        new Chart(deviceCtx, {
            type: 'doughnut',
            data: {
                labels: ['Desktop', 'Mobile', 'Tablet'],
                datasets: [{
                    data: [64.2, 28.5, 7.3],
                    backgroundColor: ['#3B82F6', '#10B981', '#8B5CF6'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }

    // Real-time updates simulation
    function updateRealTimeStats() {
        const stats = document.querySelectorAll('.stat-card h3');
        stats.forEach(stat => {
            const currentValue = parseFloat(stat.textContent.replace(/[^\d.]/g, ''));
            const variation = (Math.random() - 0.5) * 0.1;
            const newValue = currentValue * (1 + variation);
            
            if (stat.textContent.includes('M')) {
                stat.textContent = newValue.toFixed(1) + 'M';
            } else if (stat.textContent.includes('K')) {
                stat.textContent = newValue.toFixed(1) + 'K';
            } else if (stat.textContent.includes('%')) {
                stat.textContent = newValue.toFixed(1) + '%';
            } else if (stat.textContent.includes(':')) {
                const minutes = Math.floor(newValue);
                const seconds = Math.floor((newValue - minutes) * 60);
                stat.textContent = minutes + ':' + seconds.toString().padStart(2, '0');
            }
        });
    }

    // Update stats every 5 seconds
    setInterval(updateRealTimeStats, 5000);
});
</script>

</body>
</html>
