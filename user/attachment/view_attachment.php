<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

if (!isset($_GET['id']) || empty($_GET['id'])) { die('News ID is required'); }

$newsId = intval($_GET['id']);

$newsStmt = $pdo->prepare("SELECT n.*, d.name as dept_name, u.username as author_name 
                           FROM news n 
                           JOIN departments d ON n.department_id = d.id 
                           LEFT JOIN users u ON n.created_by = u.id
                           WHERE n.id = ?");
$newsStmt->execute([$newsId]);
$news = $newsStmt->fetch();
if (!$news) { die('News article not found'); }

if ($_SESSION['role'] !== 'admin' && $news['department_id'] != $_SESSION['department_id']) { die('Permission denied'); }

try {
    $checkTable = $pdo->query("SHOW TABLES LIKE 'attachments'");
    if ($checkTable->rowCount() === 0) { die('Attachments feature is not available'); }
} catch (PDOException $e) { die('Database error: ' . $e->getMessage()); }

try {
    $attachStmt = $pdo->prepare("SELECT a.*, u.username as uploader_name 
                                 FROM attachments a
                                 LEFT JOIN users u ON a.uploaded_by = u.id
                                 WHERE a.news_id = ? 
                                 ORDER BY a.uploaded_at DESC");
    $attachStmt->execute([$newsId]);
    $attachments = $attachStmt->fetchAll();
} catch (PDOException $e) { die('Error fetching attachments: ' . $e->getMessage()); }

function e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function getFileIcon($f) {
    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $m = ['pdf'=>'picture_as_pdf','doc'=>'description','docx'=>'description','xls'=>'table_chart','xlsx'=>'table_chart',
          'ppt'=>'slideshow','pptx'=>'slideshow','jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image',
          'webp'=>'image','svg'=>'image','bmp'=>'image','zip'=>'folder_zip','rar'=>'folder_zip','7z'=>'folder_zip',
          'txt'=>'text_snippet','csv'=>'table_chart','mp4'=>'videocam','avi'=>'videocam','mov'=>'videocam',
          'wmv'=>'videocam','flv'=>'videocam','mkv'=>'videocam','webm'=>'videocam','mpeg'=>'videocam','mpg'=>'videocam',
          '3gp'=>'videocam','ogv'=>'videocam','mp3'=>'audiotrack','wav'=>'audiotrack','ogg'=>'audiotrack',
          'flac'=>'audiotrack','aac'=>'audiotrack','m4a'=>'audiotrack','wma'=>'audiotrack','aiff'=>'audiotrack',
          'ape'=>'audiotrack','opus'=>'audiotrack'];
    return $m[$e] ?? 'insert_drive_file';
}

function getFileTokens($f) {
    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    $m = ['pdf'=>['#FFF1F2','#DC2626'],'doc'=>['#EFF6FF','#2563EB'],'docx'=>['#EFF6FF','#2563EB'],
          'xls'=>['#ECFDF5','#059669'],'xlsx'=>['#ECFDF5','#059669'],'csv'=>['#ECFDF5','#059669'],
          'ppt'=>['#FFF7ED','#EA580C'],'pptx'=>['#FFF7ED','#EA580C'],
          'jpg'=>['#F5F3FF','#7C3AED'],'jpeg'=>['#F5F3FF','#7C3AED'],'png'=>['#F5F3FF','#7C3AED'],
          'gif'=>['#F5F3FF','#7C3AED'],'webp'=>['#F5F3FF','#7C3AED'],'svg'=>['#F5F3FF','#7C3AED'],
          'bmp'=>['#F5F3FF','#7C3AED'],'mp4'=>['#FDF2F8','#9D174D'],'webm'=>['#FDF2F8','#9D174D'],
          'mov'=>['#FDF2F8','#9D174D'],'avi'=>['#FDF2F8','#9D174D'],'mkv'=>['#FDF2F8','#9D174D'],
          'mp3'=>['#EEF2FF','#4338CA'],'wav'=>['#EEF2FF','#4338CA'],'flac'=>['#EEF2FF','#4338CA'],
          'aac'=>['#EEF2FF','#4338CA'],'m4a'=>['#EEF2FF','#4338CA'],'ogg'=>['#EEF2FF','#4338CA'],
          'zip'=>['#FFFBEB','#D97706'],'rar'=>['#FFFBEB','#D97706'],'7z'=>['#FFFBEB','#D97706']];
    return $m[$e] ?? ['#F3F1FA','#8E89A8'];
}

function canViewInBrowser($f) {
    $e = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    return in_array($e, ['pdf','jpg','jpeg','png','gif','webp','svg','bmp','txt','mp4','webm','ogg','ogv','mp3','wav','m4a','flac','aac']);
}
function isImage($f)  { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','webp','svg','bmp']); }
function isVideo($f)  { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['mp4','webm','ogg','ogv','avi','mov','wmv','flv','mkv','mpeg','mpg','3gp']); }
function isAudio($f)  { return in_array(strtolower(pathinfo($f,PATHINFO_EXTENSION)),['mp3','wav','ogg','flac','aac','m4a','wma','aiff','ape','opus']); }

function formatFileSize($b) {
    if ($b >= 1073741824) return number_format($b/1073741824,2).' GB';
    if ($b >= 1048576)    return number_format($b/1048576,2).' MB';
    if ($b >= 1024)       return number_format($b/1024,2).' KB';
    return $b.' B';
}

function findFile($storedPath) {
    if (file_exists($storedPath) && is_file($storedPath)) return $storedPath;
    $bn = basename($storedPath);
    $paths = [
        __DIR__.'/../uploads/'.$bn, __DIR__.'/../uploads/attachments/'.$bn,
        __DIR__.'/../../uploads/'.$bn, __DIR__.'/../../uploads/attachments/'.$bn,
        $_SERVER['DOCUMENT_ROOT'].'/uploads/'.$bn, $_SERVER['DOCUMENT_ROOT'].'/uploads/attachments/'.$bn,
        $_SERVER['DOCUMENT_ROOT'].'/news/uploads/'.$bn, $_SERVER['DOCUMENT_ROOT'].'/news/uploads/attachments/'.$bn,
        $_SERVER['DOCUMENT_ROOT'].'/news/admin/uploads/'.$bn, $_SERVER['DOCUMENT_ROOT'].'/news/admin/uploads/attachments/'.$bn,
        dirname(__DIR__).'/'.$storedPath, dirname(__DIR__,2).'/'.$storedPath,
        $_SERVER['DOCUMENT_ROOT'].'/'.$storedPath,
        '/var/www/html/news/uploads/'.$bn, '/var/www/html/news/uploads/attachments/'.$bn,
        '/var/www/html/uploads/'.$bn, '/var/www/html/uploads/attachments/'.$bn,
    ];
    foreach ($paths as $p) { if (file_exists($p) && is_file($p)) return $p; }
    return false;
}

// Serve single attachment
if (isset($_GET['attachment_id'])) {
    $aid = intval($_GET['attachment_id']);
    $s = $pdo->prepare("SELECT * FROM attachments WHERE id = ? AND news_id = ?");
    $s->execute([$aid, $newsId]);
    $att = $s->fetch();
    if (!$att) { die('Attachment not found'); }
    $fp = findFile($att['file_path']);
    if (!$fp) {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>File Not Found</title></head>
        <body style="display:flex;align-items:center;justify-content:center;min-height:100vh;font-family:sans-serif;background:#F3F1FA">
        <div style="background:white;border-radius:16px;padding:40px;max-width:420px;text-align:center;box-shadow:0 12px 40px rgba(60,20,120,.16)">
        <div style="font-size:48px;margin-bottom:12px">⚠️</div>
        <h2 style="font-size:20px;margin-bottom:8px;color:#13111A">File Not Found</h2>
        <p style="font-size:13px;color:#4A4560;margin-bottom:20px">'.htmlspecialchars($att['file_name']).' could not be located on the server.</p>
        <button onclick="window.close()" style="background:#7C3AED;color:white;border:none;padding:10px 24px;border-radius:9px;cursor:pointer;font-size:13px">Close</button>
        </div></body></html>';
        exit;
    }
    $mime = $att['file_type'] ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: inline; filename="'.basename($att['file_name']).'"');
    header('Content-Length: '.filesize($fp));
    header('Cache-Control: public, max-age=3600');
    if (ob_get_level()) ob_end_clean();
    readfile($fp);
    exit;
}

// Empty state
if (empty($attachments)) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>No Attachments</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,400&family=Sora:wght@400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
    <style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:\'Sora\',sans-serif;background:#F3F1FA;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:white;border-radius:16px;padding:48px 40px;max-width:400px;text-align:center;box-shadow:0 12px 40px rgba(60,20,120,.14);border:1px solid #E2DDEF}
    .ico{width:72px;height:72px;border-radius:50%;background:#F5F3FF;border:2px solid #C4B5FD;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
    .ico .material-icons-round{font-size:32px!important;color:#7C3AED}
    h2{font-family:\'Playfair Display\',serif;font-size:22px;color:#13111A;margin-bottom:8px}
    p{font-size:13px;color:#4A4560;line-height:1.6;margin-bottom:24px}
    button{background:#7C3AED;color:white;border:none;padding:10px 28px;border-radius:9px;font-family:\'Sora\',sans-serif;font-size:13px;font-weight:600;cursor:pointer;transition:background .15s}
    button:hover{background:#6D28D9}</style></head>
    <body><div class="card">
    <div class="ico"><span class="material-icons-round">folder_open</span></div>
    <h2>No Attachments</h2>
    <p>This article doesn\'t have any attachments yet.</p>
    <button onclick="window.close()">Close Window</button>
    </div></body></html>';
    exit;
}

// Totals & grouping
$totalSize = 0;
foreach ($attachments as $att) {
    $ap = findFile($att['file_path']);
    if ($ap) $totalSize += filesize($ap);
}
$grouped = ['images'=>[],'videos'=>[],'audio'=>[],'documents'=>[],'archives'=>[],'other'=>[]];
foreach ($attachments as $att) {
    $f = $att['file_name'];
    if (!findFile($att['file_path'])) continue;
    if (isImage($f)) $grouped['images'][] = $att;
    elseif (isVideo($f)) $grouped['videos'][] = $att;
    elseif (isAudio($f)) $grouped['audio'][] = $att;
    else {
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        if (in_array($ext,['pdf','doc','docx','txt','xls','xlsx','ppt','pptx','csv'])) $grouped['documents'][] = $att;
        elseif (in_array($ext,['zip','rar','7z','tar','gz'])) $grouped['archives'][] = $att;
        else $grouped['other'][] = $att;
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Attachments — <?= e($news['title']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
/* ── TOKENS ── */
:root {
    --purple:#7C3AED;--purple-md:#6D28D9;--purple-light:#EDE9FE;--purple-pale:#F5F3FF;
    --purple-glow:rgba(124,58,237,.18);
    --ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;
    --canvas:#F3F1FA;--surface:#FFFFFF;--surface-2:#EEEAF8;
    --border:#E2DDEF;--border-md:#C9C2E0;
    --r:14px;--r-sm:9px;
    --sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);
    --sh-md:0 4px 18px rgba(60,20,120,.11);
    --sh-lg:0 12px 40px rgba(60,20,120,.16);
}
[data-theme="dark"] {
    --ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;
    --canvas:#0E0C18;--surface:#17142A;--surface-2:#1E1A30;
    --border:#2A2540;--border-md:#362F50;
    --purple-light:#1E1440;--purple-pale:#150F2E;
    --sh:0 1px 4px rgba(0,0,0,.4);--sh-md:0 4px 18px rgba(0,0,0,.5);--sh-lg:0 12px 40px rgba(0,0,0,.6);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;scroll-behavior:smooth}
body{font-family:'Sora',sans-serif;background:var(--canvas);color:var(--ink);min-height:100vh;transition:background .2s,color .2s}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--purple)}

/* ── TOPBAR ── */
.topbar {
    position: sticky; top: 0; z-index: 40;
    background: var(--surface); border-bottom: 1px solid var(--border);
    box-shadow: var(--sh);
    padding: 0 28px; height: 60px;
    display: flex; align-items: center; gap: 14px;
}
.tb-logo { width: 34px; height: 34px; border-radius: 9px; background: var(--purple-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.tb-logo .material-icons-round { font-size: 17px !important; color: var(--purple); }
.tb-title { font-family: 'Playfair Display', serif; font-size: 16px; color: var(--ink); }
.tb-sub { font-size: 10px; color: var(--ink-faint); font-family: 'Fira Code', monospace; margin-top: 1px; }
.tb-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }
.btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--r-sm); border: none; cursor: pointer; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 600; text-decoration: none; transition: all .15s; white-space: nowrap; }
.btn .material-icons-round { font-size: 15px !important; }
.btn-purple { background: var(--purple); color: white; }
.btn-purple:hover { background: var(--purple-md); box-shadow: 0 4px 14px var(--purple-glow); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--ink-muted); }
.btn-outline:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.btn-icon { width: 36px; height: 36px; padding: 0; justify-content: center; }

/* ── HERO ── */
.hero {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 24px 28px; margin: 22px 24px 0;
    position: relative; overflow: hidden;
}
.hero::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--purple), #A78BFA, #60A5FA);
    border-radius: var(--r) var(--r) 0 0;
}
.hero-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.hero-icon { width: 52px; height: 52px; border-radius: 13px; background: var(--purple-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.hero-icon .material-icons-round { font-size: 26px !important; color: var(--purple); }
.hero-eyebrow { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: .14em; color: var(--ink-faint); font-family: 'Fira Code', monospace; margin-bottom: 4px; }
.hero-title { font-family: 'Playfair Display', serif; font-size: 22px; color: var(--ink); margin-bottom: 3px; }
.hero-pills { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
.hero-pill { display: inline-flex; align-items: center; gap: 5px; padding: 5px 11px; border-radius: 99px; font-size: 11px; font-weight: 500; border: 1px solid var(--border); background: var(--canvas); color: var(--ink-muted); font-family: 'Fira Code', monospace; }
.hero-pill .material-icons-round { font-size: 13px !important; }
.hero-pill.stat { border-color: #C4B5FD; background: var(--purple-light); color: var(--purple-md); font-weight: 700; }

/* ── TABS ── */
.tab-bar-wrap { margin: 18px 24px 0; background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); box-shadow: var(--sh); overflow: hidden; }
.tab-row { display: flex; overflow-x: auto; scrollbar-width: none; padding: 0 4px; border-bottom: 1px solid var(--border); }
.tab-row::-webkit-scrollbar { display: none; }
.tab-btn { display: inline-flex; align-items: center; gap: 6px; padding: 13px 16px; border: none; background: none; cursor: pointer; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 500; color: var(--ink-faint); white-space: nowrap; position: relative; transition: color .15s; flex-shrink: 0; }
.tab-btn .material-icons-round { font-size: 15px !important; }
.tab-btn:hover { color: var(--ink); }
.tab-btn.active { color: var(--purple); font-weight: 600; }
.tab-btn.active::after { content: ''; position: absolute; bottom: -1px; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--purple), #A78BFA); border-radius: 2px 2px 0 0; }
.tab-cnt { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px; font-size: 9px; font-weight: 700; font-family: 'Fira Code', monospace; background: var(--canvas); color: var(--ink-faint); border: 1px solid var(--border); }
.tab-btn.active .tab-cnt { background: var(--purple-light); color: var(--purple); border-color: #C4B5FD; }
.tab-content-wrap { padding: 20px; }
.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ── ATTACHMENT CARD ── */
.att-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 18px 20px; margin-bottom: 12px;
    transition: all .2s cubic-bezier(.4,0,.2,1);
}
.att-card:last-child { margin-bottom: 0; }
.att-card:hover { border-color: var(--border-md); box-shadow: var(--sh-md); transform: translateY(-1px); }
.att-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.att-file-ico { width: 52px; height: 52px; border-radius: 13px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.att-file-ico .material-icons-round { font-size: 26px !important; }
.att-info { flex: 1; min-width: 0; }
.att-name { font-size: 14px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 6px; }
.att-meta { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.att-badge { display: inline-flex; align-items: center; gap: 3px; padding: 3px 9px; border-radius: 99px; font-size: 10px; font-weight: 600; font-family: 'Fira Code', monospace; border: 1px solid; }
.att-badge .material-icons-round { font-size: 11px !important; }
.badge-gray { background: var(--canvas); color: var(--ink-faint); border-color: var(--border); }
.badge-blue { background: #EFF6FF; color: #1D4ED8; border-color: #BFDBFE; }
.badge-purple { background: var(--purple-light); color: var(--purple-md); border-color: #C4B5FD; }
.badge-red { background: #FFF1F2; color: #9F1239; border-color: #FECDD3; }
.att-uploader { font-size: 11px; color: var(--ink-faint); margin-top: 5px; display: flex; align-items: center; gap: 4px; font-family: 'Fira Code', monospace; }
.att-uploader .material-icons-round { font-size: 12px !important; }
.att-actions { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }

/* File not found state */
.att-missing { display: inline-flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: var(--r-sm); background: #FFF1F2; border: 1px solid #FECDD3; color: #9F1239; font-size: 11px; font-weight: 600; font-family: 'Fira Code', monospace; }
.att-missing .material-icons-round { font-size: 13px !important; }

/* ── MEDIA PREVIEW ── */
.media-preview {
    margin-top: 16px; border-radius: var(--r-sm);
    background: linear-gradient(135deg, #1E1440, #2D1B69);
    border: 1px solid rgba(167,139,250,.3);
    padding: 18px; animation: revealDown .25s ease;
    overflow: hidden;
}
@keyframes revealDown { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
.media-preview-hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.media-preview-label { font-size: 12px; font-weight: 600; color: #C4B5FD; display: flex; align-items: center; gap: 6px; font-family: 'Fira Code', monospace; }
.media-preview-label .material-icons-round { font-size: 15px !important; color: #A78BFA; }
.media-close { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.15); color: #C4B5FD; border-radius: 7px; cursor: pointer; display: flex; align-items: center; padding: 4px; transition: background .15s; }
.media-close:hover { background: rgba(255,255,255,.18); }
.media-close .material-icons-round { font-size: 15px !important; }
video, audio { max-width: 100%; border-radius: var(--r-sm); background: #000; display: block; }
iframe.preview-frame { width: 100%; height: 420px; border: none; border-radius: var(--r-sm); background: white; }

/* ── IMAGE GRID ── */
.image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
.img-tile { position: relative; border-radius: var(--r-sm); overflow: hidden; border: 1px solid var(--border); cursor: pointer; transition: all .2s; aspect-ratio: 1; background: var(--canvas); }
.img-tile:hover { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); transform: scale(1.02); }
.img-tile img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform .2s; }
.img-tile-overlay { position: absolute; inset: 0; background: rgba(19,17,26,0); display: flex; align-items: center; justify-content: center; transition: background .2s; }
.img-tile:hover .img-tile-overlay { background: rgba(124,58,237,.45); }
.img-tile-overlay .material-icons-round { font-size: 28px !important; color: white; opacity: 0; transform: scale(.8); transition: all .2s; }
.img-tile:hover .img-tile-overlay .material-icons-round { opacity: 1; transform: none; }
.img-tile-cap { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(19,17,26,.85)); padding: 18px 10px 8px; opacity: 0; transition: opacity .2s; }
.img-tile:hover .img-tile-cap { opacity: 1; }
.img-tile-cap-text { font-size: 10px; font-weight: 600; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-family: 'Fira Code', monospace; }

/* ── LIGHTBOX ── */
.lightbox { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.94); z-index: 9999; align-items: center; justify-content: center; padding: 24px; backdrop-filter: blur(4px); }
.lightbox.open { display: flex; animation: lbIn .2s ease; }
@keyframes lbIn { from { opacity: 0; } to { opacity: 1; } }
.lightbox-img { max-width: 90vw; max-height: 88vh; object-fit: contain; border-radius: var(--r-sm); box-shadow: 0 32px 80px rgba(0,0,0,.8); }
.lightbox-close { position: absolute; top: 18px; right: 18px; width: 40px; height: 40px; border-radius: 50%; border: 1px solid rgba(255,255,255,.2); background: rgba(0,0,0,.6); color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; }
.lightbox-close:hover { background: rgba(255,255,255,.15); }
.lightbox-close .material-icons-round { font-size: 18px !important; }
.lightbox-caption { position: absolute; bottom: 20px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.7); color: white; padding: 8px 18px; border-radius: 99px; font-size: 11px; font-family: 'Fira Code', monospace; max-width: 480px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; border: 1px solid rgba(255,255,255,.1); backdrop-filter: blur(8px); }

/* ── EMPTY ── */
.empty-card { text-align: center; padding: 60px 24px; }
.empty-ico { width: 64px; height: 64px; border-radius: 50%; background: var(--purple-light); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; }
.empty-ico .material-icons-round { font-size: 28px !important; color: var(--purple); }
.empty-title { font-family: 'Playfair Display', serif; font-size: 18px; margin-bottom: 6px; }
.empty-sub { font-size: 12px; color: var(--ink-faint); }
</style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
    <div class="tb-logo"><span class="material-icons-round">attach_file</span></div>
    <div>
        <div class="tb-title">File Attachments</div>
        <div class="tb-sub"><?= e($news['title']) ?></div>
    </div>
    <div class="tb-right">
        <button onclick="toggleDark()" id="darkBtn" class="btn btn-outline btn-icon" title="Toggle dark mode">
            <span class="material-icons-round">dark_mode</span>
        </button>
        <?php if (count($attachments) > 1): ?>
        <a href="get_attachment.php?id=<?= $newsId ?>" class="btn btn-purple">
            <span class="material-icons-round">archive</span>Download All
        </a>
        <?php endif; ?>
        <button onclick="window.close()" class="btn btn-outline">
            <span class="material-icons-round">close</span>Close
        </button>
    </div>
</div>

<!-- Hero -->
<div class="hero">
    <div class="hero-top">
        <div style="display:flex;align-items:flex-start;gap:14px">
            <div class="hero-icon"><span class="material-icons-round">folder_open</span></div>
            <div>
                <div class="hero-eyebrow">Attachment Viewer</div>
                <div class="hero-title"><?= e($news['title']) ?></div>
            </div>
        </div>
    </div>
    <div class="hero-pills">
        <span class="hero-pill stat">
            <span class="material-icons-round">attach_file</span>
            <?= count($attachments) ?> file<?= count($attachments) !== 1 ? 's' : '' ?>
        </span>
        <span class="hero-pill stat">
            <span class="material-icons-round">storage</span>
            <?= formatFileSize($totalSize) ?>
        </span>
        <span class="hero-pill">
            <span class="material-icons-round">business</span>
            <?= e($news['dept_name']) ?>
        </span>
        <?php if (!empty($news['author_name'])): ?>
        <span class="hero-pill">
            <span class="material-icons-round">person</span>
            <?= e($news['author_name']) ?>
        </span>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs -->
<div class="tab-bar-wrap">
    <div class="tab-row" id="tabRow">
        <button class="tab-btn active" onclick="switchTab('all', this)">
            <span class="material-icons-round">folder</span>
            All Files
            <span class="tab-cnt"><?= count($attachments) ?></span>
        </button>
        <?php
        $tabDefs = [
            'images'    => ['image',        'Images'],
            'videos'    => ['videocam',     'Videos'],
            'audio'     => ['audiotrack',   'Audio'],
            'documents' => ['description',  'Documents'],
            'archives'  => ['folder_zip',   'Archives'],
            'other'     => ['insert_drive_file','Other'],
        ];
        foreach ($tabDefs as $key => [$ico, $lbl]):
            if (empty($grouped[$key])) continue;
        ?>
        <button class="tab-btn" onclick="switchTab('<?= $key ?>', this)">
            <span class="material-icons-round"><?= $ico ?></span>
            <?= $lbl ?>
            <span class="tab-cnt"><?= count($grouped[$key]) ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- Tab panes -->
    <div class="tab-content-wrap">
        <!-- ALL -->
        <div class="tab-pane active" id="pane-all">
            <?php foreach ($attachments as $att):
                $fp = findFile($att['file_path']);
                $exists = $fp !== false;
                $sz = $exists ? filesize($fp) : 0;
                $canView = canViewInBrowser($att['file_name']);
                $isVid = isVideo($att['file_name']);
                $isAud = isAudio($att['file_name']);
                $isImg = isImage($att['file_name']);
                [$cbg, $cfg] = getFileTokens($att['file_name']);
                $ext = strtoupper(pathinfo($att['file_name'], PATHINFO_EXTENSION));
            ?>
            <div class="att-card">
                <div class="att-row">
                    <div class="att-file-ico" style="background:<?= $cbg ?>">
                        <span class="material-icons-round" style="color:<?= $cfg ?>"><?= getFileIcon($att['file_name']) ?></span>
                    </div>
                    <div class="att-info">
                        <div class="att-name" title="<?= e($att['file_name']) ?>"><?= e($att['file_name']) ?></div>
                        <div class="att-meta">
                            <span class="att-badge badge-gray"><?= $exists ? formatFileSize($sz) : 'N/A' ?></span>
                            <span class="att-badge badge-blue"><?= $ext ?></span>
                            <span class="att-badge badge-purple">
                                <span class="material-icons-round">schedule</span>
                                <?= date('M d, Y', strtotime($att['uploaded_at'])) ?>
                            </span>
                        </div>
                        <?php if (!empty($att['uploader_name'])): ?>
                        <div class="att-uploader">
                            <span class="material-icons-round">person</span>
                            <?= e($att['uploader_name']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="att-actions">
                        <?php if ($exists): ?>
                            <?php if ($canView): ?>
                            <button onclick="viewMedia(<?= $att['id'] ?>, '<?= $isVid ? 'video' : ($isAud ? 'audio' : ($isImg ? 'image' : 'other')) ?>')"
                                    class="btn btn-outline" title="<?= $isVid || $isAud ? 'Play' : 'View' ?>">
                                <span class="material-icons-round"><?= $isVid || $isAud ? 'play_circle' : 'visibility' ?></span>
                                <?= $isVid ? 'Play' : ($isAud ? 'Play' : ($isImg ? 'View' : 'Preview')) ?>
                            </button>
                            <?php endif; ?>
                            <a href="download_single_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>"
                               class="btn btn-purple" title="Download">
                                <span class="material-icons-round">download</span>
                            </a>
                        <?php else: ?>
                            <span class="att-missing">
                                <span class="material-icons-round">error_outline</span>Not Found
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Media preview (hidden by default) -->
                <?php if ($exists && $canView): ?>
                <div id="media-preview-<?= $att['id'] ?>" class="media-preview" style="display:none">
                    <div class="media-preview-hd">
                        <div class="media-preview-label">
                            <span class="material-icons-round"><?= $isVid ? 'videocam' : ($isAud ? 'audiotrack' : 'visibility') ?></span>
                            <?= $isVid ? 'Video Player' : ($isAud ? 'Audio Player' : 'Preview') ?>
                            — <?= e($att['file_name']) ?>
                        </div>
                        <button class="media-close" onclick="closeMedia(<?= $att['id'] ?>)">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                    <?php if ($isImg): ?>
                        <img src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>"
                             alt="<?= e($att['file_name']) ?>"
                             style="max-width:100%;border-radius:var(--r-sm);cursor:zoom-in"
                             onclick="openLightbox('view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>', '<?= e($att['file_name']) ?>')"/>
                    <?php elseif ($isVid): ?>
                        <video id="player-<?= $att['id'] ?>" controls>
                            <source src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>" type="<?= e($att['file_type']) ?>">
                            Your browser does not support the video tag.
                        </video>
                    <?php elseif ($isAud): ?>
                        <div style="background:rgba(255,255,255,.07);border-radius:var(--r-sm);padding:18px">
                            <audio id="player-<?= $att['id'] ?>" controls style="width:100%;background:transparent">
                                <source src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>" type="<?= e($att['file_type']) ?>">
                            </audio>
                        </div>
                    <?php else: ?>
                        <iframe class="preview-frame" id="player-<?= $att['id'] ?>"
                                src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>"></iframe>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- IMAGES (grid) -->
        <?php if (!empty($grouped['images'])): ?>
        <div class="tab-pane" id="pane-images">
            <div class="image-grid">
            <?php foreach ($grouped['images'] as $att):
                if (!findFile($att['file_path'])) continue;
            ?>
                <div class="img-tile"
                     onclick="openLightbox('view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>', '<?= e($att['file_name']) ?>')">
                    <img src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>"
                         alt="<?= e($att['file_name']) ?>" loading="lazy"/>
                    <div class="img-tile-overlay">
                        <span class="material-icons-round">zoom_in</span>
                    </div>
                    <div class="img-tile-cap">
                        <div class="img-tile-cap-text"><?= e($att['file_name']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- OTHER TYPE TABS — reuse the list view -->
        <?php foreach (['videos','audio','documents','archives','other'] as $grp):
            if (empty($grouped[$grp])) continue;
        ?>
        <div class="tab-pane" id="pane-<?= $grp ?>">
            <?php foreach ($grouped[$grp] as $att):
                $fp = findFile($att['file_path']);
                $exists = $fp !== false;
                $sz = $exists ? filesize($fp) : 0;
                $canView = canViewInBrowser($att['file_name']);
                $isVid = isVideo($att['file_name']);
                $isAud = isAudio($att['file_name']);
                $isImg = isImage($att['file_name']);
                [$cbg, $cfg] = getFileTokens($att['file_name']);
                $ext = strtoupper(pathinfo($att['file_name'], PATHINFO_EXTENSION));
            ?>
            <div class="att-card">
                <div class="att-row">
                    <div class="att-file-ico" style="background:<?= $cbg ?>">
                        <span class="material-icons-round" style="color:<?= $cfg ?>"><?= getFileIcon($att['file_name']) ?></span>
                    </div>
                    <div class="att-info">
                        <div class="att-name" title="<?= e($att['file_name']) ?>"><?= e($att['file_name']) ?></div>
                        <div class="att-meta">
                            <span class="att-badge badge-gray"><?= $exists ? formatFileSize($sz) : 'N/A' ?></span>
                            <span class="att-badge badge-blue"><?= $ext ?></span>
                            <span class="att-badge badge-purple">
                                <span class="material-icons-round">schedule</span>
                                <?= date('M d, Y', strtotime($att['uploaded_at'])) ?>
                            </span>
                        </div>
                        <?php if (!empty($att['uploader_name'])): ?>
                        <div class="att-uploader"><span class="material-icons-round">person</span><?= e($att['uploader_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="att-actions">
                        <?php if ($exists): ?>
                            <?php if ($canView): ?>
                            <button onclick="viewMedia(<?= $att['id'] ?>, '<?= $isVid ? 'video' : ($isAud ? 'audio' : 'other') ?>')"
                                    class="btn btn-outline">
                                <span class="material-icons-round"><?= $isVid || $isAud ? 'play_circle' : 'visibility' ?></span>
                                <?= $isVid || $isAud ? 'Play' : 'Preview' ?>
                            </button>
                            <?php endif; ?>
                            <a href="download_single_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>"
                               class="btn btn-purple"><span class="material-icons-round">download</span></a>
                        <?php else: ?>
                            <span class="att-missing"><span class="material-icons-round">error_outline</span>Not Found</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($exists && $canView && ($isVid || $isAud)): ?>
                <div id="media-preview-<?= $att['id'] ?>" class="media-preview" style="display:none">
                    <div class="media-preview-hd">
                        <div class="media-preview-label">
                            <span class="material-icons-round"><?= $isVid ? 'videocam' : 'audiotrack' ?></span>
                            <?= $isVid ? 'Video Player' : 'Audio Player' ?> — <?= e($att['file_name']) ?>
                        </div>
                        <button class="media-close" onclick="closeMedia(<?= $att['id'] ?>)">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                    <?php if ($isVid): ?>
                    <video id="player-<?= $att['id'] ?>" controls>
                        <source src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>" type="<?= e($att['file_type']) ?>">
                    </video>
                    <?php else: ?>
                    <div style="background:rgba(255,255,255,.07);border-radius:var(--r-sm);padding:18px">
                        <audio id="player-<?= $att['id'] ?>" controls style="width:100%">
                            <source src="view_attachment.php?id=<?= $newsId ?>&attachment_id=<?= $att['id'] ?>" type="<?= e($att['file_type']) ?>">
                        </audio>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()">
        <span class="material-icons-round">close</span>
    </button>
    <img class="lightbox-img" id="lightboxImg" src="" alt="" onclick="event.stopPropagation()"/>
    <div class="lightbox-caption" id="lightboxCap"></div>
</div>

<script>
'use strict';

/* ── Dark mode ─────────────────────────────── */
function toggleDark() {
    const h = document.documentElement;
    const d = h.dataset.theme === 'dark';
    h.dataset.theme = d ? 'light' : 'dark';
    localStorage.setItem('va-theme', d ? 'light' : 'dark');
    document.getElementById('darkBtn').querySelector('.material-icons-round').textContent = d ? 'dark_mode' : 'light_mode';
}
(function () {
    const t = localStorage.getItem('va-theme') || 'light';
    document.documentElement.dataset.theme = t;
    const b = document.getElementById('darkBtn');
    if (b) b.querySelector('.material-icons-round').textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
})();

/* ── Tab switching ─────────────────────────── */
function switchTab(id, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const pane = document.getElementById('pane-' + id);
    if (pane) pane.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* ── Media previewer ───────────────────────── */
function viewMedia(id, type) {
    const preview = document.getElementById('media-preview-' + id);
    if (!preview) return;

    // Close all others
    document.querySelectorAll('[id^="media-preview-"]').forEach(div => {
        if (div.id !== 'media-preview-' + id && div.style.display !== 'none') {
            div.style.display = 'none';
            const p = div.querySelector('video,audio');
            if (p) p.pause();
        }
    });

    const open = preview.style.display !== 'none';
    if (open) {
        preview.style.display = 'none';
        const p = preview.querySelector('video,audio');
        if (p) p.pause();
    } else {
        preview.style.display = 'block';
        preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (type === 'video' || type === 'audio') {
            const p = preview.querySelector('video,audio');
            if (p) { p.load(); p.play().catch(() => {}); }
        }
    }
}

function closeMedia(id) {
    const preview = document.getElementById('media-preview-' + id);
    if (!preview) return;
    preview.style.display = 'none';
    const p = preview.querySelector('video,audio');
    if (p) { p.pause(); p.currentTime = 0; }
}

/* ── Lightbox ──────────────────────────────── */
function openLightbox(src, caption) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxCap').textContent = caption;
    document.getElementById('lightbox').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('open');
    document.body.style.overflow = '';
}

/* ── Keyboard ──────────────────────────────── */
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeLightbox();
        document.querySelectorAll('[id^="media-preview-"]').forEach(div => {
            if (div.style.display !== 'none') {
                const id = div.id.replace('media-preview-', '');
                closeMedia(id);
            }
        });
    }
});

/* ── Pause on tab hide ─────────────────────── */
document.addEventListener('visibilitychange', () => {
    if (document.hidden) document.querySelectorAll('video,audio').forEach(p => p.pause());
});
</script>
</body>
</html>