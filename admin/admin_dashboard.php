<?php
session_start();
require '../db.php';
require '../csrf.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Pagination settings
$perPage = 5;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// Total users
$totalStmt = $pdo->query("SELECT COUNT(*) FROM users");
$totalUsers = $totalStmt->fetchColumn();
$totalPages = ceil($totalUsers / $perPage);

// Fetch users with department
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role, u.created_at, d.name AS department
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    ORDER BY u.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboard stats
$totalArticles = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
//$totalComments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()) ?>"/>
<title>News Admin Dashboard</title>
<script src="https://cdn.tailwindcss.com"></script>
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
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="admin_dashboard.php">
                        <span class="material-icons mr-3 text-xl">dashboard</span> Dashboard
                    </a>
                </li>
                <li>
                    <a  class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors cursor-pointer"  href="https://project.mbcradio.net/saas/chat.php">
                        <span class="material-icons mr-3 text-xl">people</span> Chat Community
                    </a>
                </li>
                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="admin/articles_admin.php">
                        <span class="material-icons mr-3 text-xl">article</span> Articles
                    </a>
                </li>
            <li>
                <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" 
                href="admin/categories_admin.php">
                    <span class="material-icons mr-3 text-xl">category</span> Categories
                </a>
            </li>

                <li>
                    <a class="flex items-center p-3 hover:bg-purple-700 rounded-lg transition-colors" href="#">
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
                <span class="material-icons text-3xl lg:text-4xl text-purple-600 mr-3">menu_open</span>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-800">Dashboard</h2>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <input class="bg-white rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-64" placeholder="Search everything..." type="text"/>
                    <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                </div>
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
            <div class="relative">
                <input class="bg-gray-100 rounded-full py-2 pl-10 pr-4 focus:outline-none focus:ring-2 focus:ring-purple-500 w-full" placeholder="Search everything..." type="text"/>
                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">search</span>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-4 lg:p-8">
            <!-- Dashboard cards -->
            <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-6 mb-6 lg:mb-8">
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-purple-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-purple-600">article</span>
                    </div>
                    <div class="text-center lg:text-left">
                         <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $totalArticles ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Articles</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-green-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-green-600">group</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $activeUsers ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Active Users</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-orange-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-orange-600">comment</span>
                    </div>
                    <div class="text-center lg:text-left">
                        <h3 class="text-lg lg:text-3xl font-bold text-gray-800">89</h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Comments</p>
                    </div>
                </div>
                <div class="bg-white p-3 lg:p-6 rounded-xl lg:rounded-2xl shadow-md flex flex-col lg:flex-row items-center">
                    <div class="bg-red-100 p-2 lg:p-4 rounded-lg lg:rounded-xl mb-2 lg:mb-0 lg:mr-4">
                        <span class="material-icons text-lg lg:text-2xl text-red-600">pending_actions</span>
                    </div>
                    <div class="text-center lg:text-left">
                         <h3 class="text-2xl sm:text-3xl font-bold text-gray-800"><?= $pendingReviews ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Dept.</p>
                    </div>
                </div>
            </section>

            <!-- Users table -->
            <section class="bg-white rounded-xl lg:rounded-2xl shadow-md overflow-hidden mb-6 lg:mb-8">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-4 lg:p-6 border-b">
                    <h3 class="text-lg lg:text-2xl font-bold text-gray-800 mb-2 sm:mb-0">Users Table</h3>
                    <div class="flex space-x-2">
                        <button id="addUserBtn" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors flex items-center text-sm">
                            <span class="material-icons mr-2 text-base">add</span> Add User
                        </button>
                        <button class="bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center text-sm">
                            <span class="material-icons mr-2 text-base">refresh</span> Refresh
                        </button>
                    </div>
                </div>
                
                <!-- Mobile Card View -->
                <div class="lg:hidden">
                    <div class="divide-y divide-gray-200" id="mobileUserList">
                        <?php foreach ($users as $user): ?>
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-purple-200 rounded-full flex items-center justify-center font-bold text-purple-700 mr-3"> 
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>

                                    <div>
                                        <p class="font-semibold text-gray-800"><?= htmlspecialchars($user['username']) ?></p>
                                        <p class="text-sm text-gray-500"><?= htmlspecialchars($user['role']) ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-500"><?= htmlspecialchars($user['department'] ?? '-') ?></p>
                                    <p class="text-xs text-gray-400"><?= date("M d, Y", strtotime($user['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="flex space-x-4 text-sm">
                                <button class="text-blue-500 view-user"  data-id="<?= $user['id'] ?>">View</button>
                                <button class="text-purple-500 edit-user" data-id="<?= $user['id'] ?>">Edit</button>
                                <button class="text-red-500 delete-user" data-id="<?= $user['id'] ?>">Delete</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>


                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto" id="user">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-gray-500 uppercase text-sm border-b">
                                <th class="py-3 px-6">User</th>
                                <th class="py-3 px-6">Role</th>
                                <th class="py-3 px-6">Department</th>
                                <th class="py-3 px-6">Registered</th>
                                <th class="py-3 px-6">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="text-gray-700">
                        <?php foreach ($users as $user): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-4 px-6 flex items-center">
                                    <div class="w-10 h-10 bg-purple-200 rounded-full flex items-center justify-center font-bold text-purple-700 mr-3"> 
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                    <?= htmlspecialchars($user['username']) ?>
                                </td>
                                <td class="py-4 px-6"><?= htmlspecialchars($user['role']) ?></td>
                                <td class="py-4 px-6"><?= htmlspecialchars($user['department'] ?? '-') ?></td>
                                <td class="py-4 px-6"><?= date("M d, Y", strtotime($user['created_at'])) ?></td>
                                    <td class="py-4 px-6">
                                        <button class="text-blue-500 hover:underline mr-3 view-user"  data-id="<?= $user['id'] ?>">View</button>

                                        <button class="text-purple-500 hover:underline mr-3 edit-user" data-id="<?= $user['id'] ?>">Edit</button>

                                        <button class="text-red-500 hover:underline delete-user" data-id="<?= $user['id'] ?>">Delete</button>
                                    </td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="flex flex-col sm:flex-row justify-between items-center p-4 lg:p-6 border-t bg-gray-50">
                    <div class="flex items-center space-x-1">
                        <!-- Previous -->
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" 
                            class="px-3 py-1 border rounded-md bg-gray-200 text-sm hover:bg-gray-300">Previous</a>
                        <?php else: ?>
                            <span class="px-3 py-1 border rounded-md bg-gray-100 text-gray-400 text-sm">Previous</span>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-1 border rounded-md bg-purple-600 text-white text-sm"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" 
                                class="px-3 py-1 border rounded-md hover:bg-gray-100 text-sm"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <!-- Next -->
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>" 
                            class="px-3 py-1 border rounded-md bg-gray-200 text-sm hover:bg-gray-300">Next</a>
                        <?php else: ?>
                            <span class="px-3 py-1 border rounded-md bg-gray-100 text-gray-400 text-sm">Next</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Charts placeholders -->
            <section class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                <!-- Traffic Overview -->
                <div class="lg:col-span-2 bg-white p-4 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                        <h3 class="text-lg lg:text-xl font-bold text-gray-800 mb-2 sm:mb-0">Traffic Overview</h3>
                            <div class="relative">
                                <button onclick="toggleDropdown()" 
                                        class="text-gray-500 border border-gray-300 rounded-lg px-3 py-1 flex items-center text-sm">
                                    Last 7 days 
                                    <span class="material-icons text-base ml-1">expand_more</span>
                                </button>

                                <!-- Dropdown -->
                                <div id="dateDropdown" class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-xl shadow-lg z-10">
                                    <ul class="py-2 text-sm text-gray-700">
                                        <li><button onclick="updateChartRange(7)"  class="w-full text-left px-4 py-2 hover:bg-gray-100">Last 7 days</button></li>
                                        <li><button onclick="updateChartRange(30)" class="w-full text-left px-4 py-2 hover:bg-gray-100">Last 30 days</button></li>
                                        <li><button onclick="updateChartRange(90)" class="w-full text-left px-4 py-2 hover:bg-gray-100">Last 90 days</button></li>
                                    </ul>
                                </div>
                            </div>

                    </div>
                    <div class="h-72 lg:h-96 bg-gray-100 rounded-xl flex items-center justify-center">
                        <!-- Chart.js canvas -->
                        <canvas id="trafficChart" class="w-full h-full"></canvas>
                    </div>
                </div>

                <!-- Engagement -->
                <div class="bg-white p-4 lg:p-6 rounded-xl lg:rounded-2xl shadow-md">
                    <h3 class="text-lg lg:text-xl font-bold text-gray-800 mb-4">Engagement</h3>
                    <div class="h-72 lg:h-96 bg-gray-100 rounded-xl flex items-center justify-center">
                        <!-- Chart.js canvas -->
                        <canvas id="engagementChart" class="w-full h-full"></canvas>
                    </div>
                </div>
            </section>


        <!-- Add User Modal -->
        <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop hidden items-center justify-center z-50 p-4">
            <div class="bg-white w-full max-w-lg rounded-2xl shadow-lg p-6 lg:p-8 modal-enter">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">Add New User</h2>
                    <button id="closeAddUserModal" class="text-gray-500 hover:text-gray-800">
                        <span class="material-icons">close</span>
                    </button>
                </div>
                <form id="addUserForm" class="space-y-4">
                    <!-- Username -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" name="username" required
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                        <input type="password" name="password" required
                            class="w-full p-3 border rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <!-- Role -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                        <select name="role" required
                            class="w-full border rounded-lg px-3 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="" disabled selected>Select Role</option>
                            <option value="admin">admin</option>
                            <option value="user">user</option>
                            <option value="superadmin">superadmin</option>
                        </select>
                    </div>
                    <!-- Department -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select name="department" required
                            class="w-full border rounded-lg px-3 py-3 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <option value="" disabled selected>Select Department</option>
                            <option value="1">IT</option>
                            <option value="2">HR</option>
                            <option value="3">Finance</option>
                            <option value="4">News</option>
                        </select>
                    </div>
                    <!-- Buttons -->
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                        <button type="button" id="cancelAddUser"
                            class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Cancel</button>
                        <button type="submit"
                            class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">Add User</button>
                    </div>
                </form>
            </div>
        </div>


                <!-- View User Modal -->
                    <div id="ViewUserModal" 
                        class="fixed inset-0 bg-black bg-opacity-50 modal-backdrop hidden flex items-center justify-center z-50 p-4">
                    <div class="bg-white w-full max-w-lg rounded-2xl shadow-lg p-6 lg:p-8 modal-enter">

                    
                    <!-- Modal Header -->
                    <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold text-gray-800">User Details</h2>
                    <button id="closeViewUserModal" class="text-gray-500 hover:text-gray-800">
                        <span class="material-icons">close</span>
                    </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="space-y-4">
                    <div class="text-center mb-6">
                        <div id="viewUserAvatar" 
                            class="w-20 h-20 bg-purple-200 rounded-full flex items-center justify-center font-bold text-purple-700 text-2xl mx-auto mb-3">?</div>
                        <h3 id="viewUserName" class="text-lg font-semibold text-gray-800">Loading...</h3>
                        <p id="viewUserRole" class="text-sm text-gray-500">Loading...</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <p id="viewUserDepartment" class="text-gray-800">Loading...</p>
                        </div>
                        <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <span id="viewUserStatus" class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">Loading...</span>
                        </div>
                        <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Joined</label>
                        <p id="viewUserJoined" class="text-gray-800">Loading...</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Last Seen</label>
                        <p id="viewUserLastSeen" class="text-gray-800">N/A</p>
                    </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex justify-end space-x-3 mt-6 pt-4 border-t">
                    <button id="closeViewUser" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">Close</button>
                    <button id="editFromView" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">Edit User</button>
                    </div>
                </div>
                </div>

                <!-- Edit User Modal -->
                <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
                <div class="bg-white w-full max-w-lg rounded-2xl shadow-xl p-6 lg:p-8">
                    
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-6 border-b pb-3">
                    <h2 class="text-xl font-semibold text-gray-800">Edit User</h2>
                    <button id="closeEditUserModal" 
                            class="text-gray-400 hover:text-gray-600 transition-colors text-2xl leading-none">
                        &times;
                    </button>
                    </div>

                        <!-- Form -->
                        <form id="editUserForm" class="space-y-5">
                        <input type="hidden" id="editUserId" name="id">

                        <!-- Username -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                            <input type="text" id="editUsername" name="username" 
                                class="w-full border border-gray-300 rounded-xl p-2.5 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" 
                                required>
                        </div>

                        <!-- Role -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                            <select id="editRole" name="role" 
                                    class="w-full border border-gray-300 rounded-xl p-2.5 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                            <option value="">Select Role</option>
                            <option value="user">user</option>
                            <option value="admin">admin</option>
                            <option value="superadmin">superadmin</option>
                            </select>
                        </div>


                        <!-- Department -->
                        <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <select id="editDepartment" name="department"
                            class="w-full border border-gray-300 rounded-xl p-2.5 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                            <option value="">Select Department</option>
                            <option value="1">IT</option>
                            <option value="2">HR</option>
                            <option value="3">Finance</option>
                            <option value="4">News</option>
                            <!-- Options will be populated dynamically -->
                        </select>
                        </div>

                        <!-- inside editUserForm -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                            <input type="password" id="editPassword" name="password" 
                                class="w-full border border-gray-300 rounded-xl p-2.5 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition" >
                        </div>



                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="editStatus" name="status" 
                                    class="w-full border border-gray-300 rounded-xl p-2.5 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 outline-none transition">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                            </select>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" id="cancelEditUser" 
                                    class="px-4 py-2 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 transition">
                            Cancel
                            </button>
                            <button type="submit" 
                                    class="px-4 py-2 bg-purple-600 text-white rounded-xl hover:bg-purple-700 shadow-md transition">
                            Save
                            </button>
                        </div>
                        </form>
                    </div>
                    </div>



        </div>
    </main>
</div>

<!-- JS for Modal -->
<script src="admin_dashboard.js"></script>
<script src="add_user.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

    // Sidebar toggle functionality
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebarClose = document.getElementById('sidebarClose');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function openSidebar() {
    sidebar.classList.remove('-translate-x-full');
    sidebar.classList.add('translate-x-0');
    sidebarOverlay.classList.remove('hidden');
}

function closeSidebar() {
    sidebar.classList.remove('translate-x-0');
    sidebar.classList.add('-translate-x-full');
    sidebarOverlay.classList.add('hidden');
}

sidebarToggle?.addEventListener('click', openSidebar);
sidebarClose?.addEventListener('click', closeSidebar);
sidebarOverlay?.addEventListener('click', closeSidebar);

// Close sidebar when clicking outside on mobile
document.addEventListener('click', (e) => {
    if (window.innerWidth < 1024 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
        closeSidebar();
    }
});

    
document.addEventListener("DOMContentLoaded", async () => {
    const res = await fetch("admin/get_chart_data.php");
    const data = await res.json();

    // --- Traffic Overview (Line Chart) ---
    const trafficLabels = data.traffic.map(item => item.reg_date);
    const trafficData = data.traffic.map(item => item.total);

    new Chart(document.getElementById("trafficChart"), {
        type: "line",
        data: {
            labels: trafficLabels,
            datasets: [{
                label: "New Users",
                data: trafficData,
                borderColor: "rgba(75, 192, 192, 1)",
                backgroundColor: "rgba(75, 192, 192, 0.2)",
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: true } }
        }
    });

    // --- Engagement (Pie Chart) ---
    const roleLabels = data.roles.map(item => item.role);
    const roleData = data.roles.map(item => item.total);

    new Chart(document.getElementById("engagementChart"), {
        type: "pie",
        data: {
            labels: roleLabels,
            datasets: [{
                data: roleData,
                backgroundColor: ["#4F46E5", "#10B981", "#F59E0B", "#EF4444"]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: "bottom" } }
        }
    });

    
});

function toggleDropdown() {
    document.getElementById("dateDropdown").classList.toggle("hidden");
}

// Example: reload chart with different range
function updateChartRange(days) {
    console.log("Load chart for last " + days + " days");
    document.getElementById("dateDropdown").classList.add("hidden");
    // 🔹 Here you’d re-fetch data from PHP: get_chart_data.php?days=30
}


</script>







</body>
</html>