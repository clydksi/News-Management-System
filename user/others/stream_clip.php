<?php
/**
 * stream_clip.php — Secure OpusClips Video/Image Streamer
 *
 * Features:
 *  - Auth gate (same pattern as push.php)
 *  - DB access logging (tracks who streamed what and when)
 *  - HTTP Range support (seek/scrub in HTML5 video player)
 *  - Path traversal protection (no realpath dependency)
 *  - Debug mode (?debug=1)
 *
 * Usage: stream_clip.php?file=2026-02-27/Topic/Clip Folder/file.mp4
 */

require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

// ─── Config ───────────────────────────────────────────────────────────────────
define('CLIPS_BASE_PATH', 'Z:\\');

const ALLOWED_EXTS = [
    'mp4'  => 'video/mp4',
    'mov'  => 'video/quicktime',
    'webm' => 'video/webm',
    'mkv'  => 'video/x-matroska',
    'avi'  => 'video/x-msvideo',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
];

// ─── Helpers ──────────────────────────────────────────────────────────────────
function normalisePath(string $p): string {
    $isUnc = (substr($p, 0, 2) === '\\\\');
    $parts  = array_filter(explode('\\', str_replace('/', '\\', $p)), 'strlen');
    $stack  = [];
    foreach ($parts as $part) {
        if ($part === '.')  continue;
        if ($part === '..') { array_pop($stack); continue; }
        $stack[] = $part;
    }
    $result = implode('\\', $stack);
    return $isUnc ? ('\\\\' . $result) : $result;
}

function parseRelativePath(string $raw): string {
    $decoded = urldecode($raw);
    $decoded = str_replace('/', '\\', $decoded);
    return ltrim($decoded, '\\/');
}

/**
 * Log a clip stream/view event to the database.
 * Table: clip_access_log (auto-created if missing)
 */
