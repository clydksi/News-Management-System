<?php
// tabs/access_tab.php
// Variables from admin/index.php (enhanced):
// $allUsers, $allDeptsForAccess, $accessGrants, $deptVisibility
?>

<style>
/* ── Access Tab Local Styles ─────────────────────────────────────────
   Inherits all tokens from admin/index.php.
   Only access-tab-specific rules defined here.
──────────────────────────────────────────────────────────────────── */

/* Section cards */
.ac-section {
    border-bottom: 1px solid var(--border);
}
.ac-section:last-child { border-bottom: none; }
.ac-section-hd {
    padding: 18px 22px 0;
    display: flex; align-items: flex-start; justify-content: space-between; gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.ac-section-title-row { display: flex; align-items: center; gap: 10px; }
.ac-section-icon {
    width: 38px; height: 38px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.ac-section-icon .material-icons-round { font-size: 18px !important; }
.ac-section-title {
    font-family: 'Playfair Display', serif;
    font-size: 16px; color: var(--ink); font-weight: 600;
}
.ac-section-sub { font-size: 11px; color: var(--ink-faint); margin-top: 3px; max-width: 560px; line-height: 1.5; }
.ac-count-pill {
    padding: 4px 12px; border-radius: 99px;
    font-size: 11px; font-weight: 700;
    font-family: 'Fira Code', monospace;
    border: 1px solid; white-space: nowrap; flex-shrink: 0;
}
.ac-count-blue   { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.ac-count-purple { background: var(--purple-light); color: var(--purple-md); border-color: #C4B5FD; }

/* Grant form panels */
.ac-form-body { padding: 0 22px 20px; }
.dept-grant-grid {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 16px;
    align-items: end;
    margin-bottom: 16px;
}
@media(max-width: 700px) {
    .dept-grant-grid { grid-template-columns: 1fr; }
    .dept-arrow-col { display: none; }
}
.user-grant-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 16px;
}
@media(max-width: 700px) { .user-grant-grid { grid-template-columns: 1fr; } }

/* Arrow connector */
.dept-arrow-col {
    display: flex; align-items: center; justify-content: center;
    padding-bottom: 20px;
}
.dept-arrow {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--canvas); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
}
.dept-arrow .material-icons-round { font-size: 18px !important; color: var(--ink-faint); }

/* Helper text below fields */
.field-hint { font-size: 10px; color: var(--ink-faint); margin-top: 4px; font-family: 'Fira Code', monospace; }

/* Tables */
.ac-table-wrap { padding: 0 22px 22px; }
.ac-table-scroll { border-radius: var(--r-sm); border: 1px solid var(--border); overflow: hidden; overflow-x: auto; }
.ac-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.ac-table th {
    background: var(--canvas); padding: 10px 14px;
    text-align: left; font-size: 9px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .1em;
    color: var(--ink-faint); font-family: 'Fira Code', monospace;
    border-bottom: 1px solid var(--border); white-space: nowrap;
}
.ac-table td { padding: 12px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.ac-table tr:last-child td { border-bottom: none; }
.ac-table tr:hover td { background: var(--canvas); }

/* Dept chips */
.dept-chip-blue {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: var(--r-sm);
    background: #EFF6FF; border: 1px solid #BFDBFE;
    color: #1D4ED8; font-size: 11px; font-weight: 600;
    white-space: nowrap;
}
.dept-chip-purple {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: var(--r-sm);
    background: var(--purple-light); border: 1px solid #C4B5FD;
    color: var(--purple-md); font-size: 11px; font-weight: 600;
    white-space: nowrap;
}
.dept-chip-blue .material-icons-round,
.dept-chip-purple .material-icons-round { font-size: 12px !important; }

/* Arrow cell */
.table-arrow { color: var(--border-md); }
.table-arrow .material-icons-round { font-size: 16px !important; }

/* Affected users chip */
.users-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: var(--r-sm);
    background: #ECFDF5; border: 1px solid #A7F3D0;
    color: #065F46; font-size: 11px; font-weight: 600;
}
.users-chip .material-icons-round { font-size: 12px !important; }

/* Scope badge */
.scope-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 99px;
    font-size: 10px; font-weight: 700;
    border: 1px solid; white-space: nowrap;
}
.scope-chip .material-icons-round { font-size: 11px !important; }
.sc-own     { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.sc-granted { background: #FFFBEB; color: #92400E; border-color: #FDE68A; }
.sc-all     { background: #ECFDF5; color: #065F46; border-color: #A7F3D0; }

/* User avatar cell */
.ac-user-cell { display: flex; align-items: center; gap: 9px; }
.ac-avatar {
    width: 28px; height: 28px; border-radius: 8px;
    background: var(--purple); color: white;
    display: flex; align-items: center; justify-content: center;
    font-size: 12px; font-weight: 700; flex-shrink: 0;
}
.ac-username { font-weight: 600; color: var(--ink); font-size: 12px; }

/* Revoke confirmation modal */
#revokeModal     { max-width: 420px; }
#revokeDeptModal { max-width: 460px; }
.revoke-warning {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px; border-radius: var(--r-sm);
    background: #FFF1F2; border: 1px solid #FECDD3;
    margin-bottom: 16px;
}
.revoke-warning .material-icons-round { font-size: 20px !important; color: #DC2626; flex-shrink: 0; }
.revoke-warning-body { font-size: 13px; color: #9F1239; line-height: 1.55; }
.revoke-warning-body strong { color: #7F1D1D; }

/* Empty state inside sections */
.ac-empty {
    padding: 44px 24px; text-align: center;
    border: 2px dashed var(--border); border-radius: var(--r-sm);
    margin: 0 22px 22px;
}
.ac-empty-icon {
    width: 52px; height: 52px; border-radius: 50%;
    background: var(--canvas); border: 1px solid var(--border);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 12px;
}
.ac-empty-icon .material-icons-round { font-size: 22px !important; color: var(--ink-faint); }
.ac-empty-title { font-size: 14px; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
.ac-empty-sub   { font-size: 12px; color: var(--ink-faint); }
</style>

<!-- ══════════════════════════════════════════════════════════════
     REVOKE MODALS — rendered once, reused by JS
═══════════════════════════════════════════════════════════════════ -->

<!-- Individual grant revoke -->
<div class="modal-bg" id="revokeModal">
    <div class="modal-box" style="max-width:420px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:#FFF1F2">
                    <span class="material-icons-round" style="color:#DC2626;font-size:18px!important">remove_moderator</span>
                </div>
                <div>
                    <div class="m-hi-title">Revoke Access</div>
                    <div class="m-hi-sub">Individual user grant</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('revokeModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <div class="revoke-warning">
                    <span class="material-icons-round">warning</span>
                    <div class="revoke-warning-body" id="revokeWarningText">
                        This will remove the user's access to the selected department.
                    </div>
                </div>
                <p style="font-size:12px;color:var(--ink-faint);line-height:1.6">
                    If this was the only cross-department grant for this user, their
                    <code style="font-family:'Fira Code',monospace;font-size:11px;background:var(--canvas);padding:1px 5px;border-radius:3px">view_scope</code>
                    will automatically reset to <strong>own</strong>.
                </p>
            </div>
        </div>
        <div class="m-foot">
            <button class="btn btn-outline" onclick="closeModal('revokeModal')">Cancel</button>
            <form method="POST" action="?tab=access" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"/>
                <input type="hidden" name="access_action" value="revoke"/>
                <input type="hidden" name="grant_id" id="revokeGrantId" value=""/>
                <button type="submit" class="btn btn-red">
                    <span class="material-icons-round">remove_moderator</span>
                    Revoke Access
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Department visibility revoke -->
<div class="modal-bg" id="revokeDeptModal">
    <div class="modal-box" style="max-width:460px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:#FFF1F2">
                    <span class="material-icons-round" style="color:#DC2626;font-size:18px!important">domain_disabled</span>
                </div>
                <div>
                    <div class="m-hi-title">Revoke Department Visibility</div>
                    <div class="m-hi-sub">Department-wide grant</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('revokeDeptModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <div class="revoke-warning">
                    <span class="material-icons-round">warning</span>
                    <div class="revoke-warning-body" id="revokeDeptWarningText">
                        All affected users in this department will lose this visibility.
                    </div>
                </div>
                <p style="font-size:12px;color:var(--ink-faint);line-height:1.6">
                    Users with individual grants for other departments will retain those separately.
                    Only this department-wide visibility link is removed.
                </p>
            </div>
        </div>
        <div class="m-foot">
            <button class="btn btn-outline" onclick="closeModal('revokeDeptModal')">Cancel</button>
            <form method="POST" action="?tab=access" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"/>
                <input type="hidden" name="access_action" value="revoke_dept"/>
                <input type="hidden" name="visibility_id" id="revokeDeptVisId" value=""/>
                <button type="submit" class="btn btn-red">
                    <span class="material-icons-round">domain_disabled</span>
                    Revoke Visibility
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     TAB BODY
═══════════════════════════════════════════════════════════════════ -->

<!-- ── SECTION 1: Department-Wide Visibility ──────────────────────── -->
<div class="ac-section">
    <div class="ac-section-hd">
        <div>
            <div class="ac-section-title-row">
                <div class="ac-section-icon" style="background:#EFF6FF">
                    <span class="material-icons-round" style="color:#2563EB">corporate_fare</span>
                </div>
                <div>
                    <div class="ac-section-title">Department-Wide Visibility</div>
                    <div class="ac-section-sub">
                        Grant an entire department access to see another department's content.
                        Every user in the source department inherits this automatically — no per-user setup needed.
                    </div>
                </div>
            </div>
        </div>
        <span class="ac-count-pill ac-count-blue">
            <?= count($deptVisibility ?? []) ?> active
        </span>
    </div>

    <!-- Grant form -->
    <div class="ac-form-body">
        <form method="POST" action="?tab=access">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"/>
            <input type="hidden" name="access_action" value="grant_dept"/>

            <div class="dept-grant-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        <span class="material-icons-round">business</span>
                        Source Department
                    </label>
                    <select name="source_dept_id" required class="form-select">
                        <option value="">— Who gets access —</option>
                        <?php foreach ($allDeptsForAccess as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">All users in this dept will gain visibility</div>
                </div>

                <div class="dept-arrow-col">
                    <div class="dept-arrow">
                        <span class="material-icons-round">arrow_forward</span>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        <span class="material-icons-round">visibility</span>
                        Target Department
                    </label>
                    <select name="target_dept_id" required class="form-select">
                        <option value="">— What they can see —</option>
                        <?php foreach ($allDeptsForAccess as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint">This dept's data becomes visible to source</div>
                </div>
            </div>

            <button type="submit" class="btn btn-purple">
                <span class="material-icons-round">domain_add</span>
                Grant Department Visibility
            </button>
        </form>
    </div>

    <!-- Dept grants table -->
    <?php if (empty($deptVisibility ?? [])): ?>
    <div class="ac-empty">
        <div class="ac-empty-icon">
            <span class="material-icons-round">corporate_fare</span>
        </div>
        <div class="ac-empty-title">No department-wide grants yet</div>
        <div class="ac-empty-sub">Use the form above to grant cross-department visibility.</div>
    </div>

    <?php else: ?>
    <div class="ac-table-wrap">
        <div class="ac-table-scroll">
            <table class="ac-table">
                <thead>
                    <tr>
                        <th>Source Dept</th>
                        <th style="width:32px"></th>
                        <th>Can See</th>
                        <th>Affected Users</th>
                        <th>Granted By</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptVisibility as $dv): ?>
                    <tr>
                        <td>
                            <span class="dept-chip-blue">
                                <span class="material-icons-round">business</span>
                                <?= e($dv['source_dept_name']) ?>
                            </span>
                        </td>
                        <td class="table-arrow">
                            <span class="material-icons-round">arrow_forward</span>
                        </td>
                        <td>
                            <span class="dept-chip-purple">
                                <span class="material-icons-round">visibility</span>
                                <?= e($dv['target_dept_name']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="users-chip">
                                <span class="material-icons-round">group</span>
                                <?= (int)$dv['affected_users'] ?> <?= $dv['affected_users'] != 1 ? 'users' : 'user' ?>
                            </span>
                        </td>
                        <td style="color:var(--ink-faint);font-size:11px;font-family:'Fira Code',monospace">
                            <?= e($dv['granted_by_name']) ?>
                        </td>
                        <td style="color:var(--ink-faint);font-size:11px;font-family:'Fira Code',monospace;white-space:nowrap">
                            <?= date('M d, Y', strtotime($dv['granted_at'])) ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-red"
                                onclick="openRevokeDeptModal(
                                    <?= (int)$dv['id'] ?>,
                                    '<?= e(addslashes($dv['source_dept_name'])) ?>',
                                    '<?= e(addslashes($dv['target_dept_name'])) ?>',
                                    <?= (int)$dv['affected_users'] ?>
                                )">
                                <span class="material-icons-round">remove_moderator</span>
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div><!-- /section 1 -->


<!-- ── SECTION 2: Individual User Grant Form ──────────────────────── -->
<div class="ac-section">
    <div class="ac-section-hd">
        <div>
            <div class="ac-section-title-row">
                <div class="ac-section-icon" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="color:var(--purple)">lock_open</span>
                </div>
                <div>
                    <div class="ac-section-title">Individual User Grant</div>
                    <div class="ac-section-sub">
                        Grant a single user access to a specific department outside their own.
                        Use this for exceptions that shouldn't apply to the whole department.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="ac-form-body">
        <form method="POST" action="?tab=access">
            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token'] ?? '') ?>"/>
            <input type="hidden" name="access_action" value="grant"/>

            <div class="user-grant-grid">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        <span class="material-icons-round">person</span>
                        User
                    </label>
                    <select name="user_id" required class="form-select">
                        <option value="">— Select a user —</option>
                        <?php foreach ($allUsers as $u): ?>
                        <option value="<?= (int)$u['id'] ?>">
                            <?= e($u['username']) ?><?= !empty($u['dept_name']) ? ' (' . e($u['dept_name']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        <span class="material-icons-round">business</span>
                        Grant Access To
                    </label>
                    <select name="department_id" required class="form-select">
                        <option value="">— Select department —</option>
                        <?php foreach ($allDeptsForAccess as $d): ?>
                        <option value="<?= (int)$d['id'] ?>"><?= e($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label">
                        <span class="material-icons-round">tune</span>
                        View Scope
                    </label>
                    <select name="view_scope" class="form-select">
                        <option value="own">Own dept only</option>
                        <option value="granted" selected>Own + granted depts</option>
                        <option value="all">All depts (superadmin)</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-purple">
                <span class="material-icons-round">add_moderator</span>
                Grant User Access
            </button>
        </form>
    </div>
</div><!-- /section 2 -->


<!-- ── SECTION 3: Active Individual Grants Table ──────────────────── -->
<div class="ac-section">
    <div class="ac-section-hd">
        <div>
            <div class="ac-section-title-row">
                <div class="ac-section-icon" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="color:var(--purple)">manage_accounts</span>
                </div>
                <div>
                    <div class="ac-section-title">Active Individual Grants</div>
                    <div class="ac-section-sub">Per-user cross-department access grants currently in effect.</div>
                </div>
            </div>
        </div>
        <span class="ac-count-pill ac-count-purple">
            <?= count($accessGrants ?? []) ?> active
        </span>
    </div>

    <?php if (empty($accessGrants ?? [])): ?>
    <div class="ac-empty">
        <div class="ac-empty-icon">
            <span class="material-icons-round">lock</span>
        </div>
        <div class="ac-empty-title">No individual grants yet</div>
        <div class="ac-empty-sub">Use the form above to grant per-user access to additional departments.</div>
    </div>

    <?php else: ?>
    <div class="ac-table-wrap">
        <div class="ac-table-scroll">
            <table class="ac-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Own Dept</th>
                        <th>Can Also See</th>
                        <th>Scope</th>
                        <th>Granted By</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accessGrants as $grant): ?>
                    <?php
                        $scope = $grant['view_scope'] ?? 'own';
                        $scopeClass = ['own' => 'sc-own', 'granted' => 'sc-granted', 'all' => 'sc-all'][$scope] ?? 'sc-own';
                        $scopeIcon  = ['own' => 'person', 'granted' => 'lock_open', 'all' => 'public'][$scope] ?? 'person';
                    ?>
                    <tr>
                        <td>
                            <div class="ac-user-cell">
                                <div class="ac-avatar"><?= strtoupper(substr($grant['username'], 0, 1)) ?></div>
                                <span class="ac-username"><?= e($grant['username']) ?></span>
                            </div>
                        </td>
                        <td style="color:var(--ink-faint);font-size:11px;font-family:'Fira Code',monospace">
                            <?= e($grant['own_dept_name'] ?? '—') ?>
                        </td>
                        <td>
                            <span class="dept-chip-purple">
                                <span class="material-icons-round">business</span>
                                <?= e($grant['dept_name']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="scope-chip <?= $scopeClass ?>">
                                <span class="material-icons-round"><?= $scopeIcon ?></span>
                                <?= e($scope) ?>
                            </span>
                        </td>
                        <td style="color:var(--ink-faint);font-size:11px;font-family:'Fira Code',monospace">
                            <?= e($grant['granted_by_name']) ?>
                        </td>
                        <td style="color:var(--ink-faint);font-size:11px;font-family:'Fira Code',monospace;white-space:nowrap">
                            <?= date('M d, Y', strtotime($grant['granted_at'])) ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-red"
                                onclick="openRevokeModal(
                                    <?= (int)$grant['id'] ?>,
                                    '<?= e(addslashes($grant['username'])) ?>',
                                    '<?= e(addslashes($grant['dept_name'])) ?>'
                                )">
                                <span class="material-icons-round">remove_moderator</span>
                                Revoke
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div><!-- /section 3 -->

<script>
// ── Revoke modal: individual grant ────────────────────────────
function openRevokeModal(grantId, username, deptName) {
    document.getElementById('revokeGrantId').value = grantId;
    document.getElementById('revokeWarningText').innerHTML =
        'Removing <strong>' + escHtml(username) + '</strong>\'s access to <strong>' + escHtml(deptName) + '</strong>.';
    openModal('revokeModal');
}

// ── Revoke modal: dept-wide visibility ────────────────────────
function openRevokeDeptModal(visId, sourceDept, targetDept, affectedUsers) {
    document.getElementById('revokeDeptVisId').value = visId;
    const userWord = affectedUsers === 1 ? '1 user' : affectedUsers + ' users';
    document.getElementById('revokeDeptWarningText').innerHTML =
        'Revoking <strong>' + escHtml(sourceDept) + '</strong>\'s visibility of <strong>'
        + escHtml(targetDept) + '</strong>. '
        + '<strong>' + userWord + '</strong> in ' + escHtml(sourceDept) + ' will lose this access.';
    openModal('revokeDeptModal');
}
</script>