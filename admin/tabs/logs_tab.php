<!-- Activity Logs Tab — Purple Editorial Edition -->
<style>
.log-action-ico{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.log-action-ico .material-icons-round{font-size:15px!important}
.log-filter-bar{display:flex;flex-wrap:wrap;gap:10px;padding:14px 18px;background:var(--canvas);border-bottom:1px solid var(--border)}
.log-filter-bar .form-input,.log-filter-bar .form-select{height:36px;font-size:12px;padding:0 10px;width:auto}
.log-filter-bar .form-select{padding-right:28px}
.log-filter-bar > div:first-of-type{flex:1;min-width:160px}
.log-count-chip{padding:3px 10px;border-radius:99px;font-size:10px;font-weight:700;font-family:'Fira Code',monospace;background:var(--purple-light);color:var(--purple)}
</style>

<div class="section-hd">
    <div class="section-hd-l">
        <div class="section-hd-icon">
            <span class="material-icons-round">history</span>
        </div>
        <div>
            <div class="section-hd-title">Activity Logs</div>
            <div class="section-hd-sub">Monitor system activities and user actions</div>
        </div>
    </div>
    <div class="section-hd-r" style="gap:8px">
        <span class="log-count-chip"><?= number_format($totalLogs ?? 0) ?> entries</span>
        <button onclick="exportLogs()" class="btn btn-outline btn-sm">
            <span class="material-icons-round">download</span>Export CSV
        </button>
        <a href="?tab=logs" class="btn btn-outline btn-sm">
            <span class="material-icons-round">refresh</span>Refresh
        </a>
    </div>
</div>

<!-- Server-side filter form -->
<form method="GET" action="" class="log-filter-bar">
    <input type="hidden" name="tab" value="logs"/>
    <div style="position:relative;flex:1;min-width:160px">
        <span class="material-icons-round" style="position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:15px!important;color:var(--ink-faint);pointer-events:none">search</span>
        <input type="text" name="lq" value="<?= e($_GET['lq'] ?? '') ?>"
               placeholder="Search user or description…"
               class="form-input" style="padding-left:30px;width:100%;box-sizing:border-box"/>
    </div>
    <select name="laction" class="form-select" style="min-width:130px">
        <option value="">All Actions</option>
        <?php foreach (['create','update','delete','login','logout'] as $act): ?>
        <option value="<?= $act ?>" <?= ($_GET['laction'] ?? '') === $act ? 'selected' : '' ?>><?= ucfirst($act) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="date" name="lfrom" value="<?= e($_GET['lfrom'] ?? '') ?>"
           class="form-input" style="width:140px" title="From date"/>
    <input type="date" name="lto" value="<?= e($_GET['lto'] ?? '') ?>"
           class="form-input" style="width:140px" title="To date"/>
    <button type="submit" class="btn btn-purple btn-sm">
        <span class="material-icons-round">filter_list</span>Filter
    </button>
    <?php if (!empty($_GET['lq']) || !empty($_GET['laction']) || !empty($_GET['lfrom']) || !empty($_GET['lto'])): ?>
    <a href="?tab=logs" class="btn btn-outline btn-sm" style="color:#DC2626;border-color:#FECACA">
        <span class="material-icons-round">clear</span>Clear
    </a>
    <?php endif; ?>
</form>

<!-- Table -->
<div class="data-table-wrap" style="border-radius:0;border:none;border-top:1px solid var(--border)">
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Date & Time</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($logs)):
                $actionMeta = [
                    'create'  => ['#ECFDF5','#059669','add_circle'],
                    'update'  => ['#EFF6FF','#2563EB','edit'],
                    'delete'  => ['#FFF1F2','#DC2626','delete'],
                    'login'   => ['var(--purple-light)','var(--purple)','login'],
                    'logout'  => ['var(--canvas)','var(--ink-faint)','logout'],
                ];
                foreach ($logs as $log):
                    [$bg, $clr, $ico] = $actionMeta[$log['action']] ?? ['var(--canvas)','var(--ink-faint)','info'];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:9px">
                        <div style="width:30px;height:30px;border-radius:8px;background:var(--purple-light);color:var(--purple);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;flex-shrink:0">
                            <?= strtoupper(substr($log['username'] ?? 'S', 0, 1)) ?>
                        </div>
                        <span style="font-weight:600;font-size:13px"><?= e($log['username'] ?? 'System') ?></span>
                    </div>
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:7px">
                        <div class="log-action-ico" style="background:<?= $bg ?>">
                            <span class="material-icons-round" style="color:<?= $clr ?>"><?= $ico ?></span>
                        </div>
                        <span class="badge" style="background:<?= $bg ?>;color:<?= $clr ?>;border-color:<?= $bg ?>">
                            <?= ucfirst(e($log['action'])) ?>
                        </span>
                    </div>
                </td>
                <td style="font-size:12px;color:var(--ink-muted);max-width:320px">
                    <?= e($log['description']) ?>
                </td>
                <td style="font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace">
                    <?= e($log['ip_address'] ?? '—') ?>
                </td>
                <td style="font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;white-space:nowrap">
                    <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                    <span style="color:var(--ink-muted)"><?= date('g:i A', strtotime($log['created_at'])) ?></span>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr>
                <td colspan="5">
                    <div class="empty-state">
                        <div class="empty-icon"><span class="material-icons-round">history</span></div>
                        <div class="empty-title">No Logs Found</div>
                        <div class="empty-sub">
                            <?php if (!empty($_GET['lq']) || !empty($_GET['laction'])): ?>
                            No logs match your current filters. <a href="?tab=logs" style="color:var(--purple)">Clear filters</a>
                            <?php else: ?>
                            Activity will appear here as users interact with the system.
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php
    // Build query string preserving filters
    $logQS = http_build_query(array_filter([
        'tab'     => 'logs',
        'lq'      => $_GET['lq'] ?? '',
        'laction' => $_GET['laction'] ?? '',
        'lfrom'   => $_GET['lfrom'] ?? '',
        'lto'     => $_GET['lto'] ?? '',
    ]));
    ?>
    <?php if ($totalPages > 1): ?>
    <div class="pg-bar">
        <div class="pg-info">
            Showing <strong><?= number_format(min(($page-1)*$perPage+1, $totalLogs)) ?>–<?= number_format(min($page*$perPage, $totalLogs)) ?></strong>
            of <strong><?= number_format($totalLogs) ?></strong> logs
        </div>
        <div class="pg-btns">
            <?php if ($page > 1): ?>
            <a href="?<?= $logQS ?>&page=<?= $page-1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <?php endif; ?>
            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?<?= $logQS ?>&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="?<?= $logQS ?>&page=<?= $page+1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_right</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
async function exportLogs() {
    try {
        const qs = new URLSearchParams({
            lq:      document.querySelector('[name="lq"]')?.value     || '',
            laction: document.querySelector('[name="laction"]')?.value || '',
            lfrom:   document.querySelector('[name="lfrom"]')?.value   || '',
            lto:     document.querySelector('[name="lto"]')?.value     || '',
        });
        const response = await fetch('actions/export_logs.php?' + qs.toString(), { method: 'POST' });
        const blob = await response.blob();
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), { href: url, download: `activity_logs_${new Date().toISOString().split('T')[0]}.csv` });
        document.body.appendChild(a); a.click();
        URL.revokeObjectURL(url); a.remove();
        showToast('Logs exported successfully!', 'success');
    } catch (e) {
        showToast('Failed to export logs', 'error');
    }
}
</script>
