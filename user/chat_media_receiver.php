<?php
/**
 * Chat Media Receiver for MBC News Network
 *
 * Receives media uploads from MBC Chat and inserts them into the
 * news system's `news` table + downloads media into `attachments`.
 *
 * Supports both single-media and multi-media (attachments[]) payloads.
 *
 * Deploy this file to: C:\xampp\htdocs\crud\user\chat_media_receiver.php
 * URL: https://newsnetwork.mbcradio.net/crud/user/chat_media_receiver.php
 *
 * Does NOT require auth.php (no session) — uses API key authentication
 * instead, since this is called server-to-server by the chat system.
 */

header('Content-Type: application/json');

// ---- Configuration ----
$VALID_API_KEY = 'f761c594caa48576cad9e7c8bedc6c818d39c9ab144ce150da120c79dbb7d23d';

// Database — same as save.php uses
$db_host = 'localhost';
$db_name = 'crud_news';
$db_user = 'root';
$db_pass = '';

// Local uploads directory for downloaded media
$uploadsBase = __DIR__ . '/uploads/chat_media';

// ---- Handle GET requests (e.g. get_categories) ----
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $apiKey = $_GET['api_key'] ?? '';

    if (empty($apiKey) || !hash_equals($VALID_API_KEY, $apiKey)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    if ($action === 'get_categories') {
        try {
            $pdo = new PDO(
                "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
                $db_user, $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
            $categories = $stmt->fetchAll();
            echo json_encode(['success' => true, 'categories' => $categories]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit;
}

// Only accept POST for media uploads
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ---- Read Input ----
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// ---- Authenticate via API Key ----
if (empty($data['api_key']) || !hash_equals($VALID_API_KEY, $data['api_key'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// ---- Validate Required Fields ----
if (empty($data['title'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Title is required']);
    exit;
}

// ---- Connect to Database ----
try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    error_log("[ChatMediaReceiver] DB connection failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// ---- Helper: download a file from URL ----
function downloadFile($url, $destPath, $timeout = 60) {
    $ch = curl_init($url);
    $fp = fopen($destPath, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if ($httpCode === 200 && file_exists($destPath) && filesize($destPath) > 0) {
        return true;
    }
    @unlink($destPath);
    return false;
}

// ---- Determine category ----
$categoryId = null;
$categoryName = 'Media';

// Use category_id from chat system if provided
if (!empty($data['category_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, name FROM categories WHERE id = ? LIMIT 1");
        $stmt->execute([$data['category_id']]);
        $cat = $stmt->fetch();
        if ($cat) {
            $categoryId = $cat['id'];
            $categoryName = $cat['name'];
        }
    } catch (PDOException $e) {
        error_log("[ChatMediaReceiver] Category lookup error: " . $e->getMessage());
    }
}

// Fallback: auto-detect from media type if no category_id provided or not found
if (!$categoryId) {
    $hasVideo = false;
    if (!empty($data['attachments']) && is_array($data['attachments'])) {
        foreach ($data['attachments'] as $att) {
            if (($att['media_type'] ?? 'image') === 'video') { $hasVideo = true; break; }
        }
    } else {
        $hasVideo = ($data['media_type'] ?? 'image') === 'video';
    }
    $categoryName = $hasVideo ? 'Video' : 'Media';

    try {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$categoryName]);
        $cat = $stmt->fetch();

        if ($cat) {
            $categoryId = $cat['id'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$categoryName]);
            $categoryId = $pdo->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log("[ChatMediaReceiver] Category error: " . $e->getMessage());
    }
}

// ---- Extract fields ----
$title = $data['title'];
$description = $data['description'] ?? '';
$senderName = $data['sender_name'] ?? 'MBC Chat User';
$publishedAt = $data['published_at'] ?? date('Y-m-d H:i:s');

// ---- Build content body ----
$content = $description ?: $title;
$content .= "\n\n---\n";
$content .= "Author: " . $senderName . "\n";
$content .= "Source: MBC Chat\n";
$content .= "Published: " . date('F d, Y', strtotime($publishedAt)) . "\n";

// ---- Get default department_id ----
$defaultDeptId = 1;
try {
    $stmt = $pdo->query("SELECT id FROM departments ORDER BY id ASC LIMIT 1");
    $dept = $stmt->fetch();
    if ($dept) $defaultDeptId = $dept['id'];
} catch (PDOException $e) {
    error_log("[ChatMediaReceiver] Dept lookup error: " . $e->getMessage());
}

// ---- Find or create user for the chat sender ----
$senderPasswordHash = $data['sender_password_hash'] ?? '';
$authorUserId = null;
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$senderName]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        $authorUserId = $existingUser['id'];
        // Update password hash to stay in sync with chat system
        if ($senderPasswordHash) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                ->execute([$senderPasswordHash, $authorUserId]);
        }
    } else {
        // Use same password hash from chat system so user can log in with same credentials
        $pwHash = $senderPasswordHash ?: password_hash('chat_user_' . time(), PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, department_id, role, is_active)
            VALUES (?, ?, ?, 'user', 1)
        ");
        $stmt->execute([
            $senderName,
            $pwHash,
            $defaultDeptId
        ]);
        $authorUserId = $pdo->lastInsertId();
        error_log("[ChatMediaReceiver] Created news user '{$senderName}' with ID={$authorUserId} (password synced from chat)");
    }
} catch (PDOException $e) {
    error_log("[ChatMediaReceiver] User lookup/create error: " . $e->getMessage());
    $stmt = $pdo->query("SELECT id FROM users ORDER BY id ASC LIMIT 1");
    $usr = $stmt->fetch();
    $authorUserId = $usr['id'] ?? 1;
}

// ---- Build media list (supports both single-media and multi-media payloads) ----
$mediaItems = [];

if (!empty($data['attachments']) && is_array($data['attachments'])) {
    // Multi-media payload
    foreach ($data['attachments'] as $att) {
        $mediaItems[] = [
            'media_url'  => $att['media_url'] ?? '',
            'thumb_url'  => $att['thumb_url'] ?? '',
            'media_type' => $att['media_type'] ?? 'image',
            'mime_type'  => $att['mime_type'] ?? 'image/jpeg'
        ];
    }
} else {
    // Legacy single-media payload
    $mediaUrl = $data['media_url'] ?? '';
    if ($mediaUrl) {
        $mediaItems[] = [
            'media_url'  => $mediaUrl,
            'thumb_url'  => $data['thumb_url'] ?? '',
            'media_type' => $data['media_type'] ?? 'image',
            'mime_type'  => $data['mime_type'] ?? 'image/jpeg'
        ];
    }
}

// ---- Download all media files ----
$yearMonth = date('Y/m');
$uploadDir = $uploadsBase . '/' . $yearMonth;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$downloadedFiles = [];
$firstThumbnailPath = null;

foreach ($mediaItems as $item) {
    $mediaUrl = $item['media_url'];
    $thumbUrl = $item['thumb_url'];
    $mimeType = $item['mime_type'];

    if (!$mediaUrl) continue;

    // Download media file
    $urlPath = parse_url($mediaUrl, PHP_URL_PATH);
    $ext = pathinfo($urlPath, PATHINFO_EXTENSION) ?: 'jpg';
    $localFileName = 'chat_' . uniqid() . '.' . $ext;
    $fullLocalPath = $uploadDir . '/' . $localFileName;

    if (!downloadFile($mediaUrl, $fullLocalPath)) {
        error_log("[ChatMediaReceiver] Media download failed: {$mediaUrl}");
        continue;
    }

    $localFilePath = 'uploads/chat_media/' . $yearMonth . '/' . $localFileName;
    $localFileSize = filesize($fullLocalPath);

    // Download thumbnail
    $thumbnailPath = null;
    if ($thumbUrl) {
        $thumbFileName = 'thumb_' . uniqid() . '.jpg';
        $fullThumbPath = $uploadDir . '/' . $thumbFileName;
        if (downloadFile($thumbUrl, $fullThumbPath, 30)) {
            $thumbnailPath = 'uploads/chat_media/' . $yearMonth . '/' . $thumbFileName;
        }
    }

    if (!$firstThumbnailPath && $thumbnailPath) {
        $firstThumbnailPath = $thumbnailPath;
    }

    $downloadedFiles[] = [
        'file_name'  => $localFileName,
        'file_path'  => $localFilePath,
        'file_size'  => $localFileSize,
        'mime_type'  => $mimeType,
        'thumb_path' => $thumbnailPath
    ];
}

// ---- Insert into news table ----
try {
    $stmt = $pdo->prepare("
        INSERT INTO news (
            title,
            content,
            category_id,
            department_id,
            created_by,
            source_type,
            thumbnail,
            is_pushed,
            created_at,
            updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
    ");

    $stmt->execute([
        $title,
        $content,
        $categoryId,
        $defaultDeptId,
        $authorUserId,
        'mbc_chat',
        $firstThumbnailPath
    ]);

    $newsId = $pdo->lastInsertId();

    // ---- Insert all attachments ----
    $attachmentIds = [];
    foreach ($downloadedFiles as $file) {
        $stmt = $pdo->prepare("
            INSERT INTO attachments (news_id, file_name, file_path, file_size, file_type, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $newsId,
            $file['file_name'],
            $file['file_path'],
            $file['file_size'],
            $file['mime_type'],
            $authorUserId
        ]);
        $attachmentIds[] = $pdo->lastInsertId();
    }

    error_log("[ChatMediaReceiver] News ID={$newsId}, attachments=" . count($attachmentIds) . ", files=" . implode(',', array_column($downloadedFiles, 'file_path')));

    echo json_encode([
        'success' => true,
        'message' => 'News article created with ' . count($attachmentIds) . ' attachment(s)',
        'data' => [
            'article_id'     => $newsId,
            'attachment_ids'  => $attachmentIds,
            'attachment_count' => count($attachmentIds),
            'title'          => $title,
            'category'       => $categoryName,
            'thumbnail'      => $firstThumbnailPath,
            'source_type'    => 'mbc_chat'
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    error_log("[ChatMediaReceiver] Insert failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save: ' . $e->getMessage()]);
}
