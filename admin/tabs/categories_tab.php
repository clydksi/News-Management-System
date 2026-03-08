<!-- Categories Management Tab — Purple Editorial Edition -->
<style>
/* Category color palette — assigns consistent colors by ID mod */
.cat-chip {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700;
    box-shadow: 0 2px 6px rgba(0,0,0,.12);
}
.cat-chip .material-icons-round { font-size: 16px !important; }

/* Inline search strip */
.cat-search-strip {
    padding: 12px 20px;
    border-bottom: 1px solid var(--border);
    background: var(--canvas);
    display: flex; align-items: center; gap: 10px;
}
.cat-search-wrap {
    flex: 1; position: relative; display: flex; align-items: center;
}
.cat-search-wrap .material-icons-round {
    position: absolute; left: 10px;
    font-size: 16px !important; color: var(--ink-faint); pointer-events: none;
}
.cat-search-input {
    width: 100%;
    padding: 8px 36px;
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--surface); color: var(--ink);
    font-family: 'Sora', sans-serif; font-size: 13px;
    outline: none; transition: border-color .15s, box-shadow .15s;
}
.cat-search-input:focus {
    border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow);
}
.cat-search-clear {
    position: absolute; right: 8px;
    width: 20px; height: 20px; border-radius: 50%;
    border: none; background: var(--border-md);
    color: var(--ink-faint); cursor: pointer;
    display: none; align-items: center; justify-content: center;
    font-size: 12px; line-height: 1;
    transition: background .15s;
}
.cat-search-clear.visible { display: flex; }
.cat-search-clear:hover { background: var(--purple); color: white; }
.cat-count-badge {
    flex-shrink: 0; padding: 4px 10px;
    border-radius: 99px; border: 1px solid var(--border);
    background: var(--surface);
    font-size: 11px; font-weight: 700; font-family: 'Fira Code', monospace;
    color: var(--ink-faint); white-space: nowrap;
}
.cat-count-badge strong { color: var(--purple-md); }

/* No-results row */
.cat-no-results {
    display: none;
    padding: 40px 20px; text-align: center;
    border-bottom: 1px solid var(--border);
}
.cat-no-results.show { display: block; }
</style>

<!-- Section header -->
<div class="section-hd">
    <div class="section-hd-l">
        <div class="section-hd-icon">
            <span class="material-icons-round">label</span>
        </div>
        <div>
            <div class="section-hd-title">Categories Management</div>
            <div class="section-hd-sub">Manage article categories</div>
        </div>
    </div>
    <div class="section-hd-r">
        <button onclick="location.reload()" class="btn btn-outline btn-icon" title="Refresh">
            <span class="material-icons-round">refresh</span>
        </button>
        <button onclick="openModal('addCategoryModal')" class="btn btn-purple">
            <span class="material-icons-round">add</span>Add Category
        </button>
    </div>
</div>

<!-- Inline search -->
<div class="cat-search-strip">
    <div class="cat-search-wrap">
        <span class="material-icons-round">search</span>
        <input class="cat-search-input" id="catSearch" type="text"
               placeholder="Filter categories by name…" autocomplete="off"/>
        <button class="cat-search-clear" id="catSearchClear" title="Clear">✕</button>
    </div>
    <div class="cat-count-badge" id="catCountBadge">
        <strong><?= count($categories) ?></strong> / <?= $totalCats ?? count($categories) ?>
    </div>
</div>

