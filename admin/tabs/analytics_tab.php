<!-- Analytics Tab — Purple Editorial Edition -->
<style>
/* ── Analytics-specific tokens ── */
.an-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden}
.an-card-body{padding:20px 22px}
.an-card-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px}
.an-card-title{display:flex;align-items:center;gap:8px;font-family:'Playfair Display',serif;font-size:16px;color:var(--ink)}
.an-card-title .material-icons-round{font-size:18px!important}
.an-card-sub{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}

/* Quick-stat tiles */
.qs-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:22px}
@media(max-width:900px){.qs-grid{grid-template-columns:repeat(3,1fr)}}
@media(max-width:540px){.qs-grid{grid-template-columns:repeat(2,1fr)}}
.qs-tile{
    background:var(--surface);border:1px solid var(--border);
    border-radius:var(--r);box-shadow:var(--sh);
    padding:16px;position:relative;overflow:hidden;
    transition:all .2s cubic-bezier(.4,0,.2,1);
}
.qs-tile:hover{transform:translateY(-2px);box-shadow:var(--sh-md)}
.qs-tile::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;border-radius:var(--r) var(--r) 0 0}
.qs-tile.t-blue::before{background:#3B82F6}
.qs-tile.t-green::before{background:#10B981}
.qs-tile.t-purple::before{background:var(--purple)}
.qs-tile.t-orange::before{background:#F97316}
.qs-tile.t-amber::before{background:#F59E0B}
.qs-tile.t-red::before{background:#EF4444}
.qs-tile-top{display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:10px}
.qs-ico{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.qs-ico .material-icons-round{font-size:18px!important}
.qs-ico.ic-blue{background:#EFF6FF}.qs-ico.ic-blue .material-icons-round{color:#2563EB}
.qs-ico.ic-green{background:#ECFDF5}.qs-ico.ic-green .material-icons-round{color:#059669}
.qs-ico.ic-purple{background:var(--purple-light)}.qs-ico.ic-purple .material-icons-round{color:var(--purple)}
.qs-ico.ic-orange{background:#FFF7ED}.qs-ico.ic-orange .material-icons-round{color:#EA580C}
.qs-ico.ic-amber{background:#FFFBEB}.qs-ico.ic-amber .material-icons-round{color:#D97706}
.qs-ico.ic-red{background:#FFF1F2}.qs-ico.ic-red .material-icons-round{color:#DC2626}
.qs-tag{padding:2px 8px;border-radius:99px;font-size:9px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;font-family:'Fira Code',monospace}
.qs-val{font-family:'Playfair Display',serif;font-size:26px;font-weight:700;color:var(--ink);line-height:1;margin-bottom:4px}
.qs-lbl{font-size:11px;color:var(--ink-faint);font-weight:500}

/* Chart cards grid */
.chart-duo{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
@media(max-width:860px){.chart-duo{grid-template-columns:1fr}}
.chart-wrap{position:relative}
.chart-canvas-box{padding:0 16px 16px}

/* Dept analytics table */
.dept-score-bar{height:5px;border-radius:99px;background:var(--border);overflow:hidden;min-width:50px}
.dept-score-fill{height:100%;border-radius:99px;background:linear-gradient(90deg,var(--purple),#A78BFA)}

/* Author rank badge */
.rank-badge{
    width:30px;height:30px;border-radius:8px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-family:'Fira Code',monospace;font-size:11px;font-weight:700;
}
.rank-1{background:#FEF3C7;color:#92400E;border:1px solid #FDE68A}
.rank-2{background:#F1F5F9;color:#475569;border:1px solid #CBD5E1}
.rank-3{background:#FFF7ED;color:#9A3412;border:1px solid #FED7AA}
.rank-n{background:var(--canvas);color:var(--ink-faint);border:1px solid var(--border)}

/* Activity feed */
.activity-feed{max-height:400px;overflow-y:auto;display:flex;flex-direction:column;gap:6px}
.activity-item{display:flex;align-items:flex-start;gap:10px;padding:10px 12px;border-radius:9px;background:var(--canvas);border:1px solid var(--border);transition:border-color .15s}
.activity-item:hover{border-color:var(--border-md)}
.activity-status-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0;margin-top:5px}
.activity-title{font-size:12px;font-weight:600;color:var(--ink);line-height:1.4;margin-bottom:4px;
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px}
.activity-meta{display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.activity-meta .material-icons-round{font-size:11px!important}

/* two-col bottom */
.bottom-duo{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:16px}
@media(max-width:860px){.bottom-duo{grid-template-columns:1fr}}

/* Pending approval queue */
.pq-row{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:9px;background:var(--canvas);border:1px solid var(--border);transition:border-color .15s}
.pq-row:hover{border-color:#EF4444}
.pq-title{font-size:13px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px}
.pq-meta{display:flex;flex-wrap:wrap;align-items:center;gap:5px;font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:3px}
.pq-meta .material-icons-round{font-size:11px!important}
.pq-actions{display:flex;gap:6px;flex-shrink:0}
.pq-btn{border:none;border-radius:7px;font-size:11px;font-weight:600;padding:5px 12px;cursor:pointer;display:flex;align-items:center;gap:4px;transition:all .15s}
.pq-btn .material-icons-round{font-size:13px!important}
.pq-approve{background:#ECFDF5;color:#059669}.pq-approve:hover{background:#059669;color:#fff}
.pq-reject{background:#FFF1F2;color:#DC2626}.pq-reject:hover{background:#DC2626;color:#fff}
@keyframes pulse-dot{0%,100%{opacity:1}50%{opacity:.5}}
</style>

<!-- ── Quick Stats Tiles ─────────────────────────────────── -->
<div style="padding:20px 22px 0">

<div class="qs-grid">

    <div class="qs-tile t-blue">
        <div class="qs-tile-top">
            <div class="qs-ico ic-blue"><span class="material-icons-round">today</span></div>
            <span class="qs-tag" style="background:#EFF6FF;color:#1D4ED8">Today</span>
        </div>
        <div class="qs-val"><?= number_format($todayArticles) ?></div>
        <div class="qs-lbl">Articles Today</div>
    </div>

    <div class="qs-tile t-green">
        <div class="qs-tile-top">
            <div class="qs-ico ic-green"><span class="material-icons-round">date_range</span></div>
            <span class="qs-tag" style="background:#ECFDF5;color:#065F46">Week</span>
        </div>
        <div class="qs-val"><?= number_format($weekArticles) ?></div>
        <div class="qs-lbl">This Week</div>
    </div>

    <div class="qs-tile t-purple">
        <div class="qs-tile-top">
            <div class="qs-ico ic-purple"><span class="material-icons-round">calendar_month</span></div>
            <span class="qs-tag" style="background:var(--purple-light);color:var(--purple-md)">Month</span>
        </div>
        <div class="qs-val"><?= number_format($monthArticles) ?></div>
        <div class="qs-lbl">This Month</div>
    </div>

    <div class="qs-tile t-orange">
        <div class="qs-tile-top">
            <div class="qs-ico ic-orange"><span class="material-icons-round">check_circle</span></div>
            <span class="qs-tag" style="background:#FFF7ED;color:#9A3412">Live</span>
        </div>
        <div class="qs-val"><?= number_format($publishedArticles) ?></div>
        <div class="qs-lbl">Published</div>
    </div>

    <div class="qs-tile t-amber">
        <div class="qs-tile-top">
            <div class="qs-ico ic-amber"><span class="material-icons-round">draft</span></div>
            <span class="qs-tag" style="background:#FFFBEB;color:#92400E">Drafts</span>
        </div>
        <div class="qs-val"><?= number_format($draftArticles) ?></div>
        <div class="qs-lbl">Drafts</div>
    </div>

    <div class="qs-tile t-red" style="cursor:pointer" onclick="document.getElementById('pending-queue').scrollIntoView({behavior:'smooth'})">
        <div class="qs-tile-top">
            <div class="qs-ico ic-red"><span class="material-icons-round">pending_actions</span></div>
            <?php if ($pendingApprovalCount > 0): ?>
            <span class="qs-tag" style="background:#FFF1F2;color:#B91C1C;animation:pulse-dot 1.5s infinite">Urgent</span>
            <?php else: ?>
            <span class="qs-tag" style="background:#FFF1F2;color:#9CA3AF">Review</span>
            <?php endif; ?>
        </div>
        <div class="qs-val" style="<?= $pendingApprovalCount > 0 ? 'color:#DC2626' : '' ?>"><?= number_format($pendingApprovalCount) ?></div>
        <div class="qs-lbl">Pending Review</div>
    </div>

</div>

<!-- ── Chart Duo ──────────────────────────────────────────── -->
<div class="chart-duo">

    <!-- Dept Performance -->
    <div class="an-card">
        <div class="an-card-hd">
            <div>
                <div class="an-card-title">
                    <span class="material-icons-round" style="color:var(--purple)">business</span>
                    Department Performance
                </div>
                <div class="an-card-sub">Articles by department</div>
            </div>
        </div>
        <div class="chart-canvas-box" style="padding-top:16px">
            <div style="position:relative;height:260px">
                <canvas id="deptChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Category Doughnut -->
    <div class="an-card">
        <div class="an-card-hd">
            <div>
                <div class="an-card-title">
                    <span class="material-icons-round" style="color:#F97316">label</span>
                    Category Distribution
                </div>
                <div class="an-card-sub">Articles per category</div>
            </div>
        </div>
        <div class="chart-canvas-box" style="padding-top:16px">
            <div style="position:relative;height:260px">
                <canvas id="catChart"></canvas>
            </div>
        </div>
    </div>

</div>

<!-- ── Trends Line Chart ──────────────────────────────────── -->
<div class="an-card" style="margin-bottom:16px">
    <div class="an-card-hd">
        <div>
            <div class="an-card-title">
                <span class="material-icons-round" style="color:#2563EB">trending_up</span>
                Publishing Trends
            </div>
            <div class="an-card-sub">Last 12 months — total vs published</div>
        </div>
        <span class="badge badge-blue">12 Months</span>
    </div>
    <div class="chart-canvas-box" style="padding-top:16px">
        <div style="position:relative;height:280px">
            <canvas id="trendsChart"></canvas>
        </div>
    </div>
</div>

<!-- ── Department Analytics Table ────────────────────────── -->
<div class="an-card" style="margin-bottom:16px">
    <div class="an-card-hd">
        <div>
            <div class="an-card-title">
                <span class="material-icons-round" style="color:#6366F1">business_center</span>
                Department Performance Analysis
            </div>
            <div class="an-card-sub">Detailed breakdown of each department's activity</div>
        </div>
    </div>
    <div style="overflow-x:auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Department</th>
                    <th style="text-align:center">Total</th>
                    <th style="text-align:center">Published</th>
                    <th style="text-align:center">Drafts</th>
                    <th style="text-align:center">This Week</th>
                    <th style="text-align:center">This Month</th>
                    <th style="text-align:center">Users</th>
                    <th style="text-align:center">Active</th>
                    <th>Score</th>
                    <th style="text-align:center">Last Activity</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($deptAnalytics)):
                $maxArticles = max(1, max(array_column($deptAnalytics, 'total_articles')));
                foreach ($deptAnalytics as $dept):
                    $score = round(($dept['total_articles'] / $maxArticles) * 100);
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:9px">
                        <div style="width:30px;height:30px;border-radius:8px;background:var(--purple-light);border:1px solid var(--trans-border, #C4B5FD);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <span class="material-icons-round" style="font-size:15px!important;color:var(--purple)">business</span>
                        </div>
                        <span style="font-weight:600;font-size:13px"><?= e($dept['name']) ?></span>
                    </div>
                </td>
                <td style="text-align:center">
                    <span style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:var(--ink)"><?= number_format($dept['total_articles']) ?></span>
                </td>
                <td style="text-align:center">
                    <span class="badge badge-green"><?= $dept['published_articles'] ?></span>
                </td>
                <td style="text-align:center">
                    <span class="badge badge-orange"><?= $dept['draft_articles'] ?></span>
                </td>
                <td style="text-align:center">
                    <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                        <span style="font-size:12px;font-weight:600;color:var(--ink)"><?= $dept['articles_this_week'] ?></span>
                        <?php if ($dept['articles_this_week'] > 0): ?>
                        <span class="material-icons-round" style="font-size:13px!important;color:#10B981">trending_up</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td style="text-align:center;font-size:12px;font-weight:600;color:var(--ink)"><?= $dept['articles_this_month'] ?></td>
                <td style="text-align:center">
                    <span class="badge badge-gray"><?= $dept['total_users'] ?></span>
                </td>
                <td style="text-align:center">
                    <span class="badge badge-blue"><?= $dept['active_users'] ?></span>
                </td>
                <td style="min-width:80px">
                    <div style="display:flex;align-items:center;gap:6px">
                        <div class="dept-score-bar" style="flex:1">
                            <div class="dept-score-fill" style="width:<?= $score ?>%"></div>
                        </div>
                        <span style="font-size:10px;font-family:'Fira Code',monospace;color:var(--ink-faint);white-space:nowrap"><?= $score ?>%</span>
                    </div>
                </td>
                <td style="text-align:center;font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;white-space:nowrap">
                    <?php if ($dept['last_article_date']): ?>
                    <?= date('M d, Y', strtotime($dept['last_article_date'])) ?>
                    <?php else: ?>
                    <span style="color:var(--border-md)">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="10"><div class="empty-state"><div class="empty-icon"><span class="material-icons-round">analytics</span></div><div class="empty-title">No Analytics Yet</div><div class="empty-sub">Article data will appear here once departments are active.</div></div></td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Bottom Duo: Authors + Activity ────────────────────── -->
<div class="bottom-duo">

    <!-- Top Contributors -->
    <div class="an-card">
        <div class="an-card-hd">
            <div class="an-card-title">
                <span class="material-icons-round" style="color:#D97706">emoji_events</span>
                Top Contributors
            </div>
        </div>
        <div class="an-card-body" style="padding-top:14px">
            <?php if (!empty($topAuthors)): ?>
            <div style="display:flex;flex-direction:column;gap:7px">
            <?php foreach ($topAuthors as $idx => $author):
                $rankClass = match($idx) { 0 => 'rank-1', 1 => 'rank-2', 2 => 'rank-3', default => 'rank-n' };
                $rankLabel = $idx < 3 ? ['🥇','🥈','🥉'][$idx] : '#' . ($idx + 1);
            ?>
            <div style="display:flex;align-items:center;gap:10px;padding:9px 12px;background:var(--canvas);border:1px solid var(--border);border-radius:9px;transition:border-color .15s" onmouseover="this.style.borderColor='var(--border-md)'" onmouseout="this.style.borderColor='var(--border)'">
                <div class="rank-badge <?= $rankClass ?>"><?= $rankLabel ?></div>
                <div style="min-width:0;flex:1">
                    <div style="font-size:13px;font-weight:600;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($author['username']) ?></div>
                    <div style="font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;display:flex;align-items:center;gap:3px;margin-top:1px">
                        <span class="material-icons-round" style="font-size:11px!important">business</span>
                        <?= e($author['department'] ?? '—') ?>
                    </div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-family:'Playfair Display',serif;font-size:18px;font-weight:700;color:var(--ink);line-height:1"><?= $author['article_count'] ?></div>
                    <div style="font-size:9px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px">articles</div>
                </div>
                <span class="badge badge-purple" style="flex-shrink:0"><?= $author['recent_count'] ?> mo</span>
            </div>
            <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state" style="padding:32px"><div class="empty-icon"><span class="material-icons-round">people_outline</span></div><div class="empty-title">No Contributors</div></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Feed -->
    <div class="an-card">
        <div class="an-card-hd">
            <div class="an-card-title">
                <span class="material-icons-round" style="color:#2563EB">schedule</span>
                Recent Activity
            </div>
        </div>
        <div class="an-card-body" style="padding-top:14px">
        <?php
        $statusMeta = [
            0 => ['#F59E0B', 'Draft'],
            1 => ['#3B82F6', 'Edited'],
            2 => ['#8B5CF6', 'Headline'],
            3 => ['#10B981', 'Published'],
        ];
        if (!empty($recentActivity)): ?>
            <div class="activity-feed">
            <?php foreach ($recentActivity as $act):
                [$dotColor, $statusLabel] = $statusMeta[$act['is_pushed']] ?? ['#8E89A8', 'Unknown'];
            ?>
            <div class="activity-item">
                <span class="activity-status-dot" style="background:<?= $dotColor ?>"></span>
                <div style="min-width:0;flex:1">
                    <div class="activity-title" title="<?= e($act['title']) ?>"><?= e($act['title']) ?></div>
                    <div class="activity-meta">
                        <span class="badge" style="padding:1px 7px;font-size:9px;background:<?= $dotColor ?>22;color:<?= $dotColor ?>;border-color:<?= $dotColor ?>44"><?= $statusLabel ?></span>
                        <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">person</span><?= e($act['author']) ?></span>
                        <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">schedule</span><?= date('M d, g:i A', strtotime($act['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state" style="padding:32px"><div class="empty-icon"><span class="material-icons-round">history</span></div><div class="empty-title">No Recent Activity</div></div>
        <?php endif; ?>
        </div>
    </div>

</div>

<!-- ── Pending Approval Queue ──────────────────────────── -->
<div id="pending-queue" class="an-card" style="margin-top:16px;margin-bottom:16px">
    <div class="an-card-hd">
        <div>
            <div class="an-card-title">
                <span class="material-icons-round" style="color:#DC2626">pending_actions</span>
                Pending Approval Queue
            </div>
            <div class="an-card-sub">Articles submitted by users for headline promotion</div>
        </div>
        <?php if ($pendingApprovalCount > 0): ?>
        <span class="badge" style="background:#FFF1F2;color:#B91C1C;border-color:#FECACA;font-size:11px;padding:4px 10px">
            <?= $pendingApprovalCount ?> awaiting
        </span>
        <?php endif; ?>
    </div>
    <div class="an-card-body" style="padding-top:14px">
        <?php if (!empty($pendingArticles)): ?>
        <div style="display:flex;flex-direction:column;gap:7px" id="pq-list">
        <?php foreach ($pendingArticles as $pa): ?>
        <div class="pq-row" id="pq-item-<?= $pa['id'] ?>">
            <div style="width:34px;height:34px;border-radius:8px;background:#FFF1F2;border:1px solid #FECACA;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <span class="material-icons-round" style="font-size:16px!important;color:#DC2626">pending_actions</span>
            </div>
            <div style="flex:1;min-width:0">
                <div class="pq-title" title="<?= e($pa['title']) ?>"><?= e($pa['title']) ?></div>
                <div class="pq-meta">
                    <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">person</span><?= e($pa['author'] ?? '—') ?></span>
                    <span style="color:var(--border-md)">·</span>
                    <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">business</span><?= e($pa['department'] ?? '—') ?></span>
                    <?php if (!empty($pa['category'])): ?>
                    <span style="color:var(--border-md)">·</span>
                    <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">label</span><?= e($pa['category']) ?></span>
                    <?php endif; ?>
                    <span style="color:var(--border-md)">·</span>
                    <span style="display:flex;align-items:center;gap:2px"><span class="material-icons-round">schedule</span><?= date('M d, Y g:i A', strtotime($pa['created_at'])) ?></span>
                </div>
            </div>
            <div class="pq-actions">
                <button class="pq-btn pq-approve" onclick="pqApprove(<?= $pa['id'] ?>)">
                    <span class="material-icons-round">check_circle</span>Approve
                </button>
                <button class="pq-btn pq-reject" onclick="pqOpenReject(<?= $pa['id'] ?>)">
                    <span class="material-icons-round">cancel</span>Reject
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:40px 20px">
            <div class="empty-icon"><span class="material-icons-round" style="color:#10B981">check_circle</span></div>
            <div class="empty-title" style="color:#10B981">All Clear</div>
            <div class="empty-sub">No articles awaiting approval. Great work!</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Reject modal for queue -->
<div id="pq-reject-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
    <div style="background:var(--surface);border-radius:14px;padding:28px;width:420px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
            <span class="material-icons-round" style="color:#DC2626;font-size:22px">cancel</span>
            <span style="font-family:'Playfair Display',serif;font-size:17px;font-weight:700;color:var(--ink)">Reject Submission</span>
        </div>
        <p style="font-size:13px;color:var(--ink-faint);margin-bottom:14px">Please provide a reason so the author can improve their article.</p>
        <textarea id="pq-reject-note" rows="4" placeholder="Enter rejection reason…" style="width:100%;border:1px solid var(--border);border-radius:9px;padding:10px 12px;font-size:13px;font-family:'Sora',sans-serif;color:var(--ink);background:var(--canvas);resize:vertical;box-sizing:border-box"></textarea>
        <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
            <button onclick="pqCloseReject()" style="border:1px solid var(--border);background:var(--canvas);color:var(--ink-faint);border-radius:8px;padding:8px 18px;font-size:12px;cursor:pointer">Cancel</button>
            <button onclick="pqSubmitReject()" style="background:#DC2626;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:4px">
                <span class="material-icons-round" style="font-size:14px">cancel</span>Reject
            </button>
        </div>
    </div>
</div>

</div><!-- end padding shell -->



<script>
/* ── Pending Queue Actions ─────────────────────────────────────── */
let _pqRejectId = null;

function pqApprove(id) {
    const row  = document.getElementById('pq-item-' + id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('../user/function/approve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=approve&id=' + id + '&csrf_token=' + encodeURIComponent(csrf)
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) {
            if (row) row.style.transition = 'opacity .4s';
            if (row) { row.style.opacity = '0'; setTimeout(() => row.remove(), 400); }
            _pqUpdateCount(-1);
            _pqToast('Article approved and set to Headline ✓', '#059669');
        } else {
            _pqToast(d.message || 'Approval failed.', '#DC2626');
        }
    })
    .catch(() => _pqToast('Network error. Please try again.', '#DC2626'));
}

function pqOpenReject(id) {
    _pqRejectId = id;
    document.getElementById('pq-reject-note').value = '';
    const m = document.getElementById('pq-reject-modal');
    m.style.display = 'flex';
    setTimeout(() => document.getElementById('pq-reject-note').focus(), 50);
}
function pqCloseReject() {
    document.getElementById('pq-reject-modal').style.display = 'none';
    _pqRejectId = null;
}
function pqSubmitReject() {
    const note = document.getElementById('pq-reject-note').value.trim();
    if (!note) { document.getElementById('pq-reject-note').focus(); return; }
    const row  = document.getElementById('pq-item-' + _pqRejectId);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('../user/function/approve.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=reject&id=' + _pqRejectId + '&note=' + encodeURIComponent(note) + '&csrf_token=' + encodeURIComponent(csrf)
    })
    .then(r => r.json())
    .then(d => {
        pqCloseReject();
        if (d.success) {
            if (row) { row.style.transition = 'opacity .4s'; row.style.opacity = '0'; setTimeout(() => row.remove(), 400); }
            _pqUpdateCount(-1);
            _pqToast('Article rejected. Author will be notified.', '#F97316');
        } else {
            _pqToast(d.message || 'Rejection failed.', '#DC2626');
        }
    })
    .catch(() => _pqToast('Network error. Please try again.', '#DC2626'));
}

function _pqUpdateCount(delta) {
    // Update the tile counter
    const tileVal = document.querySelector('.qs-tile.t-red .qs-val');
    if (tileVal) {
        let n = parseInt(tileVal.textContent.replace(/,/g, ''), 10) + delta;
        if (n < 0) n = 0;
        tileVal.textContent = n.toLocaleString();
        if (n === 0) tileVal.style.color = '';
    }
    // Show all-clear state if queue is empty
    const list = document.getElementById('pq-list');
    if (list && list.children.length === 0) {
        list.outerHTML = '<div class="empty-state" style="padding:40px 20px"><div class="empty-icon"><span class="material-icons-round" style="color:#10B981">check_circle</span></div><div class="empty-title" style="color:#10B981">All Clear</div><div class="empty-sub">No articles awaiting approval. Great work!</div></div>';
    }
}

function _pqToast(msg, color) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:24px;right:24px;background:'+color+';color:#fff;padding:11px 18px;border-radius:10px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 20px rgba(0,0,0,.25);opacity:0;transition:opacity .3s';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => { t.style.opacity = '1'; setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3200); });
}

// Close reject modal on backdrop click
document.getElementById('pq-reject-modal')?.addEventListener('click', function(e){ if(e.target===this) pqCloseReject(); });
</script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* ── Shared chart defaults ─────────────────────────── */
    const FONT_FAMILY = "'Sora', sans-serif";
    const MONO_FAMILY = "'Fira Code', monospace";

    // Read CSS vars for theming
    const style  = getComputedStyle(document.documentElement);
    const ink    = style.getPropertyValue('--ink').trim()      || '#13111A';
    const inkFt  = style.getPropertyValue('--ink-faint').trim()|| '#8E89A8';
    const border = style.getPropertyValue('--border').trim()   || '#E2DDEF';
    const surface= style.getPropertyValue('--surface').trim()  || '#FFFFFF';

    function gridColor() { return getComputedStyle(document.documentElement).getPropertyValue('--border').trim() || '#E2DDEF'; }

    const tooltipDefaults = {
        backgroundColor: 'rgba(19,17,26,.92)',
        titleColor: '#EAE6F8',
        bodyColor: '#9E98B8',
        padding: 12,
        cornerRadius: 9,
        titleFont: { size: 12, family: FONT_FAMILY, weight: '600' },
        bodyFont:  { size: 11, family: MONO_FAMILY },
        borderColor: 'rgba(124,58,237,.25)',
        borderWidth: 1,
    };

    const legendDefaults = {
        position: 'bottom',
        labels: {
            padding: 16,
            font: { family: FONT_FAMILY, size: 11 },
            color: inkFt,
            usePointStyle: true,
            pointStyle: 'circle',
        }
    };

    const scaleDefaults = (label = false) => ({
        y: {
            beginAtZero: true,
            ticks: { stepSize: 1, font: { family: MONO_FAMILY, size: 10 }, color: inkFt },
            grid:  { color: gridColor() },
            border:{ dash: [4,4] },
        },
        x: {
            ticks: { font: { family: FONT_FAMILY, size: 10 }, color: inkFt },
            grid:  { display: false },
        }
    });

    /* ── 1. Department Bar Chart ───────────────────────── */
    const deptCtx = document.getElementById('deptChart');
    if (deptCtx) {
        const d = <?= json_encode($deptComparison) ?>;
        new Chart(deptCtx, {
            type: 'bar',
            data: {
                labels: d.labels,
                datasets: [
                    {
                        label: 'Total Articles',
                        data: d.articles,
                        backgroundColor: 'rgba(124,58,237,.80)',
                        borderColor:     'rgba(124,58,237,1)',
                        borderWidth: 2,
                        borderRadius: { topLeft:6, topRight:6 },
                        borderSkipped: false,
                    },
                    {
                        label: 'Published',
                        data: d.published,
                        backgroundColor: 'rgba(16,185,129,.75)',
                        borderColor:     'rgba(16,185,129,1)',
                        borderWidth: 2,
                        borderRadius: { topLeft:6, topRight:6 },
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: legendDefaults, tooltip: tooltipDefaults },
                scales: scaleDefaults(),
            }
        });
    }

    /* ── 2. Category Doughnut ──────────────────────────── */
    const catCtx = document.getElementById('catChart');
    if (catCtx) {
        const c = <?= json_encode($categoryAnalytics) ?>;
        const palette = [
            '#7C3AED','#6366F1','#3B82F6','#0EA5E9','#06B6D4',
            '#10B981','#F59E0B','#F97316','#EF4444','#EC4899'
        ];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: c.map(x => x.name),
                datasets:[{
                    data: c.map(x => x.article_count),
                    backgroundColor: palette,
                    borderColor:     surface,
                    borderWidth: 3,
                    hoverOffset: 10,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    legend: legendDefaults,
                    tooltip: {
                        ...tooltipDefaults,
                        callbacks: {
                            label: ctx => {
                                const total = ctx.chart.data.datasets[0].data.reduce((a,b)=>a+b,0);
                                const pct   = total ? Math.round(ctx.parsed/total*100) : 0;
                                return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    /* ── 3. Trends Line Chart ──────────────────────────── */
    const trendsCtx = document.getElementById('trendsChart');
    if (trendsCtx) {
        const t = <?= json_encode($monthlyTrends) ?>;
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: t.map(x => x.month_label),
                datasets: [
                    {
                        label: 'Total Articles',
                        data: t.map(x => x.count),
                        borderColor:     '#7C3AED',
                        backgroundColor: 'rgba(124,58,237,.10)',
                        fill: true,
                        tension: 0.42,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#7C3AED',
                        pointBorderColor:     surface,
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Published',
                        data: t.map(x => x.published_count),
                        borderColor:     '#10B981',
                        backgroundColor: 'rgba(16,185,129,.08)',
                        fill: true,
                        tension: 0.42,
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 7,
                        pointBackgroundColor: '#10B981',
                        pointBorderColor:     surface,
                        pointBorderWidth: 2,
                    },
                    {
                        label: 'Drafts',
                        data: t.map(x => x.draft_count),
                        borderColor:     '#F59E0B',
                        backgroundColor: 'rgba(245,158,11,.06)',
                        fill: true,
                        tension: 0.42,
                        borderWidth: 1.5,
                        borderDash: [5,4],
                        pointRadius: 3,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#F59E0B',
                        pointBorderColor:     surface,
                        pointBorderWidth: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: { legend: legendDefaults, tooltip: tooltipDefaults },
                scales: scaleDefaults(),
            }
        });
    }
});
</script>