<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/admin/includes/access_control.php';

$visibleDeptIds = getVisibleDepartmentIds($pdo, $_SESSION);
$isAdmin        = in_array($_SESSION['role'], ['admin', 'superadmin']);
[$dw_n, $dp_n]  = buildDeptWhere($visibleDeptIds, 'n.department_id');

// Apply same filters as user_dashboard.php
$filterCategory   = $_GET['filter_category']   ?? '';
$filterDepartment = $_GET['filter_department']  ?? '';
$filterAuthor     = $_GET['filter_author']      ?? '';
$filterDateFrom   = $_GET['filter_date_from']   ?? '';
$filterDateTo     = $_GET['filter_date_to']     ?? '';
$filterSearch     = $_GET['filter_search']      ?? '';
$filterStatus     = $_GET['filter_status']      ?? '';
$section          = $_GET['section']            ?? 'all';

$whereClauses = [$dw_n];
$params       = $dp_n;

if ($section === 'archive') {
    $whereClauses[] = "n.is_pushed = 3";
} elseif ($filterStatus !== '') {
    $whereClauses[] = "n.is_pushed = ?"; $params[] = intval($filterStatus);
} else {
    $whereClauses[] = "n.is_pushed != 3";
}

if (!empty($filterCategory))               { $whereClauses[] = "n.category_id = ?";      $params[] = $filterCategory; }
if (!empty($filterDepartment) && $isAdmin) { $whereClauses[] = "n.department_id = ?";    $params[] = $filterDepartment; }
if (!empty($filterAuthor))                 { $whereClauses[] = "n.created_by = ?";        $params[] = $filterAuthor; }
if (!empty($filterDateFrom))               { $whereClauses[] = "DATE(n.created_at) >= ?"; $params[] = $filterDateFrom; }
if (!empty($filterDateTo))                 { $whereClauses[] = "DATE(n.created_at) <= ?"; $params[] = $filterDateTo; }
if (!empty($filterSearch)) {
    $whereClauses[] = "(n.title LIKE ? OR n.content LIKE ?)";
    $params[] = "%{$filterSearch}%"; $params[] = "%{$filterSearch}%";
}

$where = " WHERE " . implode(" AND ", $whereClauses);
$query = "SELECT n.id, n.title, n.content, u.username, d.name AS dept_name,
                 c.name AS category_name, n.is_pushed, n.created_at,
                 n.pending_approval
          FROM   news n
          JOIN   users u ON n.created_by = u.id
          JOIN   departments d ON n.department_id = d.id
          LEFT JOIN categories c ON n.category_id = c.id
          {$where}
          ORDER BY n.created_at DESC
          LIMIT 10000";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    // pending_approval column may not exist yet — retry without it
    $query2 = str_replace('n.pending_approval', '0 AS pending_approval', $query);
    $stmt = $pdo->prepare($query2);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
}

$statusLabels = [0 => 'Regular', 1 => 'Edited', 2 => 'Headline', 3 => 'Archive'];

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="articles-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-cache, no-store');

$out = fopen('php://output', 'w');
// UTF-8 BOM so Excel opens correctly
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['ID', 'Title', 'Content', 'Author', 'Department', 'Category', 'Status', 'Pending Approval', 'Created At']);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['title'],
        strip_tags($row['content']),
        $row['username'],
        $row['dept_name'],
        $row['category_name'] ?? 'Uncategorized',
        $statusLabels[$row['is_pushed']] ?? 'Unknown',
        $row['pending_approval'] ? 'Yes' : 'No',
        $row['created_at'],
    ]);
}

fclose($out);
exit;
