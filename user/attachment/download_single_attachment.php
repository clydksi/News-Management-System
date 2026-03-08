<?php
/**
 * download_single_attachment.php
 * Securely serves a single attachment as a forced download.
 *
 * Usage: download_single_attachment.php?id={news_id}&attachment_id={attachment_id}
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

// ─── Helpers ─────────────────────────────────────────────────────────────────

function e(mixed $s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8');
}

function sendErrorPage(string $title, string $message, int $httpCode = 404): never
{
    http_response_code($httpCode);
    header('Content-Type: text/html; charset=utf-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8"/>
        <meta name="viewport" content="width=device-width, initial-scale=1"/>
        <title>{$title}</title>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600&display=swap" rel="stylesheet"/>
        <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Sora', sans-serif;
                background: #F3F1FA;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .card {
                background: #fff;
                border-radius: 16px;
                padding: 48px 40px;
                max-width: 420px;
                width: 100%;
                text-align: center;
                box-shadow: 0 12px 40px rgba(60,20,120,.14);
                border: 1px solid #E2DDEF;
            }
            .icon-wrap {
                width: 72px; height: 72px; border-radius: 50%;
                background: #FFF1F2; border: 2px solid #FECDD3;
                display: flex; align-items: center; justify-content: center;
                margin: 0 auto 20px;
            }
            .icon-wrap .material-icons-round { font-size: 32px !important; color: #DC2626; }
            h1 { font-size: 20px; font-weight: 700; color: #13111A; margin-bottom: 8px; }
            p  { font-size: 13px; color: #4A4560; line-height: 1.6; margin-bottom: 28px; }
            .btn-row { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
            .btn {
                display: inline-flex; align-items: center; gap: 6px;
                padding: 10px 22px; border-radius: 9px; border: none;
                font-family: 'Sora', sans-serif; font-size: 13px; font-weight: 600;
                cursor: pointer; text-decoration: none; transition: all .15s;
            }
            .btn-primary { background: #7C3AED; color: white; }
            .btn-primary:hover { background: #6D28D9; }
            .btn-ghost { background: transparent; border: 1px solid #E2DDEF; color: #4A4560; }
            .btn-ghost:hover { background: #F5F3FF; color: #7C3AED; border-color: #C4B5FD; }
            .btn .material-icons-round { font-size: 16px !important; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="icon-wrap">
                <span class="material-icons-round">error_outline</span>
            </div>
            <h1>{$title}</h1>
            <p>{$message}</p>
            <div class="btn-row">
                <button onclick="history.back()" class="btn btn-ghost">
                    <span class="material-icons-round">arrow_back</span>Go Back
                </button>
                <button onclick="window.close()" class="btn btn-primary">
                    <span class="material-icons-round">close</span>Close
                </button>
            </div>
        </div>
    </body>
    </html>
    HTML;
    exit;
}

/**
 * Resolve a stored file path to an actual path on disk.
 * Mirrors the findFile() logic in the attachment viewer.
 */
function findFile(string $storedPath): string|false
{
    if (file_exists($storedPath) && is_file($storedPath)) {
        return $storedPath;
    }

    $basename = basename($storedPath);

    $candidates = [
        __DIR__ . '/../uploads/' . $basename,
        __DIR__ . '/../uploads/attachments/' . $basename,
        __DIR__ . '/../../uploads/' . $basename,
        __DIR__ . '/../../uploads/attachments/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/uploads/attachments/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/uploads/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/uploads/attachments/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/admin/uploads/' . $basename,
        $_SERVER['DOCUMENT_ROOT'] . '/news/admin/uploads/attachments/' . $basename,
        dirname(__DIR__) . '/' . $storedPath,
        dirname(__DIR__, 2) . '/' . $storedPath,
        $_SERVER['DOCUMENT_ROOT'] . '/' . $storedPath,
        '/var/www/html/news/uploads/' . $basename,
        '/var/www/html/news/uploads/attachments/' . $basename,
        '/var/www/html/uploads/' . $basename,
        '/var/www/html/uploads/attachments/' . $basename,
    ];

    foreach ($candidates as $path) {
        if (file_exists($path) && is_file($path)) {
            return realpath($path);
        }
    }

    return false;
}

/**
 * Derive a safe MIME type from file extension as fallback.
 */
