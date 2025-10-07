<?php
require '../db.php';


// Pagination setup
$limit = 8; // rows per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Fetch categories with pagination
$stmt = $pdo->prepare("SELECT * FROM categories ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total categories count
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$totalPages = ceil($totalCategories / $limit);




$totalArticles = $pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();
$totalCategories = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
$pendingReviews = $pdo->query("SELECT COUNT(*) FROM departments")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
                <span class="material-icons text-3xl lg:text-4xl text-purple-600 mr-3">category</span>
                <h2 class="text-2xl lg:text-3xl font-bold text-gray-800">Categories</h2>
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
                        <h3 class="text-lg lg:text-3xl font-bold text-gray-800"><?= $totalCategories ?></h3>
                        <p class="text-gray-500 text-xs lg:text-base">Total Categories</p>
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

              <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold text-gray-800">Manage Categories</h3>
                <button id="openAddCategoryModal" 
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                    + Add Category
                </button>
            </div>

        <!-- Categories Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full border border-gray-200 rounded-lg overflow-hidden">
                <thead class="bg-purple-600 text-white">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-medium">ID</th>
                        <th class="px-6 py-3 text-left text-sm font-medium">Category Name</th>
                        <th class="px-6 py-3 text-left text-sm font-medium">Created At</th>
                        <th class="px-6 py-3 text-center text-sm font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($categories as $row): ?>
                    <tr>
                        <td class="px-6 py-4 text-sm text-gray-800"><?= $row['id'] ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars($row['name']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= $row['created_at'] ?></td>
                        <td class="px-6 py-4 text-center space-x-2">
                            <button class="text-blue-600 hover:underline editCategoryBtn"
                                data-id="<?= $row['id'] ?>" 
                                data-name="<?= htmlspecialchars($row['name']) ?>">
                                Edit
                            </button>
                            <button class="text-red-600 hover:underline deleteCategoryBtn"
                                data-id="<?= $row['id'] ?>">
                                Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="flex justify-center items-center mt-6 space-x-2">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-purple-500 hover:text-white">Prev</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a href="?page=<?= $i ?>" 
                class="px-3 py-1 rounded <?= $i == $page ? 'bg-purple-600 text-white' : 'bg-gray-200 hover:bg-purple-500 hover:text-white' ?>">
                <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?= $page + 1 ?>" class="px-3 py-1 bg-gray-200 rounded hover:bg-purple-500 hover:text-white">Next</a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4 modal-backdrop">
        <div class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Add New Category</h2>
                <button id="closeAddCategoryModal" class="text-gray-500 hover:text-gray-800">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form method="POST" action="add_category.php" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input type="text" name="category_name" 
                        class="w-full mt-1 px-4 py-2 rounded-lg border border-gray-300 
                                focus:outline-none focus:ring-2 focus:ring-purple-500"
                        placeholder="Enter category name" required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancelAddCategory" 
                            class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4 modal-backdrop">    <div class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Edit Category</h2>
                <button id="closeEditCategoryModal" class="text-gray-500 hover:text-gray-800">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form method="POST" action="edit_category.php" class="space-y-4">
                <input type="hidden" name="id" id="editCategoryId">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Category Name</label>
                    <input type="text" name="category_name" id="editCategoryName"
                        class="w-full mt-1 px-4 py-2 rounded-lg border border-gray-300 
                                focus:outline-none focus:ring-2 focus:ring-purple-500"
                        required>
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancelEditCategory" 
                            class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4 modal-backdrop">    <div class="bg-white w-full max-w-md rounded-2xl shadow-lg p-6 modal-enter">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-gray-800">Delete Category</h2>
                <button id="closeDeleteCategoryModal" class="text-gray-500 hover:text-gray-800">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form method="POST" action="delete_category.php" class="space-y-4">
                <input type="hidden" name="id" id="deleteCategoryId">
                <p class="text-gray-700">Are you sure you want to delete this category?</p>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancelDeleteCategory" 
                            class="px-4 py-2 rounded-lg border border-gray-300 hover:bg-gray-100">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                        Delete
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- JS for Modals -->
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
    
    document.addEventListener("DOMContentLoaded", () => {
        // Add Category Modal
        const addModal = document.getElementById("addCategoryModal");
        document.getElementById("openAddCategoryModal").addEventListener("click", () => addModal.classList.remove("hidden"));
        document.getElementById("closeAddCategoryModal").addEventListener("click", () => addModal.classList.add("hidden"));
        document.getElementById("cancelAddCategory").addEventListener("click", () => addModal.classList.add("hidden"));
        addModal.addEventListener("click", (e) => { if (e.target === addModal) addModal.classList.add("hidden"); });

        // Edit Category Modal
        const editModal = document.getElementById("editCategoryModal");
        const editId = document.getElementById("editCategoryId");
        const editName = document.getElementById("editCategoryName");
        document.querySelectorAll(".editCategoryBtn").forEach(btn => {
            btn.addEventListener("click", () => {
                editId.value = btn.dataset.id;
                editName.value = btn.dataset.name;
                editModal.classList.remove("hidden");
            });
        });
        document.getElementById("closeEditCategoryModal").addEventListener("click", () => editModal.classList.add("hidden"));
        document.getElementById("cancelEditCategory").addEventListener("click", () => editModal.classList.add("hidden"));
        editModal.addEventListener("click", (e) => { if (e.target === editModal) editModal.classList.add("hidden"); });

            // Delete Category Modal
        const deleteModal = document.getElementById("deleteCategoryModal");
        const deleteId = document.getElementById("deleteCategoryId");

        document.querySelectorAll(".deleteCategoryBtn").forEach(btn => {
            btn.addEventListener("click", () => {
                deleteId.value = btn.dataset.id;
                deleteModal.classList.remove("hidden");
            });
        });

        document.getElementById("closeDeleteCategoryModal").addEventListener("click", () => deleteModal.classList.add("hidden"));
        document.getElementById("cancelDeleteCategory").addEventListener("click", () => deleteModal.classList.add("hidden"));
        deleteModal.addEventListener("click", (e) => { if (e.target === deleteModal) deleteModal.classList.add("hidden"); });

    });
    </script>



</body>
</html>