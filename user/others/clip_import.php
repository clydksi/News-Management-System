<?php
/**
 * clip_import.php — Import an OpusClip into the news system
 *
 * Expects POST:
 *   title          — article title
 *   content        — fallback content (transcript from JS)
 *   txt_path       — relative path within OpusClips share to the .txt file
 *   thumb_web_path — already-resolved web path from clip_thumb_copy.php (e.g. uploads/thumb_xxx.jpg)
 *
 * Returns JSON: { success, id, image, message }
 */

define('CLIPS_BASE_PATH', 'Z:\\');

require dirname(__DIR__, 2) . '/db.php';
require dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$title        = trim($_POST['title']          ?? '');
$fallback     = trim($_POST['content']        ?? '');
$txtRel       = trim($_POST['txt_path']       ?? '');
$thumbWebPath = trim($_POST['thumb_web_path'] ?? '');
$userId       = (int) ($_SESSION['user_id']       ?? 0);
$deptId       = (int) ($_SESSION['department_id'] ?? 0);

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}
if (!$userId || !$deptId) {
    echo json_encode(['success' => false, 'message' => 'Session user/department missing']);
    exit;
}

// ── 1. Read .txt file as content ─────────────────────────────────────────────
$content = $fallback;
if (!empty($txtRel)) {
    $txtRel  = str_replace(['..', "\0"], '', $txtRel);
    $txtRel  = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $txtRel), DIRECTORY_SEPARATOR);
    $raw     = @file_get_contents(CLIPS_BASE_PATH . $txtRel);
    if ($raw !== false) {
        $content = $raw;
    }
}

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'No content available to import']);
    exit;
}

// ── 2. Thumbnail — already copied by clip_thumb_copy.php ────────────────────
$thumbnail = !empty($thumbWebPath) ? $thumbWebPath : null;

// ── 3. Duplicate check ───────────────────────────────────────────────────────
try {
    $dup = $pdo->prepare("SELECT id FROM news WHERE title = ? LIMIT 1");
    $dup->execute([$title]);
    if ($existing = $dup->fetchColumn()) {
        echo json_encode([
            'success'     => false,
            'message'     => 'Already imported',
            'existing_id' => $existing,
        ]);
        exit;
    }

    // ── 4. Insert article ────────────────────────────────────────────────────
    if ($thumbnail) {
        $stmt = $pdo->prepare("
            INSERT INTO news (title, content, thumbnail, created_by, department_id, is_translated, created_at)
            VALUES (?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$title, $content, $thumbnail, $userId, $deptId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO news (title, content, created_by, department_id, is_translated, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$title, $content, $userId, $deptId]);
    }

    echo json_encode([
        'success'   => true,
        'id'        => (int) $pdo->lastInsertId(),
        'thumbnail' => $thumbnail,
        'message'   => 'Imported successfully',
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
    ]);
}