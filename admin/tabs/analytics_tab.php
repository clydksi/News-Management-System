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
.qs-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:22px}
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
            <span class="qs-tag" style="background:#FFFBEB;color:#92400E">Pending</span>
        </div>
        <div class="qs-val"><?= number_format($draftArticles) ?></div>
        <div class="qs-lbl">Drafts</div>
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

</div><!-- end padding shell -->

<!-- ── Bottom Grid: Authors + Activity ───────────────────────────── -->
<div class="an-bottom-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;padding:16px 20px 20px">

    <!-- Top Contributors -->
    <div class="an-card">
        <div class="an-card-hd">
            <div class="an-card-hd-l">
                <div class="an-card-icon" style="background:#FFFBEB">
                    <span class="material-icons-round" style="color:#D97706">emoji_events</span>
                </div>
                <div>
                    <div class="an-card-title">Top Contributors</div>
                    <div class="an-card-sub">By total article count</div>
                </div>
            </div>
        </div>
        <div class="an-card-body" style="max-height:420px;overflow-y:auto">
            <?php if (!empty($topAuthors)): ?>
            <?php foreach ($topAuthors as $idx => $author):
                $rankClass = match(true) { $idx===0=>'rank-1', $idx===1=>'rank-2', $idx===2=>'rank-3', default=>'rank-n' };
                $rankLabel = $idx < 3 ? ['🥇','🥈','🥉'][$idx] : '#'.($idx+1);
            ?>
            <div class="author-row">
                <div class="author-rank <?= $rankClass ?>"><?= $rankLabel ?></div>
                <div class="author-avatar"><?= strtoupper(substr($author['username'],0,1)) ?></div>
                <div class="author-info">
                    <div class="author-name"><?= e($author['username']) ?></div>
                    <div class="author-dept">
                        <span class="material-icons-round">business</span>
                        <?= e($author['department'] ?? 'No dept') ?>
                    </div>
                </div>
                <div class="author-stats">
                    <span class="badge badge-purple" style="font-size:9px;flex-shrink:0"><?= $author['recent_count'] ?> / mo</span>
                    <div class="author-count">
                        <div class="author-count-val"><?= number_format($author['article_count']) ?></div>
                        <div class="author-count-lbl">articles</div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding:40px 20px">
                <div class="empty-icon"><span class="material-icons-round">people_outline</span></div>
                <div class="empty-sub">No contributor data yet.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="an-card">
        <div class="an-card-hd">
            <div class="an-card-hd-l">
                <div class="an-card-icon" style="background:#EFF6FF">
                    <span class="material-icons-round" style="color:#2563EB">schedule</span>
                </div>
                <div>
                    <div class="an-card-title">Recent Activity</div>
                    <div class="an-card-sub">Latest <?= count($recentActivity ?? []) ?> articles</div>
                </div>
            </div>
        </div>
        <div class="an-card-body" style="max-height:420px;overflow-y:auto">
            <?php if (!empty($recentActivity)):
                $statusDot = [
                    'Draft'     => ['#FFF7ED','#F97316','edit_note'],
                    'Edited'    => ['#ECFDF5','#10B981','rate_review'],
                    'Headline'  => ['#EFF6FF','#2563EB','push_pin'],
                    'Published' => ['var(--purple-light)','var(--purple)','check_circle'],
                ];
                foreach ($recentActivity as $act):
                    $s = $statusDot[$act['status_label']] ?? ['var(--canvas)','var(--ink-faint)','article'];
            ?>
            <div class="activity-item">
                <div class="activity-dot" style="background:<?= $s[0] ?>">
                    <span class="material-icons-round" style="color:<?= $s[1] ?>"><?= $s[2] ?></span>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="activity-title"><?= e($act['title']) ?></div>
                    <div class="activity-meta">
                        <span class="activity-meta-item">
                            <span class="material-icons-round">person</span><?= e($act['author'] ?? '—') ?>
                        </span>
                        <span class="activity-meta-item">
                            <span class="material-icons-round">business</span><?= e($act['department'] ?? '—') ?>
                        </span>
                        <?php if (!empty($act['category'])): ?>
                        <span class="activity-meta-item">
                            <span class="material-icons-round">label</span><?= e($act['category']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="activity-meta-item">
                            <span class="material-icons-round">schedule</span><?= date('M d, g:i A', strtotime($act['created_at'])) ?>
                        </span>
                    </div>
                </div>
                <span class="badge <?= ['Draft'=>'badge-orange','Edited'=>'badge-green','Headline'=>'badge-blue','Published'=>'badge-purple'][$act['status_label']] ?? 'badge-gray' ?>" style="flex-shrink:0;align-self:flex-start">
                    <?= $act['status_label'] ?>
                </span>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding:40px 20px">
                <div class="empty-icon"><span class="material-icons-round">history</span></div>
                <div class="empty-sub">No recent activity.</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', () => {

    // ── Shared chart defaults ────────────────────────────────────
    const fontFam = "'Sora', sans-serif";
    const monoFam = "'Fira Code', monospace";
    const isDark  = () => document.documentElement.dataset.theme === 'dark';
    const gridClr = () => isDark() ? 'rgba(255,255,255,.07)' : 'rgba(60,20,120,.06)';
    const tickClr = () => isDark() ? '#635D7A' : '#8E89A8';
    const tooltipBg = 'rgba(19,17,26,.92)';

    const basePlugin = {
        legend: {
            position: 'bottom',
            labels: {
                padding: 14, color: tickClr(),
                font: { family: fontFam, size: 11 },
                usePointStyle: true, pointStyle: 'circle'
            }
        },
        tooltip: {
            backgroundColor: tooltipBg,
            padding: 12, cornerRadius: 9,
            titleFont: { size: 12, family: fontFam },
            bodyFont:  { size: 11, family: monoFam },
            titleColor: '#EAE6F8', bodyColor: '#9E98B8',
        }
    };

    const baseScales = {
        y: {
            beginAtZero: true,
            ticks: { color: tickClr, font: { family: monoFam, size: 10 }, stepSize: 1 },
            grid: { color: gridClr },
            border: { dash: [3, 3] },
        },
        x: {
            ticks: { color: tickClr, font: { family: fontFam, size: 10 }, maxRotation: 30 },
            grid: { display: false },
        }
    };

    // ── Dept Bar Chart ───────────────────────────────────────────
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
                        backgroundColor: 'rgba(124,58,237,.75)',
                        borderColor: '#7C3AED',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                    },
                    {
                        label: 'Published',
                        data: d.published,
                        backgroundColor: 'rgba(16,185,129,.7)',
                        borderColor: '#10B981',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: basePlugin,
                scales: baseScales,
            }
        });
    }

    // ── Doughnut ─────────────────────────────────────────────────
    const catCtx = document.getElementById('catChart');
    if (catCtx) {
        const c = <?= json_encode($categoryAnalytics) ?>;
        const palette = [
            '#7C3AED','#A78BFA','#3B82F6','#60A5FA','#10B981',
            '#34D399','#F97316','#FBBF24','#EF4444','#F43F5E'
        ];
        new Chart(catCtx, {
            type: 'doughnut',
            data: {
                labels: c.map(x => x.name),
                datasets: [{
                    data: c.map(x => x.article_count),
                    backgroundColor: palette,
                    borderWidth: 3,
                    borderColor: isDark() ? '#17142A' : '#FFFFFF',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                cutout: '62%',
                plugins: {
                    ...basePlugin,
                    legend: {
                        ...basePlugin.legend,
                        labels: {
                            ...basePlugin.legend.labels,
                            boxWidth: 10, boxHeight: 10,
                            padding: 10,
                        }
                    }
                }
            }
        });
    }

    // ── Trends Line Chart ─────────────────────────────────────────
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
                        borderColor: '#7C3AED',
                        backgroundColor: 'rgba(124,58,237,.08)',
                        fill: true, tension: 0.4, borderWidth: 2.5,
                        pointRadius: 4, pointBackgroundColor: '#7C3AED',
                        pointBorderColor: isDark() ? '#17142A' : '#fff',
                        pointBorderWidth: 2, pointHoverRadius: 6,
                    },
                    {
                        label: 'Published',
                        data: t.map(x => x.published_count),
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16,185,129,.07)',
                        fill: true, tension: 0.4, borderWidth: 2.5,
                        pointRadius: 4, pointBackgroundColor: '#10B981',
                        pointBorderColor: isDark() ? '#17142A' : '#fff',
                        pointBorderWidth: 2, pointHoverRadius: 6,
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: basePlugin,
                scales: baseScales,
            }
        });
    }
});
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