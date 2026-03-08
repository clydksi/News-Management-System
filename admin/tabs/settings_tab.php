<!-- System Settings Tab -->
<section class="space-y-6">
    <!-- General Settings -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-6 border-b bg-gradient-to-r from-purple-50 to-blue-50">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl flex items-center justify-center mr-4">
                    <span class="material-icons text-purple-600 text-2xl">settings</span>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-gray-800">System Settings</h3>
                    <p class="text-sm text-gray-500">Configure your CMS system preferences</p>
                </div>
            </div>
        </div>

        <form id="settingsForm" class="p-6 space-y-6">
            <!-- Site Information -->
            <div class="border-b pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-blue-600 mr-2">info</span>
                    Site Information
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Site Name</label>
                        <input type="text" name="site_name" value="<?= e($settings['site_name'] ?? 'News CMS') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Site Email</label>
                        <input type="email" name="site_email" value="<?= e($settings['site_email'] ?? '') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Site Description</label>
                        <textarea name="site_description" rows="3"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= e($settings['site_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Pagination Settings -->
            <div class="border-b pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-green-600 mr-2">view_list</span>
                    Pagination Settings
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Articles Per Page</label>
                        <input type="number" name="articles_per_page" min="5" max="100" value="<?= e($settings['articles_per_page'] ?? '10') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Users Per Page</label>
                        <input type="number" name="users_per_page" min="5" max="100" value="<?= e($settings['users_per_page'] ?? '10') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                </div>
            </div>

            <!-- Article Settings -->
            <div class="border-b pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-orange-600 mr-2">article</span>
                    Article Settings
                </h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="font-semibold text-gray-800">Require Approval for Articles</p>
                            <p class="text-sm text-gray-500">New articles need admin approval before publishing</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="require_approval" value="1" <?= ($settings['require_approval'] ?? '0') == '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="font-semibold text-gray-800">Enable Comments</p>
                            <p class="text-sm text-gray-500">Allow users to comment on articles</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_comments" value="1" <?= ($settings['enable_comments'] ?? '0') == '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- File Upload Settings -->
            <div class="border-b pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-red-600 mr-2">upload_file</span>
                    File Upload Settings
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Max File Size (MB)</label>
                        <input type="number" name="max_file_size" min="1" max="100" value="<?= e($settings['max_file_size'] ?? '10') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Allowed File Types</label>
                        <input type="text" name="allowed_file_types" value="<?= e($settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx') ?>"
                            class="w-full p-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            placeholder="jpg,png,pdf">
                        <p class="text-xs text-gray-500 mt-1">Separate extensions with commas</p>
                    </div>
                </div>
            </div>

            <!-- Email Notifications -->
            <div class="border-b pb-6">
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-purple-600 mr-2">email</span>
                    Email Notifications
                </h4>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="font-semibold text-gray-800">New User Registration</p>
                            <p class="text-sm text-gray-500">Send email when a new user is registered</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_new_user" value="1" <?= ($settings['email_new_user'] ?? '0') == '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                        <div>
                            <p class="font-semibold text-gray-800">New Article Published</p>
                            <p class="text-sm text-gray-500">Send email when a new article is published</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_new_article" value="1" <?= ($settings['email_new_article'] ?? '0') == '1' ? 'checked' : '' ?> class="sr-only peer">
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-purple-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-purple-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div>
                <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                    <span class="material-icons text-yellow-600 mr-2">construction</span>
                    Maintenance Mode
                </h4>
                <div class="flex items-center justify-between p-4 bg-yellow-50 border border-yellow-200 rounded-xl">
                    <div>
                        <p class="font-semibold text-gray-800">Enable Maintenance Mode</p>
                        <p class="text-sm text-gray-500">Show maintenance page to all users except admins</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') == '1' ? 'checked' : '' ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-yellow-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-yellow-500"></div>
                    </label>
                </div>
            </div>

            <!-- Save Button -->
            <div class="flex justify-end space-x-3 pt-6 border-t">
                <button type="button" onclick="location.reload()"
                    class="action-btn px-6 py-3 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-colors font-medium">
                    Cancel
                </button>
                <button type="submit"
                    class="action-btn px-8 py-3 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white rounded-xl transition-all shadow-lg font-medium">
                    <span class="material-icons text-sm align-middle mr-2">save</span>
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    <!-- Database Backup -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <span class="material-icons text-blue-600 mr-2">backup</span>
                Database Backup
            </h3>
        </div>
        <div class="p-6">
            <p class="text-gray-600 mb-4">Create a backup of your database to protect your data.</p>
            <button onclick="backupDatabase()" 
                class="action-btn px-6 py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl transition-all shadow-lg font-medium">
                <span class="material-icons text-sm align-middle mr-2">cloud_download</span>
                Create Backup
            </button>
        </div>
    </div>

    <!-- Clear Cache -->
    <div class="bg-white rounded-2xl shadow-md overflow-hidden">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <span class="material-icons text-orange-600 mr-2">cached</span>
                Clear Cache
            </h3>
        </div>
        <div class="p-6">
            <p class="text-gray-600 mb-4">Clear system cache to free up space and refresh data.</p>
            <button onclick="clearCache()" 
                class="action-btn px-6 py-3 bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 text-white rounded-xl transition-all shadow-lg font-medium">
                <span class="material-icons text-sm align-middle mr-2">delete_sweep</span>
                Clear Cache
            </button>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Settings Form Submit
    document.getElementById('settingsForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(e.target);
        
        try {
            const response = await fetch('actions/save_settings.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('Settings saved successfully!', 'success');
            } else {
                showNotification(data.message || 'Failed to save settings', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
        }
    });
});

// Backup Database
async function backupDatabase() {
    if (!confirm('Create a database backup? This may take a few moments.')) {
        return;
    }
    
    try {
        const response = await fetch('actions/backup_database.php', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Database backup created successfully!', 'success');
            if (data.download_url) {
                window.open(data.download_url, '_blank');
            }
        } else {
            showNotification(data.message || 'Failed to create backup', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}

// Clear Cache
async function clearCache() {
    if (!confirm('Clear all cache? This action cannot be undone.')) {
        return;
    }
    
    try {
        const response = await fetch('actions/clear_cache.php', {
            method: 'POST'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showNotification('Cache cleared successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to clear cache', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showNotification('An error occurred. Please try again.', 'error');
    }
}
</script>