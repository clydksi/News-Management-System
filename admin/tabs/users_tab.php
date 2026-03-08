<!-- Users Management Tab — Purple Editorial Edition -->
<style>
.u-avatar {
    width: 36px; height: 36px; border-radius: 9px;
    background: var(--purple); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
    box-shadow: 0 2px 8px var(--purple-glow);
}
.ps-checkbox { width:15px;height:15px;accent-color:var(--purple);cursor:pointer }
@keyframes spin { to { transform: rotate(360deg); } }

/* Filter bar */
.usr-filter-bar{display:flex;flex-wrap:wrap;gap:10px;padding:14px 18px;background:var(--canvas);border-bottom:1px solid var(--border)}
.usr-filter-bar .form-input,.usr-filter-bar .form-select{height:36px;font-size:12px;padding:0 10px}
.usr-filter-bar .form-select{padding-right:28px}

/* Bulk action bar */
.bulk-bar{
    position:sticky;bottom:0;left:0;right:0;
    display:none;align-items:center;gap:10px;
    background:var(--ink);color:#fff;
    padding:12px 20px;border-radius:12px 12px 0 0;
    box-shadow:0 -4px 24px rgba(0,0,0,.22);
    z-index:20;
}
.bulk-bar.visible{display:flex}
.bulk-bar-count{font-family:'Fira Code',monospace;font-size:12px;font-weight:700;padding:3px 9px;border-radius:6px;background:rgba(255,255,255,.12)}
.bulk-btn{border:none;border-radius:7px;font-size:11px;font-weight:600;padding:6px 13px;cursor:pointer;display:flex;align-items:center;gap:4px;transition:all .15s}
.bulk-btn .material-icons-round{font-size:13px!important}
.bulk-activate{background:#ECFDF5;color:#059669}.bulk-activate:hover{background:#059669;color:#fff}
.bulk-deactivate{background:#FFFBEB;color:#D97706}.bulk-deactivate:hover{background:#D97706;color:#fff}
.bulk-delete{background:#FFF1F2;color:#DC2626}.bulk-delete:hover{background:#DC2626;color:#fff}

/* Inline status toggle */
.status-toggle{border:none;border-radius:6px;font-size:10px;font-weight:700;padding:3px 9px;cursor:pointer;display:inline-flex;align-items:center;gap:3px;transition:all .15s;font-family:'Fira Code',monospace}
.status-toggle .material-icons-round{font-size:11px!important}
.status-toggle.is-active{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0}
.status-toggle.is-active:hover{background:#DC2626;color:#fff;border-color:#DC2626}
.status-toggle.is-inactive{background:#FFF1F2;color:#DC2626;border:1px solid #FECACA}
.status-toggle.is-inactive:hover{background:#059669;color:#fff;border-color:#059669}
</style>

<div class="section-hd">
    <div class="section-hd-l">
        <div class="section-hd-icon">
            <span class="material-icons-round">people</span>
        </div>
        <div>
            <div class="section-hd-title">Users Management</div>
            <div class="section-hd-sub">Manage system users and their permissions</div>
        </div>
    </div>
    <div class="section-hd-r">
        <button onclick="openModal('addUserModal')" class="btn btn-purple">
            <span class="material-icons-round">person_add</span>
            Add New User
        </button>
    </div>
</div>

<!-- Server-side filter bar -->
<form method="GET" action="" class="usr-filter-bar">
    <input type="hidden" name="tab" value="users"/>
    <div style="position:relative;flex:1;min-width:160px">
        <span class="material-icons-round" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:15px!important;color:var(--ink-faint);pointer-events:none">search</span>
        <input type="text" name="uq" value="<?= e($_GET['uq'] ?? '') ?>"
               placeholder="Search username…"
               class="form-input" style="padding-left:30px;width:100%;box-sizing:border-box"/>
    </div>
    <select name="urole" class="form-select" style="min-width:120px">
        <option value="">All Roles</option>
        <option value="user"       <?= ($_GET['urole'] ?? '') === 'user'       ? 'selected' : '' ?>>User</option>
        <option value="admin"      <?= ($_GET['urole'] ?? '') === 'admin'      ? 'selected' : '' ?>>Admin</option>
        <option value="superadmin" <?= ($_GET['urole'] ?? '') === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
    </select>
    <select name="ustatus" class="form-select" style="min-width:120px">
        <option value="">All Status</option>
        <option value="1" <?= isset($_GET['ustatus']) && $_GET['ustatus'] === '1' ? 'selected' : '' ?>>Active</option>
        <option value="0" <?= isset($_GET['ustatus']) && $_GET['ustatus'] === '0' ? 'selected' : '' ?>>Inactive</option>
    </select>
    <select name="udept" class="form-select" style="min-width:140px">
        <option value="">All Departments</option>
        <?php foreach ($allDepartments as $dept): ?>
        <option value="<?= $dept['id'] ?>" <?= ((int)($_GET['udept'] ?? 0)) === (int)$dept['id'] ? 'selected' : '' ?>><?= e($dept['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-purple btn-sm">
        <span class="material-icons-round">filter_list</span>Filter
    </button>
    <?php if (!empty($_GET['uq']) || !empty($_GET['urole']) || isset($_GET['ustatus']) || !empty($_GET['udept'])): ?>
    <a href="?tab=users" class="btn btn-outline btn-sm" style="color:#DC2626;border-color:#FECACA">
        <span class="material-icons-round">clear</span>Clear
    </a>
    <?php endif; ?>
</form>

<!-- Users Table -->
<div class="data-table-wrap" style="border-radius:0;border:none;border-top:1px solid var(--border)">
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width:32px">
                        <input type="checkbox" class="ps-checkbox" id="selectAllUsers" title="Select all" onchange="toggleSelectAll(this)"/>
                    </th>
                    <th>User</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>View Scope</th>
                    <th>Created</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($users)): ?>
                <?php foreach ($users as $user): ?>
                <tr data-uid="<?= $user['id'] ?>">
                    <td>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <input type="checkbox" class="ps-checkbox user-cb" value="<?= $user['id'] ?>"
                               onchange="updateBulkBar()"/>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;gap:11px">
                            <div class="u-avatar"><?= strtoupper(substr($user['username'], 0, 1)) ?></div>
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--ink)"><?= e($user['username']) ?></div>
                                <div style="font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace">ID: <?= $user['id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-blue">
                            <span class="material-icons-round">business</span>
                            <?= e($user['department'] ?? 'No Dept') ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $roleBadge = [
                            'superadmin' => 'badge-red',
                            'admin'      => 'badge-purple',
                            'user'       => 'badge-green',
                        ][$user['role']] ?? 'badge-gray';
                        $roleIcon = [
                            'superadmin' => 'verified_user',
                            'admin'      => 'manage_accounts',
                            'user'       => 'person',
                        ][$user['role']] ?? 'person';
                        ?>
                        <span class="badge <?= $roleBadge ?>">
                            <span class="material-icons-round"><?= $roleIcon ?></span>
                            <?= ucfirst(e($user['role'])) ?>
                        </span>
                    </td>
                    <td>
                        <!-- Inline status toggle -->
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <button class="status-toggle <?= $user['is_active'] ? 'is-active' : 'is-inactive' ?>"
                                onclick="toggleUserStatus(<?= $user['id'] ?>, <?= $user['is_active'] ?>, this)"
                                title="<?= $user['is_active'] ? 'Click to deactivate' : 'Click to activate' ?>">
                            <span class="material-icons-round"><?= $user['is_active'] ? 'check_circle' : 'cancel' ?></span>
                            <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                        </button>
                        <?php else: ?>
                        <span class="badge badge-green">
                            <span style="width:5px;height:5px;border-radius:50%;background:#10B981;flex-shrink:0"></span>
                            Active
                        </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $scopeBadge = [
                            'all'     => ['badge-green', 'public', 'All Depts'],
                            'granted' => ['badge-orange', 'lock_open', 'Granted'],
                            'own'     => ['badge-gray', 'business', 'Own Only'],
                        ][$user['view_scope'] ?? 'own'] ?? ['badge-gray', 'business', 'Own Only'];
                        ?>
                        <span class="badge <?= $scopeBadge[0] ?>">
                            <span class="material-icons-round"><?= $scopeBadge[1] ?></span>
                            <?= $scopeBadge[2] ?>
                        </span>
                    </td>
                    <td style="font-size:12px;color:var(--ink-faint);font-family:'Fira Code',monospace;white-space:nowrap">
                        <?= date('M d, Y', strtotime($user['created_at'])) ?>
                    </td>
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                            <button onclick="viewUser(<?= $user['id'] ?>)"
                                    class="btn btn-sm btn-icon" style="border:1px solid var(--border);color:#2563EB;background:transparent"
                                    title="View Details">
                                <span class="material-icons-round">visibility</span>
                            </button>
                            <button onclick="editUser(<?= $user['id'] ?>)"
                                    class="btn btn-sm btn-icon btn-outline"
                                    title="Edit User">
                                <span class="material-icons-round">edit</span>
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= e($user['username']) ?>')"
                                    class="btn btn-sm btn-icon btn-red"
                                    title="Delete User">
                                <span class="material-icons-round">delete</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <span class="material-icons-round">people_outline</span>
                            </div>
                            <div class="empty-title">No Users Found</div>
                            <div class="empty-sub">
                                <?php if (!empty($_GET['uq']) || !empty($_GET['urole'])): ?>
                                No users match your filters. <a href="?tab=users" style="color:var(--purple)">Clear filters</a>
                                <?php else: ?>
                                Add a user to get started.
                                <?php endif; ?>
                            </div>
                            <button onclick="openModal('addUserModal')" class="btn btn-purple">
                                <span class="material-icons-round">person_add</span>Add New User
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination — preserve filter params -->
    <?php
    $uQS = http_build_query(array_filter([
        'tab'     => 'users',
        'uq'      => $_GET['uq']      ?? '',
        'urole'   => $_GET['urole']   ?? '',
        'ustatus' => $_GET['ustatus'] ?? '',
        'udept'   => $_GET['udept']   ?? '',
    ], fn($v) => $v !== ''));
    ?>
    <?php if ($totalPages > 1): ?>
    <div class="pg-bar">
        <div class="pg-info">
            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
            &nbsp;·&nbsp;
            <strong><?= $totalUsers ?></strong> users
        </div>
        <div class="pg-btns">
            <?php if ($page > 1): ?>
            <a href="?<?= $uQS ?>&page=<?= $page-1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?<?= $uQS ?>&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?<?= $uQS ?>&page=<?= $page+1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Bulk Action Bar -->
<div class="bulk-bar" id="bulkBar">
    <span class="bulk-bar-count" id="bulkCount">0 selected</span>
    <div style="flex:1"></div>
    <button class="bulk-btn bulk-activate" onclick="doBulkAction('activate')">
        <span class="material-icons-round">check_circle</span>Activate
    </button>
    <button class="bulk-btn bulk-deactivate" onclick="doBulkAction('deactivate')">
        <span class="material-icons-round">cancel</span>Deactivate
    </button>
    <button class="bulk-btn bulk-delete" onclick="doBulkAction('delete')">
        <span class="material-icons-round">delete_forever</span>Delete
    </button>
    <button class="bulk-btn" style="background:rgba(255,255,255,.1);color:#fff" onclick="clearSelection()">
        <span class="material-icons-round">close</span>
    </button>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD USER MODAL
═══════════════════════════════════════════════════════ -->
<div id="addUserModal" class="modal-bg">
    <div class="modal-box" style="max-width:460px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="font-size:18px!important;color:var(--purple)">person_add</span>
                </div>
                <div>
                    <div class="m-hi-title">Add New User</div>
                    <div class="m-hi-sub">Fill in the details below</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('addUserModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="addUserForm">
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">badge</span>Username</label>
                        <input class="form-input" type="text" name="username" required placeholder="Enter username"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">lock</span>Password</label>
                        <input class="form-input" type="password" name="password" required placeholder="Enter password"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">business</span>Department</label>
                        <select class="form-select" name="department_id" required>
                            <option value="">— Select Department —</option>
                            <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">manage_accounts</span>Role</label>
                        <select class="form-select" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                            <option value="superadmin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:9px;margin-bottom:0">
                        <input class="ps-checkbox" type="checkbox" name="is_active" id="addIsActive" value="1" checked/>
                        <label for="addIsActive" style="font-size:13px;color:var(--ink);cursor:pointer">Active User</label>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('addUserModal')" class="btn btn-outline">Cancel</button>
            <button type="button" onclick="document.getElementById('addUserForm').requestSubmit()" class="btn btn-purple">
                <span class="material-icons-round">add</span>Add User
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     VIEW USER MODAL
═══════════════════════════════════════════════════════ -->
<div id="viewUserModal" class="modal-bg">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:#EFF6FF">
                    <span class="material-icons-round" style="font-size:18px!important;color:#2563EB">account_circle</span>
                </div>
                <div>
                    <div class="m-hi-title">User Details</div>
                    <div class="m-hi-sub" id="viewUserSubtitle">—</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('viewUserModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <div style="display:flex;justify-content:center;margin-bottom:20px">
                    <div id="viewUserAvatarBig" style="width:72px;height:72px;border-radius:18px;background:var(--purple);color:white;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:32px;font-weight:700;box-shadow:0 6px 20px var(--purple-glow)"></div>
                </div>
                <div id="viewUserContent" style="display:flex;flex-direction:column;gap:8px"></div>
            </div>
        </div>
        <div class="m-foot">
            <button onclick="closeModal('viewUserModal')" class="btn btn-outline" style="width:100%">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT USER MODAL
═══════════════════════════════════════════════════════ -->
<div id="editUserModal" class="modal-bg">
    <div class="modal-box" style="max-width:460px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="font-size:18px!important;color:var(--purple)">edit</span>
                </div>
                <div>
                    <div class="m-hi-title">Edit User</div>
                    <div class="m-hi-sub">Update user information</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('editUserModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="editUserForm">
                    <input type="hidden" name="user_id" id="editUserId"/>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">badge</span>Username</label>
                        <input class="form-input" type="text" name="username" id="editUsername" required/>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">lock_reset</span>New Password <span style="font-weight:400;color:var(--ink-faint)">(leave blank to keep)</span></label>
                        <input class="form-input" type="password" name="password" id="editPassword" placeholder="Leave blank to keep current"/>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">business</span>Department</label>
                        <select class="form-select" name="department_id" id="editDepartmentId" required>
                            <option value="">— Select Department —</option>
                            <?php foreach ($allDepartments as $dept): ?>
                            <option value="<?= $dept['id'] ?>"><?= e($dept['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><span class="material-icons-round">manage_accounts</span>Role</label>
                        <select class="form-select" name="role" id="editRole" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                            <?php if ($_SESSION['role'] === 'superadmin'): ?>
                            <option value="superadmin">Super Admin</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:center;gap:9px;margin-bottom:0">
                        <input class="ps-checkbox" type="checkbox" name="is_active" id="editIsActive" value="1"/>
                        <label for="editIsActive" style="font-size:13px;color:var(--ink);cursor:pointer">Active User</label>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('editUserModal')" class="btn btn-outline">Cancel</button>
            <button type="button" onclick="document.getElementById('editUserForm').requestSubmit()" class="btn btn-purple">
                <span class="material-icons-round">save</span>Update User
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
═══════════════════════════════════════════════════════ -->
<div id="deleteUserModal" class="modal-bg">
    <div class="modal-box" style="max-width:400px">
        <div class="m-hd" style="border-bottom:none;padding-bottom:0">
            <div style="flex:1"></div>
            <button class="m-close" onclick="closeModal('deleteUserModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-body" style="padding-top:8px;text-align:center">
            <div style="width:64px;height:64px;border-radius:50%;background:#FFF1F2;border:2px solid #FECDD3;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <span class="material-icons-round" style="font-size:30px!important;color:#DC2626">warning</span>
            </div>
            <div style="font-family:'Playfair Display',serif;font-size:19px;font-weight:700;margin-bottom:8px">Delete User</div>
            <p style="font-size:13px;color:var(--ink-muted);line-height:1.6">
                Are you sure you want to delete <strong id="deleteUsername" style="color:var(--ink)"></strong>?<br/>
                <span style="color:#DC2626;font-size:12px">This action cannot be undone.</span>
            </p>
        </div>
        <div class="m-foot" style="justify-content:center;gap:10px">
            <input type="hidden" id="deleteUserId"/>
            <button onclick="closeModal('deleteUserModal')" class="btn btn-outline" style="min-width:120px">Cancel</button>
            <button onclick="confirmDeleteUser()" class="btn" style="background:#DC2626;color:white;min-width:120px">
                <span class="material-icons-round">delete_forever</span>Delete
            </button>
        </div>
    </div>
</div>

<script>
/* ─── Bulk Selection ──────────────────── */
function toggleSelectAll(cb) {
    document.querySelectorAll('.user-cb').forEach(c => c.checked = cb.checked);
    updateBulkBar();
}
function updateBulkBar() {
    const checked = document.querySelectorAll('.user-cb:checked');
    const bar     = document.getElementById('bulkBar');
    const cnt     = document.getElementById('bulkCount');
    if (checked.length > 0) {
        bar.classList.add('visible');
        cnt.textContent = checked.length + ' selected';
    } else {
        bar.classList.remove('visible');
        document.getElementById('selectAllUsers').checked = false;
    }
}
function clearSelection() {
    document.querySelectorAll('.user-cb, #selectAllUsers').forEach(c => c.checked = false);
    document.getElementById('bulkBar').classList.remove('visible');
}

async function doBulkAction(action) {
    const ids = [...document.querySelectorAll('.user-cb:checked')].map(c => c.value);
    if (!ids.length) return;
    const labels = { activate: 'activate', deactivate: 'deactivate', delete: 'permanently delete' };
    if (!confirm(`Are you sure you want to ${labels[action]} ${ids.length} user(s)?`)) return;
    try {
        const res  = await fetch('actions/bulk_users.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${action}&user_ids=${ids.join(',')}`
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Done!', 'success');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Action failed', 'error');
        }
    } catch (e) { showToast('Network error', 'error'); }
}

/* ─── Inline Status Toggle ────────────── */
async function toggleUserStatus(userId, currentStatus, btn) {
    const newStatus = currentStatus ? 0 : 1;
    btn.disabled = true;
    try {
        const res  = await fetch('actions/toggle_user_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `user_id=${userId}&is_active=${newStatus}`
        });
        const data = await res.json();
        if (data.success) {
            // Update button appearance without reload
            btn.className = newStatus
                ? 'status-toggle is-active'
                : 'status-toggle is-inactive';
            btn.innerHTML = newStatus
                ? '<span class="material-icons-round">check_circle</span>Active'
                : '<span class="material-icons-round">cancel</span>Inactive';
            btn.onclick = () => toggleUserStatus(userId, newStatus, btn);
            btn.title = newStatus ? 'Click to deactivate' : 'Click to activate';
            showToast(data.message || 'Status updated', 'success');
        } else {
            showToast(data.message || 'Failed to update status', 'error');
        }
    } catch (e) { showToast('Network error', 'error'); }
    btn.disabled = false;
}

/* ─── View User ─────────────────────────────────── */
async function viewUser(userId) {
    try {
        const res  = await fetch(`actions/get_user.php?id=${userId}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Failed to load user', 'error'); return; }
        const u = data.user;

        document.getElementById('viewUserAvatarBig').textContent = u.username.charAt(0).toUpperCase();
        document.getElementById('viewUserSubtitle').textContent  = 'ID: ' + u.id;

        const fields = [
            ['badge',           'Username',    u.username],
            ['business',        'Department',  u.department || 'No Department'],
            ['manage_accounts', 'Role',        u.role.charAt(0).toUpperCase() + u.role.slice(1)],
            ['toggle_on',       'Status',      u.is_active == 1 ? 'Active' : 'Inactive'],
            ['public',          'View Scope',  u.view_scope || 'own'],
            ['schedule',        'Created At',  new Date(u.created_at).toLocaleString()],
        ];

        document.getElementById('viewUserContent').innerHTML = fields.map(([ico, lbl, val]) => `
            <div style="background:var(--canvas);border:1px solid var(--border);border-radius:9px;padding:10px 14px;display:flex;align-items:center;gap:10px">
                <span class="material-icons-round" style="font-size:16px!important;color:var(--purple);flex-shrink:0">${ico}</span>
                <div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:2px">${lbl}</div>
                    <div style="font-size:13px;font-weight:600;color:var(--ink)">${escHtml(String(val))}</div>
                </div>
            </div>`).join('');

        openModal('viewUserModal');
    } catch (err) {
        console.error(err);
        showToast('Error loading user details', 'error');
    }
}

/* ─── Edit User ─────────────────────────────────── */
async function editUser(userId) {
    try {
        const res  = await fetch(`actions/get_user.php?id=${userId}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Failed to load user', 'error'); return; }
        const u = data.user;
        document.getElementById('editUserId').value       = u.id;
        document.getElementById('editUsername').value     = u.username;
        document.getElementById('editDepartmentId').value = u.department_id || '';
        document.getElementById('editRole').value         = u.role;
        document.getElementById('editIsActive').checked   = u.is_active == 1;
        document.getElementById('editPassword').value     = '';
        openModal('editUserModal');
    } catch (err) {
        console.error(err);
        showToast('Error loading user details', 'error');
    }
}

/* ─── Delete User ───────────────────────────────── */
function deleteUser(userId, username) {
    document.getElementById('deleteUserId').value         = userId;
    document.getElementById('deleteUsername').textContent = username;
    openModal('deleteUserModal');
}

async function confirmDeleteUser() {
    const userId = document.getElementById('deleteUserId').value;
    try {
        const res  = await fetch('actions/delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'user_id=' + userId,
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'User deleted successfully', 'success');
            closeModal('deleteUserModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to delete user', 'error');
        }
    } catch (err) { showToast('Error deleting user', 'error'); }
}

/* ─── Add User Form ─────────────────────────────── */
document.getElementById('addUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.querySelector('#addUserModal .btn-purple');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
    try {
        const res  = await fetch('actions/add_user.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'User added successfully', 'success');
            closeModal('addUserModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to add user', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">add</span>Add User';
        }
    } catch (err) {
        showToast('Error adding user', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">add</span>Add User';
    }
});

/* ─── Edit User Form ────────────────────────────── */
document.getElementById('editUserForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.querySelector('#editUserModal .btn-purple');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
    try {
        const res  = await fetch('actions/update_user.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'User updated successfully', 'success');
            closeModal('editUserModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to update user', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">save</span>Update User';
        }
    } catch (err) {
        showToast('Error updating user', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">save</span>Update User';
    }
});
</script>
