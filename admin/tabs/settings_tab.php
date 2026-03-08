<!-- System Settings Tab — Purple Editorial Edition -->
<style>
.set-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;margin-bottom:18px}
.set-card-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px}
.set-card-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.set-card-ico .material-icons-round{font-size:18px!important}
.set-card-title{font-family:'Playfair Display',serif;font-size:15px;font-weight:700;color:var(--ink)}
.set-card-sub{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.set-card-body{padding:20px 22px}
.set-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.set-grid{grid-template-columns:1fr}}
.set-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;border-radius:9px;background:var(--canvas);border:1px solid var(--border);margin-bottom:10px}
.set-toggle-row:last-child{margin-bottom:0}
.set-toggle-row:hover{border-color:var(--border-md)}
.set-toggle-label{font-size:13px;font-weight:600;color:var(--ink)}
.set-toggle-sub{font-size:11px;color:var(--ink-faint);margin-top:2px}
/* Custom toggle switch */
.toggle-sw{position:relative;display:inline-flex;align-items:center;width:44px;height:24px;cursor:pointer;flex-shrink:0}
.toggle-sw input{position:absolute;opacity:0;width:0;height:0}
.toggle-track{width:44px;height:24px;background:var(--border-md);border-radius:99px;transition:background .2s;position:relative}
.toggle-track::after{content:'';position:absolute;top:3px;left:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:left .2s}
.toggle-sw input:checked + .toggle-track{background:var(--purple)}
.toggle-sw input:checked + .toggle-track::after{left:23px}
/* Action buttons */
.set-action-row{display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--canvas);border:1px solid var(--border);border-radius:9px;margin-bottom:10px}
.set-action-row:last-child{margin-bottom:0}
.set-action-row:hover{border-color:var(--border-md)}
.set-action-info{flex:1;min-width:0}
.set-action-title{font-size:13px;font-weight:600;color:var(--ink)}
.set-action-desc{font-size:11px;color:var(--ink-faint);margin-top:2px}
</style>

<div class="section-hd">
    <div class="section-hd-l">
        <div class="section-hd-icon">
            <span class="material-icons-round">settings</span>
        </div>
        <div>
            <div class="section-hd-title">System Settings</div>
            <div class="section-hd-sub">Configure your CMS preferences and system behavior</div>
        </div>
    </div>
</div>

