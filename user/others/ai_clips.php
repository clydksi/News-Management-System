<?php
/**
 * ai_clips.php — 
 *
 * Folder structure:
 *   CLIPS_BASE_PATH\
 *     {YYYY-MM-DD}\
 *       {Topic / Show Name}\
 *         {Clip N - Title}\
 *           *.mp4  *.jpg  *.txt
 *
 * Network path: \\172.16.103.13\OpusClips\
 */

define('CLIPS_BASE_PATH', 'Z:\\');
define('CLIPS_WEB_BASE',  'stream_clip.php?file=');
define('VIDEO_EXTS',      ['mp4','mov','webm','mkv','avi']);
define('THUMB_EXTS',      ['jpg','jpeg','png','webp']);

// ─── Helpers ──────────────────────────────────────────────────────────────────
function e($v) { return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8'); }

function humanSize(int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function isVideo(string $f): bool {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), VIDEO_EXTS);
}

function isThumb(string $f): bool {
    return in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), THUMB_EXTS);
}

function parseClipTxt(string $path): array {
    $meta = [
        'title' => '', 'source_file' => '', 'content_type' => '',
        'virality_score' => '', 'virality_num' => 0.0,
        'rank' => '', 'start' => '', 'end' => '', 'duration' => '',
        'hook' => '', 'why_viral' => '', 'tags' => [], 'transcript' => '',
    ];
    $raw = @file_get_contents($path);
    if (!$raw) return $meta;

    $lines = explode("\n", str_replace(["\r\n","\r"], "\n", $raw));

    // Key : Value header
    foreach ($lines as $ln) {
        if (preg_match('/^([A-Za-z ]+?)\s*:\s*(.+)$/', $ln, $m)) {
            $k = strtolower(trim(str_replace(' ', '_', $m[1])));
            $v = trim($m[2]);
            switch ($k) {
                case 'title':          $meta['title']          = $v; break;
                case 'source_file':    $meta['source_file']    = $v; break;
                case 'content_type':   $meta['content_type']   = $v; break;
                case 'virality_score':
                    $meta['virality_score'] = $v;
                    if (preg_match('/([\d.]+)\s*\/\s*100/', $v, $vm))
                        $meta['virality_num'] = (float)$vm[1];
                    break;
                case 'rank':     $meta['rank']     = $v; break;
                case 'start':    $meta['start']    = $v; break;
                case 'end':      $meta['end']      = $v; break;
                case 'duration': $meta['duration'] = $v; break;
            }
        }
    }

    // Named sections
    $sections = []; $cur = null; $buf = [];
    foreach ($lines as $ln) {
        $clean = trim($ln);
        if (preg_match('/^[=\-]{10,}$/', $clean)) {
            if ($cur !== null && $buf) $sections[$cur] = implode("\n", $buf);
            $cur = null; $buf = []; continue;
        }
        if (preg_match('/^[A-Z][A-Z\s]{2,}$/', $clean) && strpos($clean, ':') === false) {
            if ($cur !== null && $buf) $sections[$cur] = implode("\n", $buf);
            $cur = strtolower(trim($clean)); $buf = []; continue;
        }
        if ($cur !== null) $buf[] = $ln;
    }
    if ($cur !== null && $buf) $sections[$cur] = implode("\n", $buf);

    if (!empty($sections['hook']))                   $meta['hook']      = trim($sections['hook']);
    if (!empty($sections['why it will go viral']))   $meta['why_viral'] = trim($sections['why it will go viral']);
    if (!empty($sections['transcript']))             $meta['transcript']= trim($sections['transcript']);
    if (!empty($sections['tags'])) {
        preg_match_all('/##?\w+/', $sections['tags'], $tm);
        $meta['tags'] = $tm[0] ?? [];
    }
    return $meta;
}

function scanDateFolder(string $datePath, string $dateKey): array {
    $topics = [];
    foreach (@scandir($datePath) ?: [] as $topicDir) {
        if ($topicDir === '.' || $topicDir === '..') continue;
        $topicPath = $datePath . $topicDir . DIRECTORY_SEPARATOR;
        if (!is_dir($topicPath)) continue;
        $clips = [];
        foreach (@scandir($topicPath) ?: [] as $clipDir) {
            if ($clipDir === '.' || $clipDir === '..') continue;
            $clipPath = $topicPath . $clipDir . DIRECTORY_SEPARATOR;
            if (!is_dir($clipPath)) continue;
            $videoFile = $thumbFile = $txtFile = null;
            foreach (@scandir($clipPath) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                if (!is_file($clipPath . $f)) continue;
                if (!$videoFile && isVideo($f)) $videoFile = $f;
                if (!$thumbFile && isThumb($f)) $thumbFile = $f;
                if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'txt') $txtFile = $f;
            }
            if (!$videoFile) continue;
            $meta     = $txtFile ? parseClipTxt($clipPath . $txtFile) : [];
            $meta     = array_merge(['title'=>'','rank'=>'0','virality_num'=>0,'start'=>'','end'=>'','duration'=>'','hook'=>'','content_type'=>'','source_file'=>'','tags'=>[],'transcript'=>''], $meta);
            $clips[] = [
                'clip_folder' => $clipDir,
                'filename'    => $videoFile,
                'full_path'   => $clipPath . $videoFile,
                'size'        => @filesize($clipPath . $videoFile) ?: 0,
                'mtime'       => @filemtime($clipPath . $videoFile) ?: 0,
                'thumb_rel'   => $thumbFile ? ($dateKey . '/' . $topicDir . '/' . $clipDir . '/' . $thumbFile) : null,
                'thumb_full'  => $thumbFile ? ($clipPath . $thumbFile) : null,
                'txt_rel'     => $txtFile  ? ($dateKey . '/' . $topicDir . '/' . $clipDir . '/' . $txtFile)   : null,
                'txt_full'    => $txtFile  ? ($clipPath . $txtFile) : null,
                'video_rel'   => $dateKey . '/' . $topicDir . '/' . $clipDir . '/' . $videoFile,
                'ext'         => strtolower(pathinfo($videoFile, PATHINFO_EXTENSION)),
                'meta'        => $meta,
            ];
        }
        if (!$clips) continue;
        usort($clips, function($a, $b) {
            $ra = (int)ltrim($a['meta']['rank'], '#');
            $rb = (int)ltrim($b['meta']['rank'], '#');
            return ($ra ?: 999) <=> ($rb ?: 999);
        });
        $topics[$topicDir] = $clips;
    }
    return $topics;
}

function viralityColor(float $s): array {
    if ($s >= 8) return ['#10B981','#ECFDF5','#A7F3D0'];
    if ($s >= 5) return ['#F59E0B','#FFF7ED','#FDE68A'];
    return             ['#EF4444','#FFF1F2','#FECDD3'];
}

// ─── Main ─────────────────────────────────────────────────────────────────────
$error = null;
$dateFolders = [];
$topics = [];
$selectedDate = $_GET['date'] ?? null;

if (!is_dir(CLIPS_BASE_PATH)) {
    $error = 'Cannot access the MBC Clips network share at <code>' . e(CLIPS_BASE_PATH) . '</code>.';
} else {
    foreach (@scandir(CLIPS_BASE_PATH) ?: [] as $e2) {
        if ($e2 !== '.' && $e2 !== '..' && is_dir(CLIPS_BASE_PATH . $e2) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $e2))
            $dateFolders[] = $e2;
    }
    rsort($dateFolders);
    if (!$selectedDate && $dateFolders) $selectedDate = $dateFolders[0];
    if ($selectedDate && !in_array($selectedDate, $dateFolders, true)) $selectedDate = $dateFolders[0] ?? null;
    if ($selectedDate) $topics = scanDateFolder(CLIPS_BASE_PATH . $selectedDate . DIRECTORY_SEPARATOR, $selectedDate);
}