function logClipAccess(PDO $pdo, int $userId, string $relPath, string $action = 'stream'): void {
    try {
        // Create table on first use — no migration needed
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS clip_access_log (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                user_id       INT NOT NULL,
                clip_path     VARCHAR(1000) NOT NULL,
                clip_filename VARCHAR(255)  NOT NULL,
                action        ENUM('stream','download','thumbnail') NOT NULL DEFAULT 'stream',
                ip_address    VARCHAR(45)   DEFAULT NULL,
                user_agent    VARCHAR(500)  DEFAULT NULL,
                accessed_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user    (user_id),
                INDEX idx_clip    (clip_path(255)),
                INDEX idx_accessed(accessed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        $stmt = $pdo->prepare("
            INSERT INTO clip_access_log
                (user_id, clip_path, clip_filename, action, ip_address, user_agent, accessed_at)
            VALUES
                (:user_id, :clip_path, :clip_filename, :action, :ip, :ua, NOW())
        ");
        $stmt->execute([
            ':user_id'       => $userId,
            ':clip_path'     => $relPath,
            ':clip_filename' => basename(str_replace('\\', '/', $relPath)),
            ':action'        => $action,
            ':ip'            => $_SERVER['REMOTE_ADDR'] ?? null,
            ':ua'            => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    } catch (Throwable $e) {
        // Never let a logging failure kill the stream
        error_log('clip_access_log insert failed: ' . $e->getMessage());
    }
}

// ─── DEBUG MODE ───────────────────────────────────────────────────────────────
if (isset($_GET['debug'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $base = rtrim(CLIPS_BASE_PATH, '\\/');

    echo "=== stream_clip.php DIAGNOSTICS ===\n\n";
    echo "PHP version        : " . PHP_VERSION . "\n";
    echo "Server software    : " . ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown') . "\n";
    echo "Auth user_id       : " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
    echo "CLIPS_BASE_PATH    : " . CLIPS_BASE_PATH . "\n\n";

    echo "--- Drive / Path checks ---\n";
    echo "is_dir(Z:\\)        : " . (is_dir('Z:\\')      ? 'YES' : 'NO') . "\n";
    echo "is_readable(Z:\\)   : " . (is_readable('Z:\\') ? 'YES' : 'NO') . "\n";

    $unc = '\\\\172.16.103.13\\OpusClips';
    echo "is_dir(UNC)        : " . (is_dir($unc)      ? 'YES' : 'NO') . "\n";
    echo "is_readable(UNC)   : " . (is_readable($unc) ? 'YES' : 'NO') . "\n\n";

    echo "--- scandir(Z:\\) ---\n";
    $scan = @scandir('Z:\\');
    if ($scan === false) {
        $err = error_get_last();
        echo "FAILED: " . ($err['message'] ?? 'unknown') . "\n";
        echo "\nTIP: IIS app pool identity cannot see mapped drives.\n";
        echo "Fix: Change CLIPS_BASE_PATH to '\\\\172.16.103.13\\OpusClips\\'\n";
    } else {
        echo "OK — " . count($scan) . " entries:\n";
        foreach (array_slice($scan, 2, 15) as $e) echo "  $e\n";
    }

    // DB check
    echo "\n--- Database ---\n";
    try {
        $pdo->query("SELECT 1");
        echo "PDO connection  : OK\n";
        // Check if log table exists
        $t = $pdo->query("SHOW TABLES LIKE 'clip_access_log'")->fetchColumn();
        echo "clip_access_log : " . ($t ? 'EXISTS' : 'will be auto-created on first stream') . "\n";
        if ($t) {
            $count = $pdo->query("SELECT COUNT(*) FROM clip_access_log")->fetchColumn();
            echo "log row count   : $count\n";
        }
    } catch (Throwable $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }

    // File test
    if (!empty($_GET['file'])) {
        $rel      = parseRelativePath($_GET['file']);
        $fullPath = $base . '\\' . $rel;
        echo "\n--- File test ---\n";
        echo "Relative path   : " . $rel . "\n";
        echo "Full path       : " . $fullPath . "\n";
        echo "file_exists     : " . (file_exists($fullPath) ? 'YES' : 'NO') . "\n";
        echo "is_file         : " . (is_file($fullPath)     ? 'YES' : 'NO') . "\n";
        echo "is_readable     : " . (is_readable($fullPath) ? 'YES' : 'NO') . "\n";
        $sz = @filesize($fullPath);
        echo "filesize        : " . ($sz !== false ? number_format($sz) . ' bytes' : 'FAILED — ' . (error_get_last()['message'] ?? '')) . "\n";
    }
    exit;
}

// ─── Input validation ─────────────────────────────────────────────────────────
$rawFile = trim($_GET['file'] ?? '');

if ($rawFile === '') {
    http_response_code(400);
    exit('Missing file parameter.');
}

$relative = parseRelativePath($rawFile);

if (str_contains($relative, '..')) {
    http_response_code(403);
    exit('Forbidden.');
}

$base     = rtrim(CLIPS_BASE_PATH, '\\/');
$fullPath = $base . '\\' . $relative;

// ─── Path containment check ───────────────────────────────────────────────────
$normBase = normalisePath($base);
$normFull = normalisePath($fullPath);

if (stripos($normFull, $normBase . '\\') !== 0) {
    http_response_code(403);
    exit('Forbidden.');
}

// ─── File existence & type ────────────────────────────────────────────────────
if (!file_exists($fullPath) || !is_file($fullPath)) {
    http_response_code(404);
    exit('File not found.');
}

$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (!array_key_exists($ext, ALLOWED_EXTS)) {
    http_response_code(403);
    exit('File type not allowed.');
}

$mime     = ALLOWED_EXTS[$ext];
$fileSize = @filesize($fullPath);

if ($fileSize === false) {
    http_response_code(500);
    exit('Could not read file size.');
}

// ─── Determine action type for logging ───────────────────────────────────────
$isImage    = str_starts_with($mime, 'image/');
$isDownload = isset($_GET['download']);
$action     = $isImage ? 'thumbnail' : ($isDownload ? 'download' : 'stream');

// ─── Log access ───────────────────────────────────────────────────────────────
$currentUserId = $_SESSION['user_id'] ?? 0;
logClipAccess($pdo, $currentUserId, $relative, $action);

// ─── Range-request support ────────────────────────────────────────────────────
$start  = 0;
$end    = $fileSize - 1;
$length = $fileSize;

if (!empty($_SERVER['HTTP_RANGE'])) {
    if (!preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $m)) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
    $rs = ($m[1] !== '') ? (int)$m[1] : 0;
    $re = ($m[2] !== '') ? (int)$m[2] : $fileSize - 1;

    if ($rs > $re || $re >= $fileSize) {
        header('HTTP/1.1 416 Requested Range Not Satisfiable');
        header('Content-Range: bytes */' . $fileSize);
        exit;
    }
    $start  = $rs;
    $end    = $re;
    $length = $end - $start + 1;
    http_response_code(206);
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
} else {
    http_response_code(200);
}

// ─── Response headers ─────────────────────────────────────────────────────────
$disposition = $isDownload ? 'attachment' : 'inline';
header('Content-Type: '        . $mime);
header('Content-Length: '      . $length);
header('Accept-Ranges: bytes');
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes(basename($fullPath)) . '"');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

// ─── Stream ───────────────────────────────────────────────────────────────────
while (ob_get_level() > 0) { ob_end_clean(); }

$fh = @fopen($fullPath, 'rb');
if ($fh === false) {
    http_response_code(500);
    exit('Cannot open file.');
}

if ($start > 0) fseek($fh, $start);

$bufSize   = 1024 * 256; // 256 KB chunks
$remaining = $length;

while (!feof($fh) && $remaining > 0 && !connection_aborted()) {
    $toRead = min($bufSize, $remaining);
    $chunk  = fread($fh, $toRead);
    if ($chunk === false) break;
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}

fclose($fh);
exit;