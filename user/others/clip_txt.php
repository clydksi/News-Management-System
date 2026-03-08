<?php
/**
 * clip_txt.php — Return raw .txt file content for a clip
 * GET ?path=2026-02-27/Topic/ClipFolder/file.txt
 */
define('CLIPS_BASE_PATH', 'Z:\\');

require dirname(__DIR__, 2) . '/auth.php';

header('Content-Type: text/plain; charset=UTF-8');

if (empty($_SESSION['user_id'])) { http_response_code(401); exit; }

$rel = trim($_GET['path'] ?? '');
if (empty($rel)) { http_response_code(400); echo 'No path'; exit; }

// Sanitise — block path traversal
$rel = str_replace(['..', "\0"], '', $rel);
$rel = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel), DIRECTORY_SEPARATOR);

$full = CLIPS_BASE_PATH . $rel;

if (!is_file($full) || strtolower(pathinfo($full, PATHINFO_EXTENSION)) !== 'txt') {
    http_response_code(404); echo 'File not found'; exit;
}

readfile($full);