<div style="padding:20px 22px">
<form id="settingsForm">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

    <!-- ── Site Information ───────────────────────── -->
    <div class="set-card">
        <div class="set-card-hd">
            <div class="set-card-ico" style="background:#EFF6FF">
                <span class="material-icons-round" style="color:#2563EB">info</span>
            </div>
            <div>
                <div class="set-card-title">Site Information</div>
                <div class="set-card-sub">Basic identity and contact details</div>
            </div>
        </div>
        <div class="set-card-body">
            <div class="set-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">label</span>Site Name</label>
                    <input type="text" name="site_name" class="form-input"
                           value="<?= e($settings['site_name'] ?? 'News CMS') ?>" placeholder="My News CMS"/>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">email</span>Site Email</label>
                    <input type="email" name="site_email" class="form-input"
                           value="<?= e($settings['site_email'] ?? '') ?>" placeholder="admin@example.com"/>
                </div>
                <div class="form-group" style="margin-bottom:0;grid-column:1/-1">
                    <label class="form-label"><span class="material-icons-round">description</span>Site Description</label>
                    <textarea name="site_description" rows="2" class="form-input" style="resize:vertical"
                              placeholder="A brief description of your CMS"><?= e($settings['site_description'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Pagination ─────────────────────────────── -->
    <div class="set-card">
        <div class="set-card-hd">
            <div class="set-card-ico" style="background:#ECFDF5">
                <span class="material-icons-round" style="color:#059669">view_list</span>
            </div>
            <div>
                <div class="set-card-title">Pagination Settings</div>
                <div class="set-card-sub">Control how many items appear per page</div>
            </div>
        </div>
        <div class="set-card-body">
            <div class="set-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">article</span>Articles Per Page</label>
                    <input type="number" name="articles_per_page" min="5" max="100" class="form-input"
                           value="<?= e($settings['articles_per_page'] ?? '10') ?>"/>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">people</span>Users Per Page</label>
                    <input type="number" name="users_per_page" min="5" max="100" class="form-input"
                           value="<?= e($settings['users_per_page'] ?? '10') ?>"/>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Article Settings ───────────────────────── -->
    <div class="set-card">
        <div class="set-card-hd">
            <div class="set-card-ico" style="background:#FFF7ED">
                <span class="material-icons-round" style="color:#EA580C">article</span>
            </div>
            <div>
                <div class="set-card-title">Article Settings</div>
                <div class="set-card-sub">Article workflow and interaction controls</div>
            </div>
        </div>
        <div class="set-card-body" style="padding-bottom:10px">
            <div class="set-toggle-row">
                <div>
                    <div class="set-toggle-label">Require Approval for Articles</div>
                    <div class="set-toggle-sub">New articles need admin approval before publishing</div>
                </div>
                <label class="toggle-sw">
                    <input type="checkbox" name="require_approval" value="1"
                           <?= ($settings['require_approval'] ?? '0') == '1' ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                </label>
            </div>
            <div class="set-toggle-row">
                <div>
                    <div class="set-toggle-label">Enable Comments</div>
                    <div class="set-toggle-sub">Allow users to comment on articles</div>
                </div>
                <label class="toggle-sw">
                    <input type="checkbox" name="enable_comments" value="1"
                           <?= ($settings['enable_comments'] ?? '0') == '1' ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                </label>
            </div>
        </div>
    </div>

    <!-- ── File Upload ────────────────────────────── -->
    <div class="set-card">
        <div class="set-card-hd">
            <div class="set-card-ico" style="background:#FFF1F2">
                <span class="material-icons-round" style="color:#DC2626">upload_file</span>
            </div>
            <div>
                <div class="set-card-title">File Upload Settings</div>
                <div class="set-card-sub">Control what files users can upload</div>
            </div>
        </div>
        <div class="set-card-body">
            <div class="set-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">storage</span>Max File Size (MB)</label>
                    <input type="number" name="max_file_size" min="1" max="100" class="form-input"
                           value="<?= e($settings['max_file_size'] ?? '10') ?>"/>
                </div>
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><span class="material-icons-round">extension</span>Allowed File Types</label>
                    <input type="text" name="allowed_file_types" class="form-input"
                           value="<?= e($settings['allowed_file_types'] ?? 'jpg,jpeg,png,gif,pdf,doc,docx') ?>"
                           placeholder="jpg,png,pdf"/>
                    <div style="font-size:10px;color:var(--ink-faint);margin-top:4px">Separate extensions with commas</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Email Notifications ────────────────────── -->
    <div class="set-card">
        <div class="set-card-hd">
            <div class="set-card-ico" style="background:var(--purple-light)">
                <span class="material-icons-round" style="color:var(--purple)">email</span>
            </div>
            <div>
                <div class="set-card-title">Email Notifications</div>
                <div class="set-card-sub">Automatic email alerts for system events</div>
            </div>
        </div>
        <div class="set-card-body" style="padding-bottom:10px">
            <div class="set-toggle-row">
                <div>
                    <div class="set-toggle-label">New User Registration</div>
                    <div class="set-toggle-sub">Send email when a new user is registered</div>
                </div>
                <label class="toggle-sw">
                    <input type="checkbox" name="email_new_user" value="1"
                           <?= ($settings['email_new_user'] ?? '0') == '1' ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                </label>
            </div>
            <div class="set-toggle-row">
                <div>
                    <div class="set-toggle-label">New Article Published</div>
                    <div class="set-toggle-sub">Send email when a new article is published</div>
                </div>
                <label class="toggle-sw">
                    <input type="checkbox" name="email_new_article" value="1"
                           <?= ($settings['email_new_article'] ?? '0') == '1' ? 'checked' : '' ?>>
                    <div class="toggle-track"></div>
                </label>
            </div>
        </div>
    </div>

    <!-- ── Maintenance Mode ───────────────────────── -->
    <?php $maintActive = ($settings['maintenance_mode'] ?? '0') == '1'; ?>
    <div class="set-card" id="maintenanceCard" style="<?= $maintActive ? 'border-color:#FCD34D;box-shadow:0 0 0 3px rgba(253,211,77,.2)' : '' ?>">
        <div class="set-card-hd" style="<?= $maintActive ? 'background:#FFFBEB' : '' ?>">
            <div class="set-card-ico" style="background:#FFFBEB">
                <span class="material-icons-round" style="color:#D97706">construction</span>
            </div>
            <div>
                <div class="set-card-title">Maintenance Mode</div>
                <div class="set-card-sub">Take the system offline for regular users</div>
            </div>
            <?php if ($maintActive): ?>
            <span style="margin-left:auto;padding:3px 10px;border-radius:99px;background:#FEF3C7;color:#92400E;font-size:10px;font-weight:700;font-family:'Fira Code',monospace;border:1px solid #FCD34D;animation:pulse-dot 1.5s infinite">● ACTIVE</span>
            <?php endif; ?>
        </div>
        <div class="set-card-body" style="padding-bottom:10px">
            <?php if ($maintActive): ?>
            <div style="padding:10px 14px;border-radius:8px;background:#FEF3C7;border:1px solid #FCD34D;margin-bottom:12px;display:flex;align-items:center;gap:8px;font-size:12px;color:#92400E">
                <span class="material-icons-round" style="font-size:16px!important;color:#D97706">warning</span>
                <span><strong>Maintenance is ON.</strong> Regular users see a maintenance page. Admins &amp; superadmins are unaffected.</span>
            </div>
            <?php endif; ?>
            <div class="set-toggle-row" id="maintenanceRow" style="border-color:<?= $maintActive ? '#FCD34D' : 'var(--border)' ?>;background:<?= $maintActive ? '#FFFBEB' : 'var(--canvas)' ?>">
                <div>
                    <div class="set-toggle-label">Enable Maintenance Mode</div>
                    <div class="set-toggle-sub">Show maintenance page to all users except admins &amp; superadmins</div>
                </div>
                <label class="toggle-sw">
                    <input type="checkbox" name="maintenance_mode" id="maintenanceToggle" value="1"
                           <?= $maintActive ? 'checked' : '' ?>
                           onchange="onMaintenanceToggle(this)">
                    <div class="toggle-track" style="<?= $maintActive ? 'background:#D97706' : '' ?>" id="maintenanceTrack"></div>
                </label>
            </div>
        </div>
    </div>

    <!-- Save / Cancel buttons -->
    <div style="display:flex;justify-content:flex-end;gap:10px;padding-bottom:4px">
        <button type="button" onclick="location.href='?tab=settings'" class="btn btn-outline">
            <span class="material-icons-round">undo</span>Reset
        </button>
        <button type="submit" class="btn btn-purple" id="settingsSaveBtn">
            <span class="material-icons-round">save</span>Save Settings
        </button>
    </div>

</form>

<!-- ── System Tools ───────────────────────────── -->
<div class="set-card" style="margin-top:8px">
    <div class="set-card-hd">
        <div class="set-card-ico" style="background:var(--canvas)">
            <span class="material-icons-round" style="color:var(--ink-faint)">build</span>
        </div>
        <div>
            <div class="set-card-title">System Tools</div>
            <div class="set-card-sub">Database management and cache utilities</div>
        </div>
    </div>
    <div class="set-card-body" style="padding-bottom:10px">
        <div class="set-action-row">
            <div class="set-card-ico" style="background:#EFF6FF">
                <span class="material-icons-round" style="color:#2563EB">cloud_download</span>
            </div>
            <div class="set-action-info">
                <div class="set-action-title">Database Backup</div>
                <div class="set-action-desc">Create a downloadable backup of your entire database</div>
            </div>
            <button onclick="settingsBackup(this)" class="btn btn-sm" style="background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE;flex-shrink:0">
                <span class="material-icons-round">backup</span>Create Backup
            </button>
        </div>
        <div class="set-action-row">
            <div class="set-card-ico" style="background:#FFF7ED">
                <span class="material-icons-round" style="color:#EA580C">cached</span>
            </div>
            <div class="set-action-info">
                <div class="set-action-title">Clear System Cache</div>
                <div class="set-action-desc">Flush cached data to free up space and refresh content</div>
            </div>
            <button onclick="settingsClearCache(this)" class="btn btn-sm" style="background:#FFF7ED;color:#EA580C;border:1px solid #FED7AA;flex-shrink:0">
                <span class="material-icons-round">delete_sweep</span>Clear Cache
            </button>
        </div>
    </div>
</div>

</div><!-- end padding shell -->

<script>
const _csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

document.getElementById('settingsForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('settingsSaveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
    try {
        const res  = await fetch('actions/save_settings.php', { method: 'POST', body: new FormData(e.target) });
        const data = await res.json();
        if (data.success) {
            showToast('Settings saved successfully!', 'success');
            // Reload tab after short delay so maintenance banner + state refresh
            setTimeout(() => location.href = '?tab=settings', 1000);
        } else {
            showToast(data.message || 'Failed to save settings', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">save</span>Save Settings';
        }
    } catch (err) {
        showToast('An error occurred. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">save</span>Save Settings';
    }
});

function onMaintenanceToggle(cb) {
    const row   = document.getElementById('maintenanceRow');
    const track = document.getElementById('maintenanceTrack');
    if (cb.checked) {
        row.style.borderColor   = '#FCD34D';
        row.style.background    = '#FFFBEB';
        track.style.background  = '#D97706';
    } else {
        row.style.borderColor   = 'var(--border)';
        row.style.background    = 'var(--canvas)';
        track.style.background  = '';
    }
}

async function settingsBackup(btn) {
    if (!confirm('Create a database backup? This may take a few moments.')) return;
    btn.disabled = true; btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Creating…';
    try {
        const fd = new FormData();
        fd.append('csrf_token', _csrf());
        const res  = await fetch('actions/backup_database.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast('Backup created: ' + (data.filename || 'file'), 'success');
            if (data.download_url) {
                const a = Object.assign(document.createElement('a'), { href: data.download_url });
                document.body.appendChild(a); a.click(); a.remove();
            }
        } else {
            showToast(data.message || 'Failed to create backup', 'error');
        }
    } catch (err) { showToast('An error occurred. Please try again.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">backup</span>Create Backup';
}

async function settingsClearCache(btn) {
    if (!confirm('Clear all cache? This action cannot be undone.')) return;
    btn.disabled = true; btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Clearing…';
    try {
        const fd = new FormData();
        fd.append('csrf_token', _csrf());
        const res  = await fetch('actions/clear_cache.php', { method: 'POST', body: fd });
        const data = await res.json();
        showToast(data.success ? 'Cache cleared successfully!' : (data.message || 'Failed to clear cache'), data.success ? 'success' : 'error');
    } catch (err) { showToast('An error occurred. Please try again.', 'error'); }
    btn.disabled = false; btn.innerHTML = '<span class="material-icons-round">delete_sweep</span>Clear Cache';
}
</script>