<!-- Table -->
<div class="data-table-wrap" style="border-radius:0;border:none;border-top:1px solid var(--border)">
    <div style="overflow-x:auto">
        <table class="data-table" id="catTable">
            <thead>
                <tr>
                    <th>Category</th>
                    <th style="text-align:center">Articles</th>
                    <th style="text-align:center">Usage</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="catTableBody">
            <?php
            /* Color palette for category chips — cycles by ID */
            $catColors = [
                ['#FFF7ED','#F97316'], // orange
                ['#EFF6FF','#2563EB'], // blue
                ['var(--purple-light)','var(--purple)'], // purple
                ['#ECFDF5','#059669'], // green
                ['#FFF1F2','#DC2626'], // red
                ['#FFFBEB','#D97706'], // amber
                ['#F0F9FF','#0EA5E9'], // sky
                ['#F5F3FF','#6D28D9'], // violet
            ];
            $maxArticles = !empty($categories) ? max(array_column($categories, 'article_count')) : 1;
            $maxArticles = max(1, $maxArticles);
            foreach ($categories as $cat):
                $ci = $cat['id'] % count($catColors);
                [$cbg, $cfg] = $catColors[$ci];
                $pct = round(($cat['article_count'] / $maxArticles) * 100);
            ?>
            <tr class="cat-row" data-name="<?= strtolower(e($cat['name'])) ?>">
                <!-- Name -->
                <td>
                    <div style="display:flex;align-items:center;gap:11px">
                        <div class="cat-chip" style="background:<?= $cbg ?>;color:<?= $cfg ?>">
                            <span class="material-icons-round">label</span>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px;color:var(--ink)"><?= e($cat['name']) ?></div>
                            <div style="font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace">ID: <?= $cat['id'] ?></div>
                        </div>
                    </div>
                </td>
                <!-- Article count -->
                <td style="text-align:center">
                    <span class="badge badge-blue">
                        <span class="material-icons-round">article</span>
                        <?= number_format($cat['article_count']) ?>
                    </span>
                </td>
                <!-- Usage bar -->
                <td style="min-width:110px">
                    <div style="display:flex;align-items:center;gap:8px">
                        <div style="flex:1;height:6px;border-radius:99px;background:var(--border);overflow:hidden">
                            <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,<?= $cfg ?>,<?= $cbg ?>);opacity:.9;border-radius:99px;transition:width .4s"></div>
                        </div>
                        <span style="font-size:10px;font-family:'Fira Code',monospace;color:var(--ink-faint);white-space:nowrap"><?= $pct ?>%</span>
                    </div>
                </td>
                <!-- Actions -->
                <td>
                    <div style="display:flex;align-items:center;justify-content:center;gap:4px">
                        <button class="btn btn-sm btn-icon btn-outline edit-category"
                                data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>"
                                title="Edit">
                            <span class="material-icons-round">edit</span>
                        </button>
                        <button class="btn btn-sm btn-icon btn-red delete-category"
                                data-id="<?= $cat['id'] ?>" data-name="<?= e($cat['name']) ?>"
                                data-count="<?= $cat['article_count'] ?>"
                                title="Delete">
                            <span class="material-icons-round">delete</span>
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($categories)): ?>
            <tr>
                <td colspan="4">
                    <div class="empty-state">
                        <div class="empty-icon"><span class="material-icons-round">label_off</span></div>
                        <div class="empty-title">No Categories Yet</div>
                        <div class="empty-sub">Create categories to organise your articles.</div>
                        <button onclick="openModal('addCategoryModal')" class="btn btn-purple">
                            <span class="material-icons-round">add</span>Add Category
                        </button>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            </tbody>
        </table>

        <!-- No-results message (shown by JS search filter) -->
        <div class="cat-no-results" id="catNoResults">
            <div style="font-size:36px;margin-bottom:8px">🔍</div>
            <div style="font-family:'Playfair Display',serif;font-size:16px;color:var(--ink);margin-bottom:4px">No matches found</div>
            <div style="font-size:12px;color:var(--ink-faint)">Try a different search term.</div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pg-bar">
        <div class="pg-info">
            Showing <strong><?= min($offset + 1, $totalCats ?? 0) ?></strong>–<strong><?= min($offset + $perPage, $totalCats ?? 0) ?></strong>
            of <strong><?= $totalCats ?? 0 ?></strong>
        </div>
        <div class="pg-btns">
            <?php if ($page > 1): ?>
            <a href="?tab=categories&page=<?= $page-1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_left</span>
            </a>
            <?php else: ?>
            <span class="pg-btn" style="opacity:.35;pointer-events:none">
                <span class="material-icons-round">chevron_left</span>
            </span>
            <?php endif; ?>

            <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
            <a href="?tab=categories&page=<?= $i ?>" class="pg-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="?tab=categories&page=<?= $page+1 ?>" class="pg-btn">
                <span class="material-icons-round">chevron_right</span>
            </a>
            <?php else: ?>
            <span class="pg-btn" style="opacity:.35;pointer-events:none">
                <span class="material-icons-round">chevron_right</span>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ═══════════════════════════════════════════════════════
     ADD CATEGORY MODAL