$allClips   = [];
foreach ($topics as $tc) foreach ($tc as $c) $allClips[] = $c;
$totalClips = count($allClips);
$totalSize  = array_sum(array_column($allClips, 'size'));
$prettyDate = '';
if ($selectedDate) {
    $dt = DateTime::createFromFormat('Y-m-d', $selectedDate);
    $prettyDate = $dt ? $dt->format('F j, Y') : $selectedDate;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>AI Clips — MBC Clips Browser</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
:root{--purple:#7C3AED;--purple-md:#6D28D9;--purple-light:#EDE9FE;--purple-pale:#F5F3FF;--purple-glow:rgba(124,58,237,.18);--ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;--canvas:#F3F1FA;--surface:#FFFFFF;--surface-3:#F8F7FD;--trans-bg:#F5F3FF;--trans-bg-hd:#EDE9FE;--trans-border:#C4B5FD;--trans-label:#6D28D9;--border:#E2DDEF;--border-md:#C9C2E0;--r:14px;--r-sm:9px;--r-xs:5px;--sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);--sh-md:0 4px 18px rgba(60,20,120,.11),0 2px 6px rgba(60,20,120,.06);--sh-lg:0 14px 44px rgba(60,20,120,.16),0 4px 10px rgba(60,20,120,.07);--sh-xl:0 24px 64px rgba(60,20,120,.22),0 6px 14px rgba(60,20,120,.08);--success:#059669;--danger:#DC2626;--tiktok:#010101;--tiktok-red:#FE2C55;--tiktok-cyan:#25F4EE}
[data-theme="dark"]{--ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;--canvas:#0E0C18;--surface:#17142A;--surface-3:#13102A;--trans-bg:#0F0A22;--trans-bg-hd:#130E28;--trans-border:#241A4A;--trans-label:#A78BFA;--border:#2A2540;--border-md:#362F50;--purple-light:#1E1440;--purple-pale:#150F2E;--sh:0 1px 4px rgba(0,0,0,.35);--sh-md:0 4px 18px rgba(0,0,0,.45);--sh-lg:0 14px 44px rgba(0,0,0,.55);--sh-xl:0 24px 64px rgba(0,0,0,.7)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{min-height:100%;scroll-behavior:smooth}
body{font-family:'Sora',sans-serif;background:var(--canvas);color:var(--ink);min-height:100vh;transition:background .25s,color .25s}
::-webkit-scrollbar{width:5px;height:5px}::-webkit-scrollbar-track{background:transparent}::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}::-webkit-scrollbar-thumb:hover{background:var(--purple)}
#readingProgress{position:fixed;top:0;left:0;height:3px;z-index:200;background:linear-gradient(90deg,var(--purple),#A78BFA,#60A5FA);width:0%;transition:width .1s linear;box-shadow:0 0 8px var(--purple-glow)}
.page-shell{max-width:1400px;margin:0 auto;padding:0 20px 80px}
/* TOPBAR */
.topbar{position:sticky;top:0;z-index:60;padding:14px 20px;margin:0 -20px;display:flex;align-items:center;justify-content:space-between;gap:12px;transition:background .2s,box-shadow .2s}
.topbar.scrolled{background:rgba(243,241,250,.9);backdrop-filter:blur(14px);box-shadow:0 1px 0 var(--border),0 2px 12px rgba(60,20,120,.07)}
[data-theme="dark"] .topbar.scrolled{background:rgba(14,12,24,.9)}
.back-link{display:inline-flex;align-items:center;gap:7px;font-size:13px;font-weight:500;color:var(--ink-faint);text-decoration:none;transition:color .15s;padding:6px 10px;border-radius:var(--r-sm)}
.back-link:hover{color:var(--purple);background:var(--purple-pale)}
.back-link .material-icons-round{font-size:17px!important}
.topbar-right{display:flex;align-items:center;gap:6px}
.tb-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 13px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--surface);color:var(--ink-muted);text-decoration:none;transition:all .15s}
.tb-btn .material-icons-round{font-size:15px!important}
.tb-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.tb-btn-icon{width:34px;height:34px;padding:0;justify-content:center}
/* HERO */
.hero{padding:36px 0 0;margin-bottom:28px}
.hero-eyebrow{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:99px;background:var(--purple-light);border:1px solid var(--trans-border);font-size:10px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--trans-label);margin-bottom:14px;font-family:'Fira Code',monospace}
.hero-eyebrow .material-icons-round{font-size:13px!important}
.hero-title{font-family:'Playfair Display',serif;font-size:clamp(26px,4vw,38px);font-weight:700;color:var(--ink);line-height:1.15;margin-bottom:10px;letter-spacing:-.02em}
.hero-title em{font-style:italic;color:var(--purple)}
.hero-sub{font-size:14px;color:var(--ink-faint);max-width:560px;line-height:1.6}
.hero-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:18px}
.meta-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:99px;border:1px solid var(--border);background:var(--surface);font-size:11px;color:var(--ink-muted);font-family:'Fira Code',monospace;box-shadow:var(--sh)}
.meta-pill .material-icons-round{font-size:13px!important;color:var(--purple)}
.meta-pill strong{color:var(--ink);font-weight:700}
/* LAYOUT */
.main-layout{display:grid;grid-template-columns:220px 1fr;gap:24px;align-items:start}
@media(max-width:900px){.main-layout{grid-template-columns:1fr}}
/* SIDEBAR */
.date-sidebar{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;position:sticky;top:72px}
.sidebar-hd{padding:12px 16px;border-bottom:1px solid var(--border);background:var(--trans-bg);display:flex;align-items:center;gap:8px}
.sidebar-hd-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--trans-label);font-family:'Fira Code',monospace}
.sidebar-hd .material-icons-round{font-size:14px!important;color:var(--purple)}
.date-list{padding:6px 0;max-height:60vh;overflow-y:auto}
.date-item{display:flex;align-items:center;gap:8px;padding:9px 14px;text-decoration:none;font-family:'Fira Code',monospace;font-size:12px;font-weight:500;color:var(--ink-muted);transition:background .13s,color .13s;border-left:2px solid transparent}
.date-item:hover{background:var(--purple-pale);color:var(--purple)}
.date-item.active{background:var(--purple-light);color:var(--trans-label);border-left-color:var(--purple);font-weight:700}
.date-item .material-icons-round{font-size:14px!important;flex-shrink:0}
.date-badge{margin-left:auto;padding:2px 7px;border-radius:99px;font-size:10px;font-weight:700;background:var(--canvas);border:1px solid var(--border);color:var(--ink-faint)}
.date-item.active .date-badge{background:var(--purple);border-color:var(--purple);color:white}
/* TOOLBAR */
.clips-toolbar{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-sm);box-shadow:var(--sh);padding:10px 14px;margin-bottom:18px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.toolbar-search{flex:1;min-width:200px;position:relative}
.toolbar-search input{width:100%;padding:7px 12px 7px 34px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);font-family:'Sora',sans-serif;font-size:12px;color:var(--ink);outline:none;transition:border-color .15s}
.toolbar-search input:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}
.toolbar-search input::placeholder{color:var(--ink-faint)}
.toolbar-search .si{position:absolute;left:9px;top:50%;transform:translateY(-50%);font-size:15px!important;color:var(--ink-faint);pointer-events:none}
.sort-select{border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);padding:6px 24px 6px 10px;font-family:'Sora',sans-serif;font-size:11px;color:var(--ink);cursor:pointer;outline:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 7px center}
.sort-select:focus{border-color:var(--purple)}
.toolbar-label{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;white-space:nowrap}
/* TOPIC GROUP */
.topic-group{margin-bottom:32px}
.topic-header{display:flex;align-items:center;gap:12px;margin-bottom:16px;padding:12px 18px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh)}
.topic-icon{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,var(--purple),var(--purple-md));display:flex;align-items:center;justify-content:center;flex-shrink:0}
.topic-icon .material-icons-round{font-size:20px!important;color:white}
.topic-info{flex:1;min-width:0}
.topic-name{font-family:'Playfair Display',serif;font-size:16px;font-weight:700;color:var(--ink)}
.topic-meta{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.topic-count{padding:4px 12px;border-radius:99px;background:var(--purple-light);border:1px solid var(--trans-border);color:var(--trans-label);font-size:11px;font-weight:700;font-family:'Fira Code',monospace;white-space:nowrap;flex-shrink:0}
/* CLIPS GRID */
.clips-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:18px}
/* CLIP CARD */
.clip-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh-md);overflow:hidden;transition:box-shadow .2s,border-color .2s,transform .2s;opacity:0;transform:translateY(12px);display:flex;flex-direction:column}
.clip-card.visible{opacity:1;transform:none;transition:opacity .4s ease,transform .4s ease,box-shadow .2s,border-color .2s}
.clip-card:hover{box-shadow:var(--sh-xl);border-color:var(--trans-border);transform:translateY(-3px)}
/* Rank strip */
.clip-rank-strip{background:linear-gradient(135deg,var(--purple) 0%,var(--purple-md) 100%);padding:7px 14px;display:flex;align-items:center;gap:10px}
.rank-block{display:flex;flex-direction:column}
.rank-label-sm{font-size:9px;color:rgba(255,255,255,.65);font-family:'Fira Code',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.1em}
.rank-num{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:white;line-height:1}
.virality-block{margin-left:auto;display:flex;align-items:center;gap:8px}
.virality-text{font-family:'Fira Code',monospace;font-size:13px;font-weight:700;color:white;text-align:right}
.virality-label-sm{font-size:9px;color:rgba(255,255,255,.65);font-family:'Fira Code',monospace;font-weight:700;text-transform:uppercase;letter-spacing:.1em}
.virality-track{width:78px;height:5px;border-radius:99px;background:rgba(255,255,255,.25)}
.virality-fill{height:100%;border-radius:99px;background:white}
/* Thumbnail */
.clip-thumb{position:relative;background:#0F0A20;aspect-ratio:16/9;overflow:hidden;cursor:pointer}
.clip-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .35s}
.clip-card:hover .clip-thumb img{transform:scale(1.04)}
.clip-thumb-placeholder{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:8px;color:rgba(167,139,250,.4)}
.clip-thumb-placeholder .material-icons-round{font-size:44px!important}
.clip-thumb-placeholder span{font-size:11px;font-family:'Fira Code',monospace}
.clip-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.75) 0%,transparent 55%);display:flex;align-items:center;justify-content:center;opacity:0;transition:opacity .2s}
.clip-card:hover .clip-overlay{opacity:1}
.play-btn{width:54px;height:54px;border-radius:50%;background:rgba(124,58,237,.9);border:2px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;transform:scale(.82);transition:transform .2s;box-shadow:0 4px 20px rgba(0,0,0,.5)}
.clip-card:hover .play-btn{transform:scale(1)}
.play-btn .material-icons-round{font-size:28px!important;color:white}
.dur-badge{position:absolute;bottom:8px;right:10px;background:rgba(0,0,0,.72);color:white;font-size:10px;font-family:'Fira Code',monospace;font-weight:700;padding:3px 8px;border-radius:5px}
.type-badge{position:absolute;top:8px;left:8px;padding:3px 9px;border-radius:5px;font-size:9px;font-weight:700;font-family:'Fira Code',monospace;text-transform:uppercase;background:var(--purple);color:white}
/* Body */
.clip-body{padding:14px 16px;flex:1;display:flex;flex-direction:column;gap:10px}
.clip-title{font-family:'Playfair Display',serif;font-size:14px;line-height:1.45;color:var(--ink);font-weight:600}
/* Hook */
.hook-box{background:var(--trans-bg);border:1px solid var(--trans-border);border-radius:var(--r-sm);padding:10px 12px}
.hook-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--trans-label);font-family:'Fira Code',monospace;margin-bottom:5px;display:flex;align-items:center;gap:4px}
.hook-label .material-icons-round{font-size:11px!important}
.hook-text{font-size:12px;color:var(--ink-muted);line-height:1.6}
/* Meta chips */
.clip-meta-row{display:flex;flex-wrap:wrap;gap:5px}
.chip{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:99px;font-size:10px;font-family:'Fira Code',monospace;font-weight:600;border:1px solid var(--border);background:var(--canvas);color:var(--ink-faint)}
.chip .material-icons-round{font-size:11px!important}
.chip.hl{background:var(--purple-light);border-color:var(--trans-border);color:var(--trans-label)}
/* Tags */
.clip-tags{display:flex;flex-wrap:wrap;gap:5px}
.ctag{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;font-family:'Fira Code',monospace;background:var(--canvas);border:1px solid var(--border);color:var(--ink-faint);cursor:pointer;transition:all .13s}
.ctag:hover{background:var(--purple-light);border-color:var(--trans-border);color:var(--trans-label)}
/* Transcript */
.transcript-btn{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:600;color:var(--ink-faint);cursor:pointer;padding:8px 0 0;border:none;background:none;font-family:'Sora',sans-serif;transition:color .15s;border-top:1px solid var(--border);margin-top:2px;width:100%;text-align:left}
.transcript-btn:hover{color:var(--purple)}
.transcript-btn .material-icons-round{font-size:14px!important;transition:transform .3s}
.transcript-btn.open .material-icons-round{transform:rotate(180deg)}
.transcript-box{display:none;margin-top:8px;padding:12px;background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm)}
.transcript-box.open{display:block}
.transcript-text{font-size:11px;color:var(--ink-faint);line-height:1.85;white-space:pre-wrap;word-break:break-word}
/* Actions */
.clip-actions{padding:10px 16px;border-top:1px solid var(--border);display:flex;gap:7px;background:var(--surface-3);flex-shrink:0;flex-wrap:wrap}
.clip-act{flex:1;display:inline-flex;align-items:center;justify-content:center;gap:4px;padding:7px 10px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:11px;font-weight:600;cursor:pointer;border:none;text-decoration:none;transition:all .15s;white-space:nowrap}
.clip-act .material-icons-round{font-size:13px!important}
.clip-act-play{background:var(--purple);color:white}
.clip-act-play:hover{background:var(--purple-md);box-shadow:0 3px 12px var(--purple-glow)}
.clip-act-dl{background:var(--canvas);border:1px solid var(--border);color:var(--ink-muted)}
.clip-act-dl:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.clip-act-import{background:var(--purple-light);border:1px solid var(--trans-border);color:var(--purple-md)}
.clip-act-import:hover{background:var(--purple);color:white;border-color:var(--purple)}
.clip-act-import.imported{background:#ECFDF5;border-color:#A7F3D0;color:#065F46;pointer-events:none}

/* ── TikTok Button ── */
.clip-act-tiktok{
    background:var(--tiktok);
    color:white;
    border:1px solid #333;
    position:relative;
    overflow:hidden;
}
.clip-act-tiktok::before{
    content:'';
    position:absolute;
    inset:0;
    background:linear-gradient(135deg,var(--tiktok-cyan) 0%,var(--tiktok-red) 100%);
    opacity:0;
    transition:opacity .2s;
}
.clip-act-tiktok:hover::before{opacity:1}
.clip-act-tiktok:hover{box-shadow:0 3px 14px rgba(254,44,85,.4);transform:translateY(-1px);border-color:var(--tiktok-red)}
.clip-act-tiktok span{position:relative;z-index:1}
.clip-act-tiktok .tiktok-icon{position:relative;z-index:1;width:13px;height:13px;flex-shrink:0}
.clip-act-tiktok.sending{opacity:.7;pointer-events:none}
.clip-act-tiktok.sent{background:#ECFDF5;border-color:#A7F3D0;color:#065F46;pointer-events:none}
.clip-act-tiktok.sent::before{display:none}

/* TikTok confirm modal */
.tiktok-backdrop{display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
.tiktok-backdrop.open{display:flex;animation:fadeIn .2s ease}
.tiktok-modal{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh-xl);width:100%;max-width:460px;overflow:hidden}
.tiktok-modal-hd{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:12px;background:#010101}
.tiktok-modal-hd-icon{width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,#25F4EE,#FE2C55);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.tiktok-modal-hd h3{font-family:'Playfair Display',serif;font-size:16px;color:white;flex:1}
.tiktok-modal-close{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.5);padding:4px;border-radius:var(--r-xs);transition:color .15s}
.tiktok-modal-close:hover{color:white}
.tiktok-modal-close .material-icons-round{font-size:17px!important}
.tiktok-modal-body{padding:18px 20px;display:flex;flex-direction:column;gap:12px}
.tiktok-preview-row{display:flex;gap:12px;align-items:flex-start}
.tiktok-thumb-wrap{border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--border);flex-shrink:0;width:90px;height:51px;background:var(--canvas);display:flex;align-items:center;justify-content:center}
.tiktok-thumb-wrap img{width:100%;height:100%;object-fit:cover;display:block}
.tiktok-preview-info{flex:1;min-width:0}
.tiktok-clip-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:4px}
.tiktok-clip-title{font-family:'Playfair Display',serif;font-size:13px;font-weight:600;color:var(--ink);line-height:1.4}
.tiktok-payload-info{background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm);padding:10px 12px}
.tiktok-payload-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-bottom:7px}
.tiktok-payload-row{display:flex;gap:6px;align-items:center;margin-bottom:4px;font-size:11px;font-family:'Fira Code',monospace;color:var(--ink-muted)}
.tiktok-payload-row:last-child{margin-bottom:0}
.tiktok-payload-row .tpkey{color:var(--trans-label);font-weight:700;min-width:80px;flex-shrink:0}
.tiktok-payload-row .tpval{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--ink)}
.tiktok-webhook-notice{display:flex;align-items:center;gap:7px;padding:8px 11px;border-radius:var(--r-sm);background:rgba(37,244,238,.08);border:1px solid rgba(37,244,238,.25);font-size:11px;color:#1a9e98;font-family:'Sora',sans-serif}
.tiktok-webhook-notice .material-icons-round{font-size:13px!important;color:#25F4EE;flex-shrink:0}
.tiktok-webhook-notice code{background:rgba(37,244,238,.12);padding:1px 5px;border-radius:3px;font-family:'Fira Code',monospace;font-size:10px}
[data-theme="dark"] .tiktok-webhook-notice{color:#25F4EE;background:rgba(37,244,238,.06);border-color:rgba(37,244,238,.2)}
.tiktok-modal-ft{padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:8px;background:var(--surface-3)}
.tiktok-cancel{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);color:var(--ink-muted);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s}
.tiktok-cancel:hover{border-color:var(--border-strong);color:var(--ink)}
.tiktok-send{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:var(--r-sm);background:linear-gradient(135deg,#FE2C55,#c21a3c);color:white;font-family:'Sora',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;box-shadow:0 3px 14px rgba(254,44,85,.4);transition:all .15s}
.tiktok-send:hover{background:linear-gradient(135deg,#ff3d63,#FE2C55);transform:translateY(-1px)}
.tiktok-send:disabled{opacity:.5;cursor:not-allowed;transform:none}
.tiktok-send .material-icons-round{font-size:15px!important}

/* STATE */
.state-box{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);padding:60px 24px;text-align:center;box-shadow:var(--sh-md)}
.state-icon{width:72px;height:72px;border-radius:50%;background:var(--purple-light);border:2px solid var(--trans-border);display:flex;align-items:center;justify-content:center;margin:0 auto 18px;animation:floatIdle 3s ease-in-out infinite}
@keyframes floatIdle{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.state-icon .material-icons-round{font-size:34px!important;color:var(--purple)}
.state-icon.err{background:#FFF1F2;border-color:#FECDD3}
.state-icon.err .material-icons-round{color:var(--danger)}
.state-box h3{font-family:'Playfair Display',serif;font-size:20px;margin-bottom:8px}
.state-box p{font-size:13px;color:var(--ink-faint);max-width:380px;margin:0 auto;line-height:1.65}
/* PLAYER */
.player-backdrop{display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.92);backdrop-filter:blur(10px);align-items:center;justify-content:center;padding:20px;flex-direction:column;gap:14px}
.player-backdrop.open{display:flex;animation:fadeIn .2s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.player-modal{width:100%;max-width:1040px;background:#0F0A20;border:1px solid rgba(124,58,237,.3);border-radius:var(--r);overflow:hidden;box-shadow:0 30px 80px rgba(0,0,0,.8)}
.player-topbar{padding:11px 16px;background:rgba(0,0,0,.5);display:flex;align-items:center;gap:10px;border-bottom:1px solid rgba(124,58,237,.2)}
.player-close{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);display:flex;align-items:center;justify-content:center;cursor:pointer;color:rgba(255,255,255,.8);transition:background .15s;flex-shrink:0}
.player-close:hover{background:rgba(220,38,38,.6)}
.player-close .material-icons-round{font-size:16px!important}
.player-rank-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:9px;font-weight:700;font-family:'Fira Code',monospace;background:var(--purple);color:white;margin-bottom:3px;text-transform:uppercase}
.player-clip-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.9);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-family:'Sora',sans-serif}
video#mainPlayer{width:100%;display:block;max-height:65vh;background:#000}
.player-meta-bar{padding:11px 16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:rgba(0,0,0,.4);border-top:1px solid rgba(124,58,237,.15)}
.pmeta-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:99px;background:rgba(124,58,237,.15);border:1px solid rgba(124,58,237,.3);font-size:11px;color:rgba(200,180,255,.85);font-family:'Fira Code',monospace}
.pmeta-pill .material-icons-round{font-size:12px!important}
.pdl{display:inline-flex;align-items:center;gap:5px;padding:7px 16px;border-radius:var(--r-sm);background:var(--purple);color:white;text-decoration:none;font-family:'Sora',sans-serif;font-size:12px;font-weight:700;box-shadow:0 3px 12px var(--purple-glow);transition:background .15s;margin-left:auto;flex-shrink:0}
.pdl:hover{background:var(--purple-md)}
.pdl .material-icons-round{font-size:15px!important}
/* IMPORT MODAL */
.import-backdrop{display:none;position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
.import-backdrop.open{display:flex;animation:fadeIn .2s ease}
.import-modal{background:var(--surface);border:1px solid var(--border);border-radius:var(--r);box-shadow:var(--sh-xl);width:100%;max-width:560px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden}
.import-modal-hd{padding:16px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;flex-shrink:0}
.import-modal-hd .hd-icon{width:34px;height:34px;border-radius:9px;background:var(--purple-light);border:1px solid var(--trans-border);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.import-modal-hd .hd-icon .material-icons-round{font-size:17px!important;color:var(--purple)}
.import-modal-hd h3{font-family:'Playfair Display',serif;font-size:16px;flex:1}
.import-modal-close{background:none;border:none;cursor:pointer;color:var(--ink-faint);padding:4px;border-radius:var(--r-xs);transition:color .15s}
.import-modal-close:hover{color:#DC2626}
.import-modal-close .material-icons-round{font-size:17px!important}
.import-modal-body{padding:18px 20px;overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:14px}
.im-field label{display:block;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--ink-faint);margin-bottom:5px;font-family:'Fira Code',monospace}
.im-display{padding:9px 12px;border:1px solid var(--border);border-radius:var(--r-sm);background:var(--canvas);font-family:'Sora',sans-serif;font-size:13px;color:var(--ink);line-height:1.6;word-break:break-word}
.im-display-scroll{max-height:180px;overflow-y:auto;white-space:pre-wrap;font-size:12px;font-family:'Fira Code',monospace;color:var(--ink-muted)}
.im-thumb-row{display:flex;align-items:center;gap:12px}
.im-thumb-wrap{border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--border);flex-shrink:0}
.im-thumb-img{display:block;width:120px;height:68px;object-fit:cover}
.im-thumb-status{font-size:12px;font-family:'Sora',sans-serif;color:var(--ink-muted)}
.im-thumb-status.im-thumb-ok{color:#059669}
.im-thumb-status.im-thumb-err{color:#DC2626}
.im-info{display:flex;align-items:center;gap:7px;padding:9px 12px;border-radius:var(--r-sm);background:var(--purple-light);border:1px solid var(--trans-border);font-size:11px;color:var(--trans-label);font-family:'Sora',sans-serif}
.im-info .material-icons-round{font-size:14px!important;color:var(--purple);flex-shrink:0}
.im-info code{background:rgba(124,58,237,.15);padding:1px 5px;border-radius:3px;font-family:'Fira Code',monospace}
.import-modal-ft{padding:12px 20px;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:flex-end;gap:8px;background:var(--surface-3);flex-shrink:0}
.im-cancel{display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:var(--r-sm);border:1px solid var(--border);background:var(--surface);color:var(--ink-muted);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s}
.im-cancel:hover{border-color:var(--border-strong);color:var(--ink)}
.im-submit{display:inline-flex;align-items:center;gap:6px;padding:8px 18px;border-radius:var(--r-sm);background:var(--purple);color:white;font-family:'Sora',sans-serif;font-size:13px;font-weight:700;border:none;cursor:pointer;box-shadow:0 3px 12px var(--purple-glow);transition:background .15s,transform .15s}
.im-submit:hover{background:var(--purple-md);transform:translateY(-1px)}
.im-submit:disabled{opacity:.5;cursor:not-allowed;transform:none}
.im-submit .material-icons-round{font-size:15px!important}
/* TOAST */
.toast-stack{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{display:flex;align-items:center;gap:9px;padding:10px 14px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;box-shadow:var(--sh-lg);pointer-events:all;animation:toastIn .22s ease;border:1px solid;max-width:300px}
.toast.success{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.toast.error{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.toast.info{background:var(--purple-light);color:var(--trans-label);border-color:var(--trans-border)}
.toast .material-icons-round{font-size:16px!important;flex-shrink:0}
@keyframes toastIn{from{transform:translateX(10px);opacity:0}to{transform:none;opacity:1}}
</style>
</head>
<body>
<div id="readingProgress" aria-hidden="true"></div>
<div class="page-shell">

<!-- TOPBAR -->
<nav class="topbar" id="topbar">
    <a href="../user_dashboard.php" class="back-link">
        <span class="material-icons-round">arrow_back</span>Back to Dashboard
    </a>
    <div class="topbar-right">
        <button onclick="toggleDark()" id="darkBtn" class="tb-btn tb-btn-icon" title="Toggle dark mode (D)">
            <span class="material-icons-round">dark_mode</span>
        </button>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-eyebrow">
        <span class="material-icons-round">smart_display</span>
        MBC AI Clip Generator
    </div>
    <h1 class="hero-title"><em>AI Clips</em> Library</h1>
    <p class="hero-sub">Browse AI video clips from MBC Clips, organised by date and show. Each clip includes virality score, hook, tags, and transcript.</p>
    <div class="hero-meta">
        <span class="meta-pill">
            <span class="material-icons-round">storage</span>
            <code style="font-size:10px;font-family:'Fira Code',monospace">\\172.16.103.13\OpusClips</code>
        </span>
        <?php if (!$error): ?>
        <span class="meta-pill"><span class="material-icons-round">folder</span><strong><?= count($dateFolders) ?></strong>&nbsp;date<?= count($dateFolders) !== 1 ? 's' : '' ?></span>
        <?php if ($selectedDate): ?>
        <span class="meta-pill"><span class="material-icons-round">video_library</span><strong><?= $totalClips ?></strong>&nbsp;clip<?= $totalClips !== 1 ? 's' : '' ?></span>
        <span class="meta-pill"><span class="material-icons-round">sd_storage</span><strong><?= humanSize($totalSize) ?></strong></span>
        <?php endif; endif; ?>
    </div>
</div>

<?php if ($error): ?>
<div class="state-box"><div class="state-icon err"><span class="material-icons-round">cloud_off</span></div><h3>Share Not Accessible</h3><p><?= $error ?></p></div>
<?php elseif (empty($dateFolders)): ?>
<div class="state-box"><div class="state-icon"><span class="material-icons-round">video_library</span></div><h3>No Clips Found</h3><p>The MBC Clips share is accessible but contains no dated folders yet.</p></div>
<?php else: ?>

<div class="main-layout">

    <!-- DATE SIDEBAR -->
    <aside class="date-sidebar">
        <div class="sidebar-hd">
            <span class="material-icons-round">calendar_month</span>
            <span class="sidebar-hd-label">Recording Dates</span>
        </div>
        <div class="date-list">
        <?php foreach ($dateFolders as $df):
            $dfPath   = CLIPS_BASE_PATH . $df . DIRECTORY_SEPARATOR;
            $dfCount  = 0;
            foreach (@scandir($dfPath) ?: [] as $t) {
                if ($t==='.'||$t==='..') continue;
                $tp = $dfPath . $t . DIRECTORY_SEPARATOR;
                if (!is_dir($tp)) continue;
                foreach (@scandir($tp) ?: [] as $cd) {
                    if ($cd==='.'||$cd==='..') continue;
                    $cp = $tp . $cd . DIRECTORY_SEPARATOR;
                    if (!is_dir($cp)) continue;
                    foreach (@scandir($cp) ?: [] as $ff) if (isVideo($ff)) { $dfCount++; break; }
                }
            }
            $dfDt  = DateTime::createFromFormat('Y-m-d', $df);
            $dfLbl = $dfDt ? $dfDt->format('d M Y') : $df;
        ?>
        <a href="?date=<?= urlencode($df) ?>" class="date-item <?= $df===$selectedDate?'active':'' ?>">
            <span class="material-icons-round"><?= $df===date('Y-m-d')?'today':'event' ?></span>
            <span><?= e($dfLbl) ?></span>
            <span class="date-badge"><?= $dfCount ?></span>
        </a>
        <?php endforeach; ?>
        </div>
    </aside>

    <!-- CONTENT AREA -->
    <div class="content-area">

        <div class="clips-toolbar">
            <div class="toolbar-search">
                <span class="material-icons-round si">search</span>
                <input type="text" id="clipSearch" placeholder="Search by title, hook, or tag… ( / )" oninput="filterClips(this.value)"/>
            </div>
            <span class="toolbar-label">Sort</span>
            <select class="sort-select" onchange="sortClips(this.value)">
                <option value="rank">By Rank</option>
                <option value="virality">By Virality</option>
                <option value="name">By Name</option>
            </select>
        </div>

        <?php if (empty($topics)): ?>
        <div class="state-box"><div class="state-icon"><span class="material-icons-round">video_off</span></div><h3>No Clips for This Date</h3><p>No video folders were found under <strong><?= e($prettyDate) ?></strong>.</p></div>
        <?php else: ?>

        <?php
        $globalIdx = 0;
        foreach ($topics as $topicName => $topicClips):
            $topicSize = array_sum(array_column($topicClips, 'size'));
        ?>
        <div class="topic-group" data-topic="<?= e(strtolower($topicName)) ?>">
            <div class="topic-header">
                <div class="topic-icon"><span class="material-icons-round">smart_display</span></div>
                <div class="topic-info">
                    <div class="topic-name"><?= e($topicName) ?></div>
                    <div class="topic-meta"><?= count($topicClips) ?> clips &bull; <?= humanSize($topicSize) ?> &bull; <?= e($prettyDate) ?></div>
                </div>
                <span class="topic-count"><?= count($topicClips) ?> clips</span>
            </div>

            <div class="clips-grid">
            <?php foreach ($topicClips as $clip):
                $meta    = $clip['meta'];
                $title   = !empty($meta['title']) ? $meta['title'] : $clip['clip_folder'];
                $rank    = ltrim($meta['rank'] ?? '0', '#');
                $rankInt = is_numeric($rank) ? (int)$rank : 0;
                $vNum    = (float)($meta['virality_num'] ?? 0);
                [$vCol, $vBg, $vBord] = viralityColor($vNum);
                $duration = '';
                if (!empty($meta['duration']) && preg_match('/(\d{1,2}:\d{2})/', $meta['duration'], $dm)) $duration = $dm[1];
                $thumbSrc = $clip['thumb_rel'] ? CLIPS_WEB_BASE . rawurlencode($clip['thumb_rel']) : null;
                $videoSrc = CLIPS_WEB_BASE . rawurlencode($clip['video_rel']);
                $startStr = !empty($meta['start']) && preg_match('/(\d{1,2}:\d{2})/', $meta['start'], $sm) ? $sm[1] : ($meta['start'] ?? '');
                $endStr   = !empty($meta['end'])   && preg_match('/(\d{1,2}:\d{2})/', $meta['end'],   $em) ? $em[1] : ($meta['end']   ?? '');
                // Build searchable text for JS filter
                $searchText = strtolower($title . ' ' . ($meta['hook'] ?? '') . ' ' . implode(' ', $meta['tags'] ?? []));
            ?>
            <div class="clip-card"
                 data-idx="<?= $globalIdx ?>"
                 data-name="<?= e($searchText) ?>"
                 data-rank="<?= $rankInt ?>"
                 data-virality="<?= $vNum ?>"
                 data-title="<?= e($title) ?>"
                 data-transcript="<?= e($meta['transcript'] ?? '') ?>"
                 style="transition-delay:<?= min(($globalIdx % 12) * 55, 500) ?>ms">

                <!-- Rank + Virality -->
                <div class="clip-rank-strip">
                    <div class="rank-block">
                        <span class="rank-label-sm">Rank</span>
                        <span class="rank-num">#<?= e($rank ?: '—') ?></span>
                    </div>
                    <div class="virality-block">
                        <div>
                            <div class="virality-label-sm" style="text-align:right">Virality</div>
                            <div class="virality-text"><?= $vNum > 0 ? number_format($vNum,1) : '—' ?> / 100</div>
                        </div>
                        <?php if ($vNum > 0): ?>
                        <div class="virality-track">
                            <div class="virality-fill" style="width:<?= min(100,$vNum) ?>%;background:white"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Thumbnail -->
                <div class="clip-thumb" onclick="openPlayer(<?= $globalIdx ?>)">
                    <?php if ($thumbSrc): ?>
                    <img src="<?= e($thumbSrc) ?>" loading="lazy" alt="<?= e($title) ?>"/>
                    <?php else: ?>
                    <div class="clip-thumb-placeholder">
                        <span class="material-icons-round">smart_display</span>
                        <span><?= strtoupper(e($clip['ext'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="clip-overlay">
                        <div class="play-btn"><span class="material-icons-round">play_arrow</span></div>
                    </div>
                    <?php if ($duration): ?><span class="dur-badge"><?= e($duration) ?></span><?php endif; ?>
                    <?php if (!empty($meta['content_type'])): ?><span class="type-badge"><?= e($meta['content_type']) ?></span><?php endif; ?>
                </div>

                <!-- Body -->
                <div class="clip-body">
                    <div class="clip-title"><?= e($title) ?></div>

                    <?php if (!empty($meta['hook'])): ?>
                    <div class="hook-box">
                        <div class="hook-label"><span class="material-icons-round">bolt</span>Hook</div>
                        <div class="hook-text"><?= e($meta['hook']) ?></div>
                    </div>
                    <?php endif; ?>

                    <div class="clip-meta-row">
                        <?php if ($startStr && $endStr): ?>
                        <span class="chip hl"><span class="material-icons-round">timer</span><?= e($startStr . ' → ' . $endStr) ?></span>
                        <?php endif; ?>
                        <?php if ($duration): ?>
                        <span class="chip"><span class="material-icons-round">hourglass_empty</span><?= e($duration) ?></span>
                        <?php endif; ?>
                        <span class="chip"><span class="material-icons-round">sd_storage</span><?= humanSize($clip['size']) ?></span>
                        <?php if (!empty($meta['source_file'])): ?>
                        <span class="chip"><span class="material-icons-round">movie</span><?= e($meta['source_file']) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($meta['tags'])): ?>
                    <div class="clip-tags">
                        <?php foreach ($meta['tags'] as $tag): ?>
                        <span class="ctag" onclick="setSearch('<?= e(ltrim($tag,'#')) ?>')"><?= e($tag) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($meta['transcript'])): ?>
                    <button class="transcript-btn" onclick="toggleTranscript(this)">
                        <span class="material-icons-round">expand_more</span>
                        <span>View Transcript</span>
                    </button>
                    <div class="transcript-box">
                        <div class="transcript-text"><?= e($meta['transcript']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Actions -->
                <div class="clip-actions">
                    <button class="clip-act clip-act-play" onclick="openPlayer(<?= $globalIdx ?>)">
                        <span class="material-icons-round">play_arrow</span>Play
                    </button>
                    <a class="clip-act clip-act-dl"
                       href="<?= e($videoSrc) ?>"
                       download="<?= e($clip['filename']) ?>">
                        <span class="material-icons-round">download</span>Save
                    </a>
                    <button class="clip-act clip-act-import"
                            onclick="openImportModal(this)"
                            data-title="<?= e($title) ?>"
                            data-transcript="<?= e($meta['transcript'] ?? '') ?>"
                            data-txt="<?= e($clip['txt_rel'] ?? '') ?>"
                            data-thumb="<?= e($clip['thumb_rel'] ?? '') ?>">
                        <span class="material-icons-round">upload_file</span>Import
                    </button>
                    <!-- ── TikTok Button ── -->
                    <button class="clip-act clip-act-tiktok"
                            onclick="openTikTokModal(this)"
                            data-title="<?= e($title) ?>"
                            data-hook="<?= e($meta['hook'] ?? '') ?>"
                            data-transcript="<?= e($meta['transcript'] ?? '') ?>"
                            data-tags="<?= e(implode(' ', $meta['tags'] ?? [])) ?>"
                            data-virality="<?= e($meta['virality_score'] ?? '') ?>"
                            data-duration="<?= e($duration) ?>"
                            data-source="<?= e($meta['source_file'] ?? '') ?>"
                            data-video="<?= e($videoSrc) ?>"
                            data-video-path="<?= e($clip['video_rel']) ?>"
                            data-thumb="<?= e($thumbSrc ?? '') ?>"
                            data-date="<?= e($selectedDate ?? '') ?>"
                            data-topic="<?= e($topicName) ?>">
                        <!-- TikTok logo SVG -->
                        <svg class="tiktok-icon" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.77 1.52V6.72a4.85 4.85 0 01-1-.03z"/>
                        </svg>
                        <span>TikTok</span>
                    </button>
                </div>
            </div>
            <?php $globalIdx++; endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

</div><!-- .page-shell -->

<!-- VIDEO PLAYER MODAL -->
<div class="player-backdrop" id="playerBackdrop" onclick="if(event.target===this)closePlayer()">
    <div class="player-modal" onclick="event.stopPropagation()">
        <div class="player-topbar">
            <button class="player-close" onclick="closePlayer()"><span class="material-icons-round">close</span></button>
            <div style="flex:1;min-width:0">
                <div class="player-rank-badge" id="playerRank">Rank #—</div>
                <div class="player-clip-title" id="playerTitle">—</div>
            </div>
        </div>
        <video id="mainPlayer" controls preload="metadata"></video>
        <div class="player-meta-bar">
            <span class="pmeta-pill"><span class="material-icons-round">hourglass_empty</span><span id="pDur">—</span></span>
            <span class="pmeta-pill"><span class="material-icons-round">sd_storage</span><span id="pSize">—</span></span>
            <span class="pmeta-pill"><span class="material-icons-round">insert_drive_file</span><span id="pFile">—</span></span>
            <a class="pdl" id="pDl" href="#" download><span class="material-icons-round">download</span>Download</a>
        </div>
    </div>
</div>

<!-- ══ IMPORT MODAL ══ -->
<div class="import-backdrop" id="importBackdrop" onclick="if(event.target===this)closeImportModal()">
    <div class="import-modal" onclick="event.stopPropagation()">
        <div class="import-modal-hd">
            <div class="hd-icon"><span class="material-icons-round">upload_file</span></div>
            <h3>Import Clip to News</h3>
            <button onclick="closeImportModal()" class="import-modal-close">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="import-modal-body">
            <input type="hidden" id="imTxtPath">
            <input type="hidden" id="imThumbPath">
            <input type="hidden" id="imThumbWebPath">
            <input type="hidden" id="imTitle">
            <input type="hidden" id="imTranscript">

            <!-- Thumbnail preview -->
            <div class="im-field">
                <label>Thumbnail</label>
                <div class="im-thumb-row">
                    <div id="imThumbWrap" class="im-thumb-wrap" style="display:none">
                        <img id="imThumbPreview" class="im-thumb-img" src="" alt="thumbnail"/>
                    </div>
                    <span id="imThumbStatus" class="im-thumb-status">—</span>
                </div>
            </div>

            <div class="im-field">
                <label>Title</label>
                <div class="im-display" id="imTitleDisplay">—</div>
            </div>
            <div class="im-field">
                <label>Content <span style="font-weight:400;text-transform:none;letter-spacing:0">(.txt file)</span></label>
                <div class="im-display im-display-scroll" id="imTranscriptDisplay">—</div>
            </div>
            <div class="im-info">
                <span class="material-icons-round">info</span>
                Full <code>.txt</code> file saved as content · <code>.jpg</code> copied to uploads on click
            </div>
        </div>
        <div class="import-modal-ft">
            <button onclick="closeImportModal()" class="im-cancel">Cancel</button>
            <button onclick="submitClipImport()" id="imSubmitBtn" class="im-submit">
                <span class="material-icons-round">upload</span>
                <span id="imSubmitLabel">Import Article</span>
            </button>
        </div>
    </div>
</div>

<!-- ══ TIKTOK CONFIRM MODAL ══ -->
<div class="tiktok-backdrop" id="tiktokBackdrop" onclick="if(event.target===this)closeTikTokModal()">
    <div class="tiktok-modal" onclick="event.stopPropagation()">
        <div class="tiktok-modal-hd">
            <div class="tiktok-modal-hd-icon">
                <svg viewBox="0 0 24 24" fill="white" width="18" height="18" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.77 1.52V6.72a4.85 4.85 0 01-1-.03z"/>
                </svg>
            </div>
            <h3>Send to TikTok via Zapier</h3>
            <button onclick="closeTikTokModal()" class="tiktok-modal-close">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="tiktok-modal-body">
            <!-- Preview -->
            <div class="tiktok-preview-row">
                <div class="tiktok-thumb-wrap">
                    <img id="ttThumb" src="" alt="" style="display:none"/>
                    <span class="material-icons-round" id="ttThumbIcon" style="font-size:22px!important;color:var(--border-md)">smart_display</span>
                </div>
                <div class="tiktok-preview-info">
                    <div class="tiktok-clip-label">Clip to send</div>
                    <div class="tiktok-clip-title" id="ttTitle">—</div>
                </div>
            </div>
            <!-- Payload preview -->
            <div class="tiktok-payload-info">
                <div class="tiktok-payload-label">Webhook Payload Preview</div>
                <div class="tiktok-payload-row"><span class="tpkey">title</span><span class="tpval" id="ttPTitle">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">hook</span><span class="tpval" id="ttPHook">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">tags</span><span class="tpval" id="ttPTags">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">virality</span><span class="tpval" id="ttPVirality">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">duration</span><span class="tpval" id="ttPDuration">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">source</span><span class="tpval" id="ttPSource">—</span></div>
                <div class="tiktok-payload-row">
                    <span class="tpkey">video_url</span>
                    <span class="tpval" id="ttPVideo" style="color:var(--ink-faint);font-style:italic">⏳ Will be copied to public URL on send…</span>
                </div>
                <div class="tiktok-payload-row"><span class="tpkey">date</span><span class="tpval" id="ttPDate">—</span></div>
                <div class="tiktok-payload-row"><span class="tpkey">topic</span><span class="tpval" id="ttPTopic">—</span></div>
            </div>
            <div class="tiktok-webhook-notice">
                <span class="material-icons-round">webhook</span>
                Sends to Zapier webhook → TikTok automation. <code id="ttWebhookHost">—</code>
            </div>
        </div>
        <div class="tiktok-modal-ft">
            <button onclick="closeTikTokModal()" class="tiktok-cancel">Cancel</button>
            <button onclick="submitTikTok()" id="ttSendBtn" class="tiktok-send">
                <svg viewBox="0 0 24 24" fill="currentColor" width="15" height="15" xmlns="http://www.w3.org/2000/svg">
                    <path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.77 1.52V6.72a4.85 4.85 0 01-1-.03z"/>
                </svg>
                <span id="ttSendLabel">Send to TikTok</span>
            </button>
        </div>
    </div>
</div>

<div class="toast-stack" id="toastStack"></div>

<script>
'use strict';

// ─── ⚙️  ZAPIER WEBHOOK CONFIG ────────────────────────────────────────────────
// Replace with your actual Zapier webhook URL
const ZAPIER_WEBHOOK_URL = 'https://hooks.zapier.com/hooks/catch/25253563/us1t4ya/';
// ──────────────────────────────────────────────────────────────────────────────

// Clip data for JS player
const CLIPS = <?= json_encode(array_map(function($c) {
    $m   = $c['meta'];
    $dur = '';
    if (!empty($m['duration']) && preg_match('/(\d{1,2}:\d{2})/', $m['duration'], $dm)) $dur = $dm[1];
    $sz  = function(int $b): string {
        if ($b >= 1073741824) return round($b/1073741824,1).' GB';
        if ($b >= 1048576)    return round($b/1048576,1).' MB';
        if ($b >= 1024)       return round($b/1024,1).' KB';
        return $b.' B';
    };
    return [
        'filename' => $c['filename'],
        'title'    => !empty($m['title']) ? $m['title'] : $c['clip_folder'],
        'rank'     => ltrim($m['rank'] ?? '—', '#'),
        'duration' => $dur,
        'size'     => $sz($c['size']),
        'src'      => CLIPS_WEB_BASE . rawurlencode($c['video_rel']),
    ];
}, $allClips), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?>;

// Dark mode
function toggleDark(){const h=document.documentElement,d=h.dataset.theme==='dark';h.dataset.theme=d?'light':'dark';localStorage.setItem('theme',d?'light':'dark');document.getElementById('darkBtn').querySelector('.material-icons-round').textContent=d?'dark_mode':'light_mode'}
(function(){const t=localStorage.getItem('theme')||'light';document.documentElement.dataset.theme=t;const b=document.getElementById('darkBtn');if(b)b.querySelector('.material-icons-round').textContent=t==='dark'?'light_mode':'dark_mode'})();

// Progress bar + topbar scroll
(function(){const bar=document.getElementById('readingProgress');if(!bar)return;window.addEventListener('scroll',()=>{const h=document.documentElement;bar.style.width=Math.min(100,(h.scrollTop/(h.scrollHeight-h.clientHeight))*100)+'%';document.getElementById('topbar')?.classList.toggle('scrolled',h.scrollTop>30)},{passive:true})})();

// Card reveal
(function(){const io=new IntersectionObserver(es=>{es.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');io.unobserve(e.target)}})},{threshold:.06});document.querySelectorAll('.clip-card').forEach(c=>io.observe(c))})();

// Transcript toggle
function toggleTranscript(btn){
    const box=btn.nextElementSibling;
    const open=box.classList.toggle('open');
    btn.classList.toggle('open',open);
    btn.querySelector('span:last-child').textContent=open?'Hide Transcript':'View Transcript';
}

// Search
function setSearch(q){const i=document.getElementById('clipSearch');if(i){i.value=q;filterClips(q)}}
function filterClips(q){
    q=q.toLowerCase().trim();
    document.querySelectorAll('.clip-card').forEach(c=>{
        c.style.display=(!q||c.dataset.name.includes(q))?'':'none';
    });
    document.querySelectorAll('.topic-group').forEach(g=>{
        g.style.display=[...g.querySelectorAll('.clip-card')].some(c=>c.style.display!=='none')?'':'none';
    });
}

// Sort
function sortClips(by){
    document.querySelectorAll('.clips-grid').forEach(grid=>{
        const cards=[...grid.querySelectorAll('.clip-card')];
        cards.sort((a,b)=>{
            if(by==='rank')    return (parseInt(a.dataset.rank)||999)-(parseInt(b.dataset.rank)||999);
            if(by==='virality')return parseFloat(b.dataset.virality||0)-parseFloat(a.dataset.virality||0);
            return a.dataset.name.localeCompare(b.dataset.name);
        });
        cards.forEach(c=>grid.appendChild(c));
    });
}

// Player
const player=document.getElementById('mainPlayer'),backdrop=document.getElementById('playerBackdrop');
function openPlayer(idx){
    const c=CLIPS[idx];if(!c)return;
    player.src=c.src;
    document.getElementById('playerRank').textContent='Rank #'+c.rank;
    document.getElementById('playerTitle').textContent=c.title;
    document.getElementById('pDur').textContent=c.duration||'—';
    document.getElementById('pSize').textContent=c.size;
    document.getElementById('pFile').textContent=c.filename;
    const dl=document.getElementById('pDl');dl.href=c.src;dl.download=c.filename;
    backdrop.classList.add('open');player.play().catch(()=>{});
    document.body.style.overflow='hidden';
}
function closePlayer(){player.pause();player.src='';backdrop.classList.remove('open');document.body.style.overflow=''}

// Keyboard
document.addEventListener('keydown',e=>{
    const inInput=document.activeElement?.matches('input,textarea,select');
    if(e.key==='Escape'){closePlayer();closeTikTokModal();}
    if(!inInput&&e.key==='d')toggleDark();
    if(!inInput&&e.key==='/'){e.preventDefault();document.getElementById('clipSearch')?.focus()}
});

// Toast
function showToast(msg,type='info'){
    const s=document.getElementById('toastStack');if(!s)return;
    const ic={success:'check_circle',error:'error_outline',info:'info'};
    const t=document.createElement('div');t.className='toast '+type;
    t.innerHTML=`<span class="material-icons-round">${ic[type]||'info'}</span><span>${msg}</span>`;
    s.appendChild(t);setTimeout(()=>{t.style.transition='opacity .3s';t.style.opacity='0';setTimeout(()=>t.remove(),300)},3000);
}

// ─── Import Modal ─────────────────────────────────────────────────────────────
let _importSourceBtn = null;

function openImportModal(btn) {
    const title     = btn.dataset.title || '';
    const txtPath   = btn.dataset.txt   || '';
    const thumbPath = btn.dataset.thumb || '';

    document.getElementById('imTitle').value     = title;
    document.getElementById('imTxtPath').value   = txtPath;
    document.getElementById('imThumbPath').value = thumbPath;
    document.getElementById('imTitleDisplay').textContent      = title || '—';
    document.getElementById('imTranscriptDisplay').textContent = 'Loading…';

    const thumbWrap = document.getElementById('imThumbWrap');
    const thumbImg  = document.getElementById('imThumbPreview');
    const thumbStatus = document.getElementById('imThumbStatus');
    thumbImg.src = '';
    thumbWrap.style.display = 'none';
    thumbStatus.textContent = thumbPath ? '⏳ Copying thumbnail to uploads…' : '⚠️ No thumbnail found';
    thumbStatus.className   = 'im-thumb-status';

    document.getElementById('importBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
    _importSourceBtn = btn;

    if (thumbPath) {
        const fd = new FormData();
        fd.append('thumb_path', thumbPath);
        fetch('clip_thumb_copy.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    thumbImg.src = data.web_path;
                    thumbImg.onload = () => { thumbWrap.style.display = 'block'; };
                    thumbStatus.textContent = data.cached ? '✓ Thumbnail already in uploads' : '✓ Thumbnail copied to uploads';
                    thumbStatus.className   = 'im-thumb-status im-thumb-ok';
                    document.getElementById('imThumbWebPath').value = data.web_path;
                } else {
                    thumbStatus.textContent = '✗ ' + (data.message || 'Could not copy thumbnail');
                    thumbStatus.className   = 'im-thumb-status im-thumb-err';
                }
            })
            .catch(() => {
                thumbStatus.textContent = '✗ Network error copying thumbnail';
                thumbStatus.className   = 'im-thumb-status im-thumb-err';
            });
    }

    if (txtPath) {
        fetch('clip_txt.php?path=' + encodeURIComponent(txtPath))
            .then(r => r.text())
            .then(text => {
                document.getElementById('imTranscript').value             = text;
                document.getElementById('imTranscriptDisplay').textContent = text;
            })
            .catch(() => {
                const fallback = btn.dataset.transcript || '(could not load .txt file)';
                document.getElementById('imTranscript').value             = fallback;
                document.getElementById('imTranscriptDisplay').textContent = fallback;
            });
    } else {
        const fallback = btn.dataset.transcript || '(no .txt file found)';
        document.getElementById('imTranscript').value             = fallback;
        document.getElementById('imTranscriptDisplay').textContent = fallback;
    }
}

function closeImportModal() {
    document.getElementById('importBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    _importSourceBtn = null;
    const btn = document.getElementById('imSubmitBtn');
    btn.disabled = false;
    document.getElementById('imSubmitLabel').textContent = 'Import Article';
}

async function submitClipImport() {
    const title      = document.getElementById('imTitle').value.trim();
    const transcript = document.getElementById('imTranscript').value.trim();
    const btn        = document.getElementById('imSubmitBtn');

    if (!title)      { showToast('Title is required', 'error');      return; }
    if (!transcript) { showToast('Transcript is required', 'error'); return; }

    btn.disabled = true;
    document.getElementById('imSubmitLabel').textContent = 'Importing…';

    try {
        const fd = new FormData();
        fd.append('title',          title);
        fd.append('content',        transcript);
        fd.append('txt_path',       document.getElementById('imTxtPath').value);
        fd.append('thumb_web_path', document.getElementById('imThumbWebPath').value);

        const res  = await fetch('clip_import.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast('Imported successfully! Article #' + data.id, 'success');
            closeImportModal();
            if (_importSourceBtn) {
                _importSourceBtn.classList.add('imported');
                _importSourceBtn.innerHTML = '<span class="material-icons-round">check</span>Imported';
            }
        } else if (data.message === 'Already imported') {
            showToast('Already imported (Article #' + data.existing_id + ')', 'info');
            closeImportModal();
        } else {
            showToast(data.message || 'Import failed', 'error');
            btn.disabled = false;
            document.getElementById('imSubmitLabel').textContent = 'Import Article';
        }
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
        btn.disabled = false;
        document.getElementById('imSubmitLabel').textContent = 'Import Article';
    }
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('importBackdrop').classList.contains('open')) {
        closeImportModal();
    }
});

// ─── TikTok Modal ─────────────────────────────────────────────────────────────
let _tiktokSourceBtn = null;

function openTikTokModal(btn) {
    const title    = btn.dataset.title    || '';
    const hook     = btn.dataset.hook     || '';
    const tags     = btn.dataset.tags     || '';
    const virality = btn.dataset.virality || '';
    const duration = btn.dataset.duration || '';
    const source   = btn.dataset.source   || '';
    const video    = btn.dataset.video    || '';
    const videoPath= btn.dataset.videoPath|| '';  // raw relative path for server-side copy
    const thumb    = btn.dataset.thumb    || '';
    const date     = btn.dataset.date     || '';
    const topic    = btn.dataset.topic    || '';

    _tiktokSourceBtn = btn;

    // Store raw video path on modal for submit step
    document.getElementById('tiktokBackdrop').dataset.videoPath = videoPath;

    // Thumbnail
    const ttThumb    = document.getElementById('ttThumb');
    const ttThumbIcon= document.getElementById('ttThumbIcon');
    if (thumb) {
        ttThumb.src     = thumb;
        ttThumb.style.display = 'block';
        ttThumbIcon.style.display = 'none';
    } else {
        ttThumb.style.display = 'none';
        ttThumbIcon.style.display = 'block';
    }

    // Fill preview fields
    document.getElementById('ttTitle').textContent       = title    || '—';
    document.getElementById('ttPTitle').textContent      = title    || '—';
    document.getElementById('ttPHook').textContent       = hook     || '—';
    document.getElementById('ttPTags').textContent       = tags     || '—';
    document.getElementById('ttPVirality').textContent   = virality || '—';
    document.getElementById('ttPDuration').textContent   = duration || '—';
    document.getElementById('ttPSource').textContent     = source   || '—';
    document.getElementById('ttPDate').textContent       = date     || '—';
    document.getElementById('ttPTopic').textContent      = topic    || '—';
    // video_url shows pending until copy completes
    const vidEl = document.getElementById('ttPVideo');
    vidEl.textContent  = '⏳ Will be copied to public URL on send…';
    vidEl.style.color  = 'var(--ink-faint)';
    vidEl.style.fontStyle = 'italic';

    // Webhook host hint
    try {
        const u = new URL(ZAPIER_WEBHOOK_URL);
        document.getElementById('ttWebhookHost').textContent = u.hostname;
    } catch(e) {
        document.getElementById('ttWebhookHost').textContent = ZAPIER_WEBHOOK_URL.slice(0,40)+'…';
    }

    // Reset send button
    const sendBtn = document.getElementById('ttSendBtn');
    sendBtn.disabled = false;
    document.getElementById('ttSendLabel').textContent = 'Send to TikTok';

    document.getElementById('tiktokBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeTikTokModal() {
    document.getElementById('tiktokBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    _tiktokSourceBtn = null;
}

async function submitTikTok() {
    if (!ZAPIER_WEBHOOK_URL || ZAPIER_WEBHOOK_URL.includes('YOUR_ZAPIER_HOOK_ID')) {
        showToast('⚠️ Set your Zapier webhook URL in the ZAPIER_WEBHOOK_URL constant', 'error');
        return;
    }

    const btn      = _tiktokSourceBtn;
    const sendBtn  = document.getElementById('ttSendBtn');
    const videoPath= document.getElementById('tiktokBackdrop').dataset.videoPath || '';

    sendBtn.disabled = true;

    // ── STEP 1: Copy video to public folder via PHP ───────────────────────────
    let publicVideoUrl = '';
    try {
        document.getElementById('ttSendLabel').textContent = 'Copying video…';

        const copyFd = new FormData();
        copyFd.append('video_path', videoPath);

        const copyRes  = await fetch('clip_video_copy.php', { method: 'POST', body: copyFd });
        const copyData = await copyRes.json();

        if (!copyData.success) {
            showToast('Video copy failed: ' + (copyData.message || 'unknown error'), 'error');
            sendBtn.disabled = false;
            document.getElementById('ttSendLabel').textContent = 'Send to TikTok';
            return;
        }

        publicVideoUrl = copyData.public_url;

        // ── Update the modal row to show the real public URL ─────────────────
        const vidEl = document.getElementById('ttPVideo');
        vidEl.textContent     = publicVideoUrl;
        vidEl.style.color     = 'var(--success, #059669)';
        vidEl.style.fontStyle = 'normal';

        const cacheNote = copyData.cached ? ' (cached)' : '';
        showToast('✓ Video ready' + cacheNote + ' — sending to Zapier…', 'info');

    } catch (err) {
        showToast('Copy network error: ' + err.message, 'error');
        sendBtn.disabled = false;
        document.getElementById('ttSendLabel').textContent = 'Send to TikTok';
        return;
    }

    // ── STEP 2: Send public URL + metadata to Zapier webhook ─────────────────
    document.getElementById('ttSendLabel').textContent = 'Sending to Zapier…';

    const payload = {
        title:      document.getElementById('ttPTitle').textContent,
        hook:       document.getElementById('ttPHook').textContent,
        tags:       document.getElementById('ttPTags').textContent,
        virality:   document.getElementById('ttPVirality').textContent,
        duration:   document.getElementById('ttPDuration').textContent,
        source:     document.getElementById('ttPSource').textContent,
        video_url:  publicVideoUrl,   // ← public URL Buffer can actually reach
        thumbnail:  document.getElementById('ttThumb').src || '',
        date:       document.getElementById('ttPDate').textContent,
        topic:      document.getElementById('ttPTopic').textContent,
        transcript: btn ? btn.dataset.transcript : '',
        sent_at:    new Date().toISOString(),
        origin:     window.location.origin,
    };

    try {
        // URLSearchParams = no Content-Type header = no CORS preflight
        const body = new URLSearchParams();
        Object.entries(payload).forEach(([k, v]) => body.append(k, v));

        const res = await fetch(ZAPIER_WEBHOOK_URL, { method: 'POST', body });

        if (res.ok) {
            showToast('✓ Sent to TikTok via Zapier!', 'success');
            closeTikTokModal();
            if (btn) {
                btn.classList.add('sent');
                btn.innerHTML = '<span class="material-icons-round">check</span><span>Sent</span>';
            }
        } else {
            const errText = await res.text().catch(() => '');
            showToast('Zapier error ' + res.status + (errText ? ': ' + errText.slice(0, 80) : ''), 'error');
            sendBtn.disabled = false;
            document.getElementById('ttSendLabel').textContent = 'Send to TikTok';
        }
    } catch (err) {
        showToast('Network error: ' + err.message, 'error');
        sendBtn.disabled = false;
        document.getElementById('ttSendLabel').textContent = 'Send to TikTok';
    }
}
</script>
</body>
</html>