function guessMimeType(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $map = [
        // Documents
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'txt'  => 'text/plain',
        'csv'  => 'text/csv',
        // Images
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'bmp'  => 'image/bmp',
        // Video
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'avi'  => 'video/x-msvideo',
        'mov'  => 'video/quicktime',
        'wmv'  => 'video/x-ms-wmv',
        'mkv'  => 'video/x-matroska',
        'flv'  => 'video/x-flv',
        'ogv'  => 'video/ogg',
        '3gp'  => 'video/3gpp',
        // Audio
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac'  => 'audio/aac',
        'm4a'  => 'audio/mp4',
        'wma'  => 'audio/x-ms-wma',
        'opus' => 'audio/opus',
        // Archives
        'zip'  => 'application/zip',
        'rar'  => 'application/vnd.rar',
        '7z'   => 'application/x-7z-compressed',
        'tar'  => 'application/x-tar',
        'gz'   => 'application/gzip',
    ];

    return $map[$ext] ?? 'application/octet-stream';
}

// ─── Input Validation ─────────────────────────────────────────────────────────

if (empty($_GET['id']) || empty($_GET['attachment_id'])) {
    sendErrorPage('Missing Parameters', 'Both a news ID and attachment ID are required to download a file.');
}

$newsId       = intval($_GET['id']);
$attachmentId = intval($_GET['attachment_id']);

if ($newsId <= 0 || $attachmentId <= 0) {
    sendErrorPage('Invalid Parameters', 'The provided IDs are not valid.');
}

// ─── Fetch News Record ────────────────────────────────────────────────────────

try {
    $newsStmt = $pdo->prepare(
        "SELECT n.id, n.title, n.department_id
         FROM news n
         WHERE n.id = ?
         LIMIT 1"
    );
    $newsStmt->execute([$newsId]);
    $news = $newsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    sendErrorPage('Database Error', 'Unable to verify the news article. Please try again.', 500);
}

if (!$news) {
    sendErrorPage('Article Not Found', 'The news article associated with this attachment does not exist.');
}

// ─── Permission Check ─────────────────────────────────────────────────────────

$isAdmin     = ($_SESSION['role'] ?? '') === 'admin';
$inSameDept  = (int)($news['department_id']) === (int)($_SESSION['department_id'] ?? -1);

if (!$isAdmin && !$inSameDept) {
    sendErrorPage(
        'Access Denied',
        'You do not have permission to download files from this article.',
        403
    );
}

// ─── Fetch Attachment Record ──────────────────────────────────────────────────

try {
    $attStmt = $pdo->prepare(
        "SELECT a.id, a.news_id, a.file_name, a.file_path, a.file_type, a.file_size, a.uploaded_at
         FROM attachments a
         WHERE a.id = ? AND a.news_id = ?
         LIMIT 1"
    );
    $attStmt->execute([$attachmentId, $newsId]);
    $attachment = $attStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    sendErrorPage('Database Error', 'Unable to retrieve the attachment record.', 500);
}

if (!$attachment) {
    sendErrorPage(
        'Attachment Not Found',
        'This attachment does not exist or does not belong to the specified article.'
    );
}

// ─── Resolve File on Disk ─────────────────────────────────────────────────────

$filePath = findFile($attachment['file_path']);

if ($filePath === false) {
    sendErrorPage(
        'File Not Found',
        'The file <strong>' . e($attachment['file_name']) . '</strong> could not be located on the server. '
        . 'It may have been moved or deleted.'
    );
}

// ─── Security: Prevent Path Traversal ────────────────────────────────────────

$realPath = realpath($filePath);
if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
    sendErrorPage('Access Denied', 'The file could not be read securely.', 403);
}

// ─── Determine MIME & File Info ───────────────────────────────────────────────

$mimeType  = !empty($attachment['file_type'])
    ? $attachment['file_type']
    : guessMimeType($attachment['file_name']);

$fileSize  = filesize($realPath);
$safeFileName = preg_replace('/[^\w\s.\-]/u', '_', $attachment['file_name']);

// ─── Optional Download Log ────────────────────────────────────────────────────

try {
    $logCheck = $pdo->query("SHOW TABLES LIKE 'attachment_downloads'");
    if ($logCheck->rowCount() > 0) {
        $logStmt = $pdo->prepare(
            "INSERT INTO attachment_downloads (attachment_id, downloaded_by, downloaded_at, ip_address)
             VALUES (?, ?, NOW(), ?)"
        );
        $logStmt->execute([
            $attachmentId,
            $_SESSION['user_id'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }
} catch (PDOException) {
    // Non-critical — log silently and continue
}

// ─── Stream File ──────────────────────────────────────────────────────────────

// Clean any buffered output before streaming
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $safeFileName . '"');
header('Content-Length: ' . $fileSize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: private, no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// Stream in 1MB chunks to handle large files without memory exhaustion
$handle = fopen($realPath, 'rb');
if ($handle === false) {
    sendErrorPage('Stream Error', 'The file could not be opened for reading.', 500);
}

while (!feof($handle) && !connection_aborted()) {
    echo fread($handle, 1048576); // 1MB chunks
    flush();
}

fclose($handle);
exit;