═══════════════════════════════════════════════════════ -->
<div id="addCategoryModal" class="modal-bg">
    <div class="modal-box" style="max-width:400px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:#FFF7ED">
                    <span class="material-icons-round" style="font-size:18px!important;color:#F97316">add_box</span>
                </div>
                <div>
                    <div class="m-hi-title">Add Category</div>
                    <div class="m-hi-sub">Give it a clear, descriptive name</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('addCategoryModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="addCategoryForm">
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">
                            <span class="material-icons-round">label</span>Category Name
                        </label>
                        <input class="form-input" type="text" name="name" required
                               placeholder="e.g. Politics, Technology, Sports…"
                               autocomplete="off"/>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('addCategoryModal')" class="btn btn-outline">Cancel</button>
            <button type="button" id="addCatSubmitBtn"
                    onclick="document.getElementById('addCategoryForm').requestSubmit()"
                    class="btn btn-purple">
                <span class="material-icons-round">add</span>Add Category
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     EDIT CATEGORY MODAL
═══════════════════════════════════════════════════════ -->
<div id="editCategoryModal" class="modal-bg">
    <div class="modal-box" style="max-width:400px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-ico" style="background:var(--purple-light)">
                    <span class="material-icons-round" style="font-size:18px!important;color:var(--purple)">edit</span>
                </div>
                <div>
                    <div class="m-hi-title">Edit Category</div>
                    <div class="m-hi-sub" id="editCatSubtitle">—</div>
                </div>
            </div>
            <button class="m-close" onclick="closeModal('editCategoryModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <form id="editCategoryForm">
                    <input type="hidden" id="editCategoryId" name="id"/>
                    <div class="form-group" style="margin-bottom:0">
                        <label class="form-label">
                            <span class="material-icons-round">label</span>Category Name
                        </label>
                        <input class="form-input" type="text" id="editCategoryName" name="name"
                               required autocomplete="off"/>
                    </div>
                </form>
            </div>
        </div>
        <div class="m-foot">
            <button type="button" onclick="closeModal('editCategoryModal')" class="btn btn-outline">Cancel</button>
            <button type="button" id="editCatSubmitBtn"
                    onclick="document.getElementById('editCategoryForm').requestSubmit()"
                    class="btn btn-purple">
                <span class="material-icons-round">save</span>Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
═══════════════════════════════════════════════════════ -->
<div id="deleteCategoryModal" class="modal-bg">
    <div class="modal-box" style="max-width:380px">
        <div class="m-hd" style="border-bottom:none;padding-bottom:0">
            <div style="flex:1"></div>
            <button class="m-close" onclick="closeModal('deleteCategoryModal')">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="m-body" style="padding-top:8px;text-align:center">
            <div style="width:60px;height:60px;border-radius:50%;background:#FFF1F2;border:2px solid #FECDD3;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
                <span class="material-icons-round" style="font-size:28px!important;color:#DC2626">label_off</span>
            </div>
            <div style="font-family:'Playfair Display',serif;font-size:19px;font-weight:700;margin-bottom:8px">Delete Category</div>
            <p style="font-size:13px;color:var(--ink-muted);line-height:1.7">
                Delete <strong id="deleteCatNameDisplay" style="color:var(--ink)"></strong>?<br/>
                <span style="color:#DC2626;font-size:12px">This action cannot be undone.</span>
            </p>
            <!-- Warning when articles exist -->
            <div id="deleteCatImpact" style="display:none;margin-top:12px;padding:10px 13px;background:#FFFBEB;border:1px solid #FDE68A;border-radius:9px;text-align:left">
                <div style="display:flex;align-items:flex-start;gap:7px">
                    <span class="material-icons-round" style="font-size:15px!important;color:#D97706;flex-shrink:0;margin-top:1px">info</span>
                    <div style="font-size:12px;color:#92400E;line-height:1.6">
                        <strong id="deleteCatCount"></strong> article(s) currently use this category.
                        Deleting it may affect those articles.
                    </div>
                </div>
            </div>
        </div>
        <div class="m-foot" style="justify-content:center;gap:10px">
            <input type="hidden" id="deleteCategoryId"/>
            <button onclick="closeModal('deleteCategoryModal')" class="btn btn-outline" style="min-width:110px">Cancel</button>
            <button id="deleteCatConfirmBtn" onclick="confirmDeleteCategory()"
                    class="btn" style="background:#DC2626;color:white;min-width:110px">
                <span class="material-icons-round">delete_forever</span>Delete
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    /* ─── Live search filter ─────────────────────────────── */
    const searchInput  = document.getElementById('catSearch');
    const clearBtn     = document.getElementById('catSearchClear');
    const countBadge   = document.getElementById('catCountBadge');
    const noResults    = document.getElementById('catNoResults');
    const totalOnPage  = document.querySelectorAll('.cat-row').length;

    function filterCats(q) {
        const term = q.trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('.cat-row').forEach(row => {
            const match = !term || row.dataset.name.includes(term);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });
        clearBtn.classList.toggle('visible', term.length > 0);
        noResults.classList.toggle('show', visible === 0 && term.length > 0);
        countBadge.innerHTML = `<strong>${visible}</strong> / <?= $totalCats ?? count($categories) ?>`;
    }

    searchInput?.addEventListener('input', () => filterCats(searchInput.value));
    clearBtn?.addEventListener('click', () => { searchInput.value = ''; filterCats(''); searchInput.focus(); });

    /* ─── Edit button listeners ──────────────────────────── */
    document.querySelectorAll('.edit-category').forEach(btn => {
        btn.addEventListener('click', () => {
            const id   = btn.dataset.id;
            const name = btn.dataset.name;
            document.getElementById('editCategoryId').value   = id;
            document.getElementById('editCategoryName').value = name;
            document.getElementById('editCatSubtitle').textContent = 'ID: ' + id;
            openModal('editCategoryModal');
        });
    });

    /* ─── Delete button listeners ────────────────────────── */
    document.querySelectorAll('.delete-category').forEach(btn => {
        btn.addEventListener('click', () => {
            const id    = btn.dataset.id;
            const name  = btn.dataset.name;
            const count = parseInt(btn.dataset.count || '0', 10);

            document.getElementById('deleteCategoryId').value         = id;
            document.getElementById('deleteCatNameDisplay').textContent = name;

            const impact = document.getElementById('deleteCatImpact');
            if (count > 0) {
                document.getElementById('deleteCatCount').textContent = count;
                impact.style.display = 'block';
            } else {
                impact.style.display = 'none';
            }

            openModal('deleteCategoryModal');
        });
    });

    /* ─── Add Category Form ──────────────────────────────── */
    document.getElementById('addCategoryForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('addCatSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
        try {
            const res  = await fetch('actions/add_category.php', { method: 'POST', body: new FormData(e.target) });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Category added successfully', 'success');
                closeModal('addCategoryModal');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(data.message || 'Failed to add category', 'error');
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons-round">add</span>Add Category';
            }
        } catch (err) {
            showToast('Error adding category', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">add</span>Add Category';
        }
    });

    /* ─── Edit Category Form ─────────────────────────────── */
    document.getElementById('editCategoryForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('editCatSubmitBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Saving…';
        try {
            const res  = await fetch('actions/edit_category.php', { method: 'POST', body: new FormData(e.target) });
            const data = await res.json();
            if (data.success) {
                showToast(data.message || 'Category updated successfully', 'success');
                closeModal('editCategoryModal');
                setTimeout(() => location.reload(), 900);
            } else {
                showToast(data.message || 'Failed to update category', 'error');
                btn.disabled = false;
                btn.innerHTML = '<span class="material-icons-round">save</span>Save Changes';
            }
        } catch (err) {
            showToast('Error updating category', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">save</span>Save Changes';
        }
    });
})();

/* ─── Delete Category (global scope for button onclick) ─── */
async function confirmDeleteCategory() {
    const id  = document.getElementById('deleteCategoryId').value;
    const btn = document.getElementById('deleteCatConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Deleting…';
    try {
        const fd = new FormData(); fd.append('id', id);
        const res  = await fetch('actions/delete_category.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Category deleted', 'success');
            closeModal('deleteCategoryModal');
            setTimeout(() => location.reload(), 900);
        } else {
            showToast(data.message || 'Failed to delete category', 'error');
            btn.disabled = false;
            btn.innerHTML = '<span class="material-icons-round">delete_forever</span>Delete';
        }
    } catch (err) {
        showToast('Error deleting category', 'error');
        btn.disabled = false;
        btn.innerHTML = '<span class="material-icons-round">delete_forever</span>Delete';
    }
}
</script>