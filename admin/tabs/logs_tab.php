<!-- Activity Logs Tab -->
<section class="bg-white rounded-2xl shadow-md overflow-hidden">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center p-6 border-b">
        <div>
            <h3 class="text-xl font-bold text-gray-800 mb-1">Activity Logs</h3>
            <p class="text-sm text-gray-500">Monitor system activities and user actions</p>
        </div>
        <div class="flex space-x-2 mt-3 sm:mt-0">
            <button onclick="exportLogs()" class="action-btn bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center text-sm shadow-lg">
                <span class="material-icons mr-2 text-base">download</span> Export
            </button>
            <button onclick="location.reload()" class="action-btn bg-gray-100 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors flex items-center text-sm">
                <span class="material-icons mr-2 text-base">refresh</span> Refresh
            </button>
        </div>
    </div>

    <!-- Filters -->
    <div class="p-4 bg-gray-50 border-b">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="relative">
                <input type="text" id="searchLogs" placeholder="Search logs..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <span class="material-icons absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xl">search</span>
            </div>
            <select id="filterAction" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                <option value="">All Actions</option>
                <option value="create">Create</option>
                <option value="update">Update</option>
                <option value="delete">Delete</option>
                <option value="login">Login</option>
                <option value="logout">Logout</option>
            </select>
            <input type="date" id="filterDateFrom" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
            <input type="date" id="filterDateTo" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
        </div>
    </div>

    <!-- Mobile Card View -->
    <div class="lg:hidden">
        <div class="divide-y divide-gray-200">
            <?php if (isset($logs) && !empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                <div class="p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-100 to-blue-200 rounded-lg flex items-center justify-center mr-3">
                                <span class="material-icons text-blue-600 text-sm">
                                    <?php 
                                    $icons = [
                                        'create' => 'add_circle',
                                        'update' => 'edit',
                                        'delete' => 'delete',
                                        'login' => 'login',
                                        'logout' => 'logout'
                                    ];
                                    echo $icons[$log['action']] ?? 'info';
                                    ?>
                                </span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-800"><?= e($log['username'] ?? 'System') ?></p>
                                <p class="text-xs text-gray-500"><?= e($log['action']) ?></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600 mb-2"><?= e($log['description']) ?></p>
                    <p class="text-xs text-gray-400">
                        <span class="material-icons text-xs align-middle">schedule</span>
                        <?= date('M d, Y g:i A', strtotime($log['created_at'])) ?>
                    </p>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    <span class="material-icons text-6xl mb-2 text-gray-300">history</span>
                    <p>No activity logs found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Desktop Table View -->
    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="bg-gray-50 text-gray-600 uppercase text-xs font-semibold border-b">
                    <th class="py-4 px-6">User</th>
                    <th class="py-4 px-6">Action</th>
                    <th class="py-4 px-6">Description</th>
                    <th class="py-4 px-6">IP Address</th>
                    <th class="py-4 px-6">Date & Time</th>
                </tr>
            </thead>
            <tbody class="text-gray-700 divide-y divide-gray-100">
            <?php if (isset($logs) && !empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="py-4 px-6">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-gradient-to-br from-purple-100 to-purple-200 rounded-full flex items-center justify-center mr-2 text-xs font-bold text-purple-700">
                                <?= strtoupper(substr($log['username'] ?? 'S', 0, 1)) ?>
                            </div>
                            <span class="font-medium"><?= e($log['username'] ?? 'System') ?></span>
                        </div>
                    </td>
                    <td class="py-4 px-6">
                        <span class="inline-flex items-center px-2 py-1 text-xs font-semibold rounded-full
                            <?php 
                            $actionColors = [
                                'create' => 'bg-green-100 text-green-700',
                                'update' => 'bg-blue-100 text-blue-700',
                                'delete' => 'bg-red-100 text-red-700',
                                'login' => 'bg-purple-100 text-purple-700',
                                'logout' => 'bg-gray-100 text-gray-700'
                            ];
                            echo $actionColors[$log['action']] ?? 'bg-gray-100 text-gray-700';
                            ?>">
                            <span class="material-icons text-xs mr-1">
                                <?php 
                                $icons = [
                                    'create' => 'add_circle',
                                    'update' => 'edit',
                                    'delete' => 'delete',
                                    'login' => 'login',
                                    'logout' => 'logout'
                                ];
                                echo $icons[$log['action']] ?? 'info';
                                ?>
                            </span>
                            <?= ucfirst(e($log['action'])) ?>
                        </span>
                    </td>
                    <td class="py-4 px-6 text-sm"><?= e($log['description']) ?></td>
                    <td class="py-4 px-6 text-sm text-gray-500"><?= e($log['ip_address'] ?? '-') ?></td>
                    <td class="py-4 px-6 text-sm text-gray-500">
                        <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                        <span class="text-xs"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="py-12 text-center text-gray-500">
                        <span class="material-icons text-6xl mb-2 text-gray-300 block">history</span>
                        No activity logs found
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if (isset($totalPages) && $totalPages > 1): ?>
    <div class="flex flex-col sm:flex-row justify-between items-center p-6 border-t bg-gray-50">
        <p class="text-sm text-gray-600 mb-3 sm:mb-0">
            Showing <?= min($offset + 1, $totalLogs) ?> to <?= min($offset + $perPage, $totalLogs) ?> of <?= $totalLogs ?> logs
        </p>
        <div class="flex items-center space-x-1">
            <?php if ($page > 1): ?>
                <a href="?tab=logs&page=<?= $page - 1 ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 text-sm transition-colors">
                    <span class="material-icons text-sm align-middle">chevron_left</span>
                </a>
            <?php else: ?>
                <span class="px-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-400 text-sm cursor-not-allowed">
                    <span class="material-icons text-sm align-middle">chevron_left</span>
                </span>
            <?php endif; ?>

            <?php 
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            for ($i = $startPage; $i <= $endPage; $i++): 
            ?>
                <?php if ($i == $page): ?>
                    <span class="px-4 py-2 border border-purple-600 rounded-lg bg-purple-600 text-white text-sm font-semibold"><?= $i ?></span>
                <?php else: ?>
                    <a href="?tab=logs&page=<?= $i ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 text-sm transition-colors"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="?tab=logs&page=<?= $page + 1 ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-lg bg-white hover:bg-gray-50 text-sm transition-colors">
                    <span class="material-icons text-sm align-middle">chevron_right</span>
                </a>
            <?php else: ?>
                <span class="px-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-400 text-sm cursor-not-allowed">
                    <span class="material-icons text-sm align-middle">chevron_right</span>
                </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Search and Filter
    const searchInput = document.getElementById('searchLogs');
    const actionFilter = document.getElementById('filterAction');
    const dateFromFilter = document.getElementById('filterDateFrom');
    const dateToFilter = document.getElementById('filterDateTo');

    function filterLogs() {
        const searchTerm = searchInput.value.toLowerCase();
        const actionValue = actionFilter.value.toLowerCase();
        const dateFrom = dateFromFilter.value;
        const dateTo = dateToFilter.value;

        const rows = document.querySelectorAll('tbody tr, .lg\\:hidden > div > div');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const matchesSearch = searchTerm === '' || text.includes(searchTerm);
            const matchesAction = actionValue === '' || text.includes(actionValue);
            
            // For date filtering, you'd need to add data attributes or parse the date
            const matchesDate = true; // Simplified for now

            if (matchesSearch && matchesAction && matchesDate) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    searchInput?.addEventListener('input', filterLogs);
    actionFilter?.addEventListener('change', filterLogs);
    dateFromFilter?.addEventListener('change', filterLogs);
    dateToFilter?.addEventListener('change', filterLogs);
});

// Export Logs
async function exportLogs() {
    try {
        const response = await fetch('actions/export_logs.php', {
            method: 'POST'
        });
        
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `activity_logs_${new Date().toISOString().split('T')[0]}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        showNotification('Logs exported successfully!', 'success');
    } catch (error) {
        console.error('Error:', error);
        showNotification('Failed to export logs', 'error');
    }
}
</script>