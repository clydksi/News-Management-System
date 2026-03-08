<?php
/**
 * Access Control Helper
 * Path: admin/includes/access_control.php
 */
function getVisibleDepartmentIds(PDO $pdo, array $session): ?array
{
    $role       = $session['role']       ?? 'user';
    $userId     = $session['user_id']    ?? 0;
    $userDeptId = $session['dept_id']    ?? 0;
    $viewScope  = $session['view_scope'] ?? 'own';

    // Superadmin or explicit 'all' scope → no filter
    if ($role === 'superadmin' || $viewScope === 'all') {
        return null;
    }

    // Always start with own department
    $ids = $userDeptId ? [(int)$userDeptId] : [];

    // ── User-level individual grants ──────────────────────────────────────────
    if ($viewScope === 'granted') {
        $stmt = $pdo->prepare("
            SELECT department_id
            FROM   department_access
            WHERE  user_id = ?
        ");
        $stmt->execute([$userId]);
        $userGrants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $ids = array_merge($ids, array_map('intval', $userGrants));
    }

    // ── Department-level grants (inherited automatically) ─────────────────────
    // Checked regardless of view_scope — if the whole dept was granted access,
    // every user in it inherits it without needing view_scope = 'granted'
    if ($userDeptId) {
        $stmt = $pdo->prepare("
            SELECT target_dept_id
            FROM   department_visibility
            WHERE  source_dept_id = ?
        ");
        $stmt->execute([$userDeptId]);
        $deptGrants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($deptGrants)) {
            $ids = array_merge($ids, array_map('intval', $deptGrants));
        }
    }

    // Deduplicate
    $ids = array_values(array_unique($ids));

    return !empty($ids) ? $ids : [0];
}

/**
 * Builds a safe SQL WHERE clause fragment for department filtering.
 *
 * @param  array|null $ids    NULL = no filter (superadmin/all)
 * @param  string     $column Column to filter on (include table alias)
 * @return array              [string $whereClause, array $params]
 */
function buildDeptWhere(?array $ids, string $column = 'd.id'): array
{
    if ($ids === null) {
        return ['1=1', []];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    return ["{$column} IN ({$placeholders})", $ids];
}