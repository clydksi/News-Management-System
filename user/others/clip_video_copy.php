<?php
/**
 * clip_video_copy.php
 *
 * Copies a video from \\172.16.103.13\OpusClips\ (UNC — accessible by IIS)
 * to the local public /others/videos/ folder so Buffer/Zapier can reach it.
 *
 * POST params:
 *   video_path — relative path e.g. "2026-02-28/ShowName/Clip 1/clip.mp4"
 *
 * Returns JSON:
 *   { success: true,  public_url: "http://newsnetwork.mbcradio.net/crud/user/others/videos/abc.mp4" }
 *   { success: false, message: "...", debug: {...} }
 */

// ─── CONFIG ───────────────────────────────────────────────────────────────────

// UNC path — works for IIS/PHP service accounts, unlike mapped drive letters
define('CLIPS_BASE_PATH', '\\\\172.16.103.13\\OpusClips\\');

// Absolute filesystem path to destination folder (same folder as this file + /videos/)
// __DIR__ = C:\...\crud\user\others  →  videos subfolder lives there
define('VIDEOS_FS_PATH', __DIR__ . '\\videos\\');

// Public URL for that folder
define('VIDEOS_PUBLIC_BASE', 'https://newsnetwork.mbcradio.net/crud/user/others/videos/');

// Allowed extensions
define('ALLOWED_EXTS', ['mp4', 'mov', 'webm', 'mkv', 'avi']);

// ─────────────────────────────────────────────────────────────────────────────

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function jsonOut(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(['success' => false, 'message' => 'POST required']);
}

$videoPath = trim($_POST['video_path'] ?? '');
if (!$videoPath) {
    jsonOut(['success' => false, 'message' => 'video_path is required']);
}

// ── Security: block path traversal ───────────────────────────────────────────
$videoPath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $videoPath);
foreach (explode(DIRECTORY_SEPARATOR, $videoPath) as $part) {
    if ($part === '..' || $part === '.') {
        jsonOut(['success' => false, 'message' => 'Invalid path']);
    }
}

// ── Validate extension ────────────────────────────────────────────────────────
$ext = strtolower(pathinfo($videoPath, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTS, true)) {
    jsonOut(['success' => false, 'message' => 'File type not allowed: ' . $ext]);
}

// ── Build source path using UNC ───────────────────────────────────────────────
$sourcePath = CLIPS_BASE_PATH . $videoPath;

// ── Ensure destination folder exists ─────────────────────────────────────────
$destDir = VIDEOS_FS_PATH;
if (!is_dir($destDir)) {
    if (!mkdir($destDir, 0755, true)) {
        jsonOut([
            'success' => false,
            'message' => 'Cannot create destination folder',
            'dest_dir' => $destDir,
        ]);
    }
}

// ── Stable filename: md5 of relative path (same clip = same filename = cache) ─
$destFilename = md5($videoPath) . '.' . $ext;
$destPath     = $destDir . $destFilename;
$publicUrl    = VIDEOS_PUBLIC_BASE . $destFilename;

// ── Try to open source first — gives clear error if UNC is unreachable ────────
$src = @fopen($sourcePath, 'rb');
if (!$src) {
    jsonOut([
        'success'          => false,
        'message'          => 'Cannot read source file — IIS may lack permission to UNC share',
        'source_path'      => $sourcePath,
        'source_exists'    => file_exists($sourcePath),
        'source_readable'  => is_readable($sourcePath),
        'php_user'         => get_current_user(),
        'open_basedir'     => ini_get('open_basedir') ?: 'none',
        'fix'              => 'Grant the IIS app pool identity (e.g. IIS AppPool\DefaultAppPool) read access to \\\\172.16.103.13\\OpusClips in share/NTFS permissions',
    ]);
}

// ── Return cached copy if it already exists and size matches ─────────────────
if (is_file($destPath)) {
    $destSize   = filesize($destPath);
    $sourceSize = filesize($sourcePath);
    if ($destSize === $sourceSize && $destSize > 0) {
        fclose($src);
        jsonOut(['success' => true, 'public_url' => $publicUrl, 'filename' => $destFilename, 'cached' => true]);
    }
}

// ── Open destination ──────────────────────────────────────────────────────────
$dst = @fopen($destPath, 'wb');
if (!$dst) {
    fclose($src);
    jsonOut([
        'success'        => false,
        'message'        => 'Cannot write to destination folder',
        'dest_path'      => $destPath,
        'dest_dir'       => $destDir,
        'dest_writable'  => is_writable($destDir),
        'php_user'       => get_current_user(),
        'fix'            => 'Give IIS write permission to the videos folder: ' . $destDir,
    ]);
}

// ── Stream copy (safe for large files) ───────────────────────────────────────
set_time_limit(300);
$sourceSize = filesize($sourcePath);
$copied     = stream_copy_to_stream($src, $dst);
fclose($src);
fclose($dst);

if ($copied === false || ($sourceSize > 0 && $copied < $sourceSize)) {
    @unlink($destPath);
    jsonOut([
        'success' => false,
        'message' => 'Copy incomplete: ' . $copied . ' of ' . $sourceSize . ' bytes copied',
    ]);
}

jsonOut([
    'success'    => true,
    'public_url' => $publicUrl,
    'filename'   => $destFilename,
    'cached'     => false,
    'bytes'      => $copied,
]);