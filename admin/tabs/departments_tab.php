<!-- Departments Management Tab — Purple Editorial Edition -->
<div class="section-hd">
    <div class="section-hd-l">
        <div class="section-hd-icon">
            <span class="material-icons-round">business</span>
        </div>
        <div>
            <div class="section-hd-title">Departments Management</div>
            <div class="section-hd-sub">Manage organizational departments</div>
        </div>
    </div>
    <div class="section-hd-r">
        <button onclick="openModal('addDepartmentModal')" class="btn btn-purple">
            <span class="material-icons-round">add_business</span>
            Add Department
        </button>
    </div>
</div>

<!-- Departments Table -->
<div class="data-table-wrap" style="border-radius:0;border:none;border-top:1px solid var(--border)">
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th>Users</th>
                    <th>Articles</th>
                    <th>Activity</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($departments)): ?>
                <?php foreach ($departments as $dept):
                    $ratio = ($dept['article_count'] ?? 0) > 0
                        ? min(100, round(($dept['article_count'] / max(1, max(array_column($departments, 'article_count')))) * 100))
                        : 0;
                ?>
                <tr>
                    <!-- Dept name + avatar -->
                    <td>
                        <div style="display:flex;align-items:center;gap:11px">
                            <div class="dept-avatar">
                                <?= strtoupper(substr($dept['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <div style="font-weight:600;font-size:13px;color:var(--ink)"><?= e($dept['name']) ?></div>
                                <div style="font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace">ID: <?= $dept['id'] ?></div>
                            </div>
                        </div>
                    </td>
                    <!-- Users count -->
                    <td>
                        <span class="badge badge-green">
                            <span class="material-icons-round">group</span>
                            <?= number_format($dept['user_count'] ?? 0) ?>
                        </span>
                    </td>
                    <!-- Articles count -->
                    <td>
                        <span class="badge badge-purple">
                            <span class="material-icons-round">article</span>
                            <?= number_format($dept['article_count'] ?? 0) ?>
                        </span>
                    </td>
                    <!-- Activity bar -->
                    <td style="min-width:110px">
                        <div style="display:flex;align-items:center;gap:8px">
                            <div style="flex:1;height:6px;border-radius:99px;background:var(--border);overflow:hidden">
                                <div style="height:100%;width:<?= $ratio ?>%;background:linear-gradient(90deg,var(--purple),#A78BFA);border-radius:99px;transition:width .4s"></div>
                            </div>
                            <span style="font-size:10px;font-family:'Fira Code',monospace;color:var(--ink-faint);white-space:nowrap"><?= $ratio ?>%</span>
                        </div>
                    </td>
                    <!-- Actions -->
                    <td>
                        <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                            <button onclick="viewDepartment(<?= $dept['id'] ?>)"
                                    class="btn btn-sm btn-icon" style="border:1px solid var(--border);color:#2563EB;background:transparent"
                                    title="View Details">
                                <span class="material-icons-round">visibility</span>
                            </button>
                            <button onclick="editDepartment(<?= $dept['id'] ?>)"
                                    class="btn btn-sm btn-icon btn-outline"
                                    title="Edit Department">
                                <span class="material-icons-round">edit</span>
                            </button>
                            <button onclick="deleteDepartment(<?= $dept['id'] ?>, '<?= e($dept['name']) ?>', <?= (int)($dept['user_count'] ?? 0) ?>, <?= (int)($dept['article_count'] ?? 0) ?>)"
                                    class="btn btn-sm btn-icon btn-red"
                                    title="Delete Department">
                                <span class="material-icons-round">delete</span>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="empty-icon">
                                <span class="material-icons-round">business_center</span>
                            </div>
                            <div class="empty-title">No Departments Found</div>
                            <div class="empty-sub">Create a department to organise your users and articles.</div>
                            <button onclick="openModal('addDepartmentModal')" class="btn btn-purple">
                                <span class="material-icons-round">add_business</span>Add Department
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pg-bar">
        <div class="pg-info">
            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
        </div>
        <div class="pg-btns">
            <?php if ($page > 1): ?>
            <a href="?tab=departments&page=<?= $page-1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?tab=departments&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?tab=departments&page=<?= $page+1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.dept-avatar {
    width: 36px; height: 36px; border-radius: 9px;
    background: linear-gradient(135deg, #3B82F6, #2563EB);
    color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(37,99,235,.28);
}
</style>

<!-- ═══════════════════════════════════════════════════════
     ADD DEPARTMENT MODAL
═══════════════════════════════════════════════════════ -->
<div id="addDepartmentModal" class="modal-bg">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="font-size:18px!important;color:var(--purple)">add_business</span>
                </div>
                <div>
                    <div class="m-hi-title">Add New Department</div>
                    <div class="m-hi-sub">2–100 characters, letters/numbers/spaces/hyphens</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('addDepartmentModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="addDepartmentForm">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">
                            <span class="material-icons-round">badge</span>Department Name
                        </label>
                        <input class="form-input" type="text" name="name" required
                               maxlength="100" minlength="2"
                               placeholder="e.g. Editorial, Sports, Finance…"/>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('addDepartmentModal')" class="btn btn-outline">Cancel</button>
            <button type="button" id="addDeptSubmitBtn"
                    onclick="document.getElementById('addDepartmentForm').requestSubmit()"
                    class="btn btn-purple">
                <span class="material-icons-round">add</span>Add Department
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     VIEW DEPARTMENT MODAL
═══════════════════════════════════════════════════════ -->
<div id="viewDepartmentModal" class="modal-bg">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:#EFF6FF">
                    <span class="material-icons-round" style="font-size:18px!important;color:#2563EB">business</span>
                </div>
                <div>
                    <div class="m-hi-title">Department Details</div>
                    <div class="m-hi-sub" id="viewDeptSubtitle">—</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('viewDepartmentModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <!-- Big avatar -->
                <div style="display:flex;justify-content:center;margin-bottom:20px">
                    <div id="viewDeptAvatarBig"
                         style="width:72px;height:72px;border-radius:18px;background:linear-gradient(135deg,#3B82F6,#2563EB);color:white;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-size:32px;font-weight:700;box-shadow:0 6px 20px rgba(37,99,235,.3)">
                    </div>
                </div>
                <!-- Stat tiles -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
                    <div style="background:var(--canvas);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:4px">Users</div>
                        <div id="viewDeptUsers" style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:#059669"></div>
                    </div>
                    <div style="background:var(--canvas);border:1px solid var(--border);border-radius:10px;padding:14px;text-align:center">
                        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:4px">Articles</div>
                        <div id="viewDeptArticles" style="font-family:'Playfair Display',serif;font-size:28px;font-weight:700;color:var(--purple)"></div>
                    </div>
                </div>
                <!-- Fields -->
                <div id="viewDeptFields"></div>
            </div>
        </div>
        <div class="m-foot">
            <button onclick="closeModal('viewDepartmentModal')" class="btn btn-outline" style="width:100%">Close</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT DEPARTMENT MODAL
═══════════════════════════════════════════════════════ -->
<div id="editDepartmentModal" class="modal-bg">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="font-size:18px!important;color:var(--purple)">edit</span>
                </div>
                <div>
                    <div class="m-hi-title">Edit Department</div>
                    <div class="m-hi-sub">Update department name</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('editDepartmentModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="editDepartmentForm">
                    <input type="hidden" name="department_id" id="editDepartmentId"/>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">
                            <span class="material-icons-round">badge</span>Department Name
                        </label>
                        <input class="form-input" type="text" name="name" id="editDeptNameInput"
                               required maxlength="100" minlength="2"/>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('editDepartmentModal')" class="btn btn-outline">Cancel</button>
            <button type="button" id="editDeptSubmitBtn"
                    onclick="document.getElementById('editDepartmentForm').requestSubmit()"
                    class="btn btn-purple">
                <span class="material-icons-round">save</span>Update
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
═══════════════════════════════════════════════════════ -->
<div id="deleteDepartmentModal" class="modal-bg">
    <div class="modal-box" style="max-width:400px">
        <div class="m-hd" style="border-bottom:none;padding-bottom:0">
            <div style="flex:1"></div>
            <button class="m-close" onclick="closeModal('deleteDepartmentModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-body" style="padding-top:8px;text-align:center">
            <div style="width:64px;height:64px;border-radius:50%;background:#FFF1F2;border:2px solid #FECDD3;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                <span class="material-icons-round" style="font-size:30px!important;color:#DC2626">warning</span>
            </div>
            <div style="font-family:'Playfair Display',serif;font-size:19px;font-weight:700;margin-bottom:8px">Delete Department</div>
            <p style="font-size:13px;color:var(--ink-muted);line-height:1.6">
                Are you sure you want to delete <strong id="deleteDeptNameDisplay" style="color:var(--ink)"></strong>?<br/>
                <span style="color:#DC2626;font-size:12px">This action cannot be undone.</span>
            </p>
            <!-- Impact warning — shown when dept has users/articles -->
            <div id="deleteDeptImpact" style="display:none;margin-top:14px;padding:11px 14px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:9px;text-align:left">
                <div style="display:flex;align-items:flex-start;gap:8px">
                    <span class="material-icons-round" style="font-size:16px!important;color:#D97706;flex-shrink:0;margin-top:1px">info</span>
                    <div style="font-size:12px;color:#92400E;line-height:1.6">
                        This department contains
                        <strong id="deleteDeptUserCount"></strong> user(s) and
                        <strong id="deleteDeptArticleCount"></strong> article(s) which may be affected.
                    </div>
                </div>
            </div>
        </div>
        <div class="m-foot" style="justify-content:center;gap:10px">
            <input type="hidden" id="deleteDepartmentId"/>
            <button onclick="closeModal('deleteDepartmentModal')" class="btn btn-outline" style="min-width:120px">Cancel</button>
            <button id="deleteDeptConfirmBtn" onclick="confirmDeleteDepartment()"
                    class="btn" style="background:#DC2626;color:white;min-width:120px">
                <span class="material-icons-round">delete_forever</span>Delete
            </button>
        </div>
    </div>
</div>

<script>
/* ─── View Department ───────────────────── */
async function viewDepartment(deptId) {
    try {
        const res  = await fetch(`actions/get_department.php?id=${deptId}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Failed to load department', 'error'); return; }
        const d = data.department;

        document.getElementById('viewDeptAvatarBig').textContent = d.name.charAt(0).toUpperCase();
        document.getElementById('viewDeptSubtitle').textContent  = 'ID: ' + d.id;
        document.getElementById('viewDeptUsers').textContent    = d.user_count    || 0;
        document.getElementById('viewDeptArticles').textContent = d.article_count || 0;

        const fields = [
            ['badge',   'Department Name', d.name],
            ['schedule','Created At',      d.created_at ? new Date(d.created_at).toLocaleString() : '—'],
        ];
        document.getElementById('viewDeptFields').innerHTML = fields.map(([ico, lbl, val]) => `
            <div style="background:var(--canvas);border:1px solid var(--border);border-radius:9px;padding:10px 14px;display:flex;align-items:center;gap:10px;margin-bottom:8px">
                <span class="material-icons-round" style="font-size:16px!important;color:#2563EB;flex-shrink:0">${ico}</span>
                <div>
                    <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:2px">${lbl}</div>
                    <div style="font-size:13px;font-weight:600;color:var(--ink)">${escHtml(String(val))}</div>
                </div>
            </div>`).join('');

        openModal('viewDepartmentModal');
    } catch (err) {
        console.error(err);
        showToast('Error loading department details', 'error');
    }
}

/* ─── Edit Department ───────────────────── */
async function editDepartment(deptId) {
    try {
        const res  = await fetch(`actions/get_department.php?id=${deptId}`);
        const data = await res.json();
        if (!data.success) { showToast(data.message || 'Failed to load department', 'error'); return; }
        const d = data.department;
        document.getElementById('editDepartmentId').value    = d.id;
        document.getElementById('editDeptNameInput').value   = d.name;
        openModal('editDepartmentModal');
    } catch (err) {
        console.error(err);
        showToast('Error loading department details', 'error');
    }
}

/* ─── Delete Department ─────────────────── */
function deleteDepartment(deptId, deptName, userCount, articleCount) {
    document.getElementById('deleteDepartmentId').value      = deptId;
    document.getElementById('deleteDeptNameDisplay').textContent = deptName;

    const impact = document.getElementById('deleteDeptImpact');
    if (userCount > 0 || articleCount > 0) {
        document.getElementById('deleteDeptUserCount').textContent    = userCount;
        document.getElementById('deleteDeptArticleCount').textContent = articleCount;
        impact.style.display = 'block';
    } else {
        impact.style.display = 'none';
    }

    openModal('deleteDepartmentModal');
}

async function confirmDeleteDepartment() {
    const deptId = document.getElementById('deleteDepartmentId').value;
    const btn    = document.getElementById('deleteDeptConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Deleting…';
    try {
        const res  = await fetch('actions/delete_department.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'department_id=' + deptId,
        });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Department deleted', 'success');
            closeModal('deleteDepartmentModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to delete department', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">delete_forever</span>Delete';
        }
    } catch (err) {
        showToast('Error deleting department', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">delete_forever</span>Delete';
    }
}

/* ─── Add Department Form ───────────────── */
document.getElementById('addDepartmentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('addDeptSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
    try {
        const res  = await fetch('actions/add_department.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Department added successfully', 'success');
            closeModal('addDepartmentModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to add department', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">add</span>Add Department';
        }
    } catch (err) {
        showToast('Error adding department', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">add</span>Add Department';
    }
});

/* ─── Edit Department Form ──────────────── */
document.getElementById('editDepartmentForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('editDeptSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
    try {
        const res  = await fetch('actions/update_department.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Department updated successfully', 'success');
            closeModal('editDepartmentModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to update department', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">save</span>Update';
        }
    } catch (err) {
        showToast('Error updating department', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">save</span>Update';
    }
});
</script>