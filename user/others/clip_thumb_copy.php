<?php
/**
 * clip_thumb_copy.php — Copy a clip's JPG thumbnail to the uploads folder
 * POST: thumb_path = relative path within OpusClips share
 * Returns JSON: { success, file, web_path }
 */
define('CLIPS_BASE_PATH', 'Z:\\');
define('UPLOADS_DIR', dirname(__DIR__, 2) . '/user/uploads/');
define('UPLOADS_WEB', 'uploads/');

require dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: application/json; charset=UTF-8');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$thumbRel = trim($_POST['thumb_path'] ?? '');
if (empty($thumbRel)) {
    echo json_encode(['success' => false, 'message' => 'No thumb_path provided']);
    exit;
}

// Sanitise — block path traversal
$thumbRel = str_replace(['..', "\0"], '', $thumbRel);
$thumbRel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $thumbRel), DIRECTORY_SEPARATOR);
$thumbFull = CLIPS_BASE_PATH . $thumbRel;

if (!@is_file($thumbFull)) {
    echo json_encode(['success' => false, 'message' => 'Thumbnail file not found: ' . $thumbFull]);
    exit;
}

// Ensure uploads dir exists
if (!is_dir(UPLOADS_DIR)) {
    @mkdir(UPLOADS_DIR, 0755, true);
}

$ext      = strtolower(pathinfo($thumbRel, PATHINFO_EXTENSION));
$safeExt  = in_array($ext, ['jpg','jpeg','png','webp']) ? $ext : 'jpg';
$newName  = 'thumb_' . md5($thumbRel) . '.' . $safeExt;
$destPath = UPLOADS_DIR . $newName;
$webPath  = UPLOADS_WEB . $newName;

// Already copied previously — return immediately
if (file_exists($destPath)) {
    echo json_encode(['success' => true, 'file' => $newName, 'web_path' => $webPath, 'cached' => true]);
    exit;
}

if (@copy($thumbFull, $destPath)) {
    echo json_encode(['success' => true, 'file' => $newName, 'web_path' => $webPath, 'cached' => false]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to copy thumbnail to uploads folder']);
}