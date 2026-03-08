<?php
// MediaStack-themed News Aggregator — Purple Editorial Edition

if (isset($_GET['debug']) && $_GET['debug'] === 'view-html') {
    header('Access-Control-Allow-Origin: *');
    header('Content-Type: text/html; charset=utf-8');
    $googleNewsUrl = isset($_GET['url']) ? $_GET['url'] : '';
    if (empty($googleNewsUrl)) { echo "No URL provided. Usage: ?debug=view-html&url=GOOGLE_NEWS_URL"; exit(); }
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL=>$googleNewsUrl,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>10,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']);
    $html = curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    echo "<h1>Debug: Google News HTML</h1>";
    echo "<p><strong>Original URL:</strong> ".htmlspecialchars($googleNewsUrl)."</p>";
    echo "<p><strong>Final URL:</strong> ".htmlspecialchars($finalUrl)."</p>";
    echo "<hr><h2>Raw HTML:</h2><pre>".htmlspecialchars($html)."</pre>";
    exit();
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>MediaStack News — Live Data</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,400&family=Sora:wght@300;400;500;600&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
/* ══════════════════════════════════════════════════════
   DESIGN TOKENS
══════════════════════════════════════════════════════ */
:root {
    --purple:#7C3AED;--purple-md:#6D28D9;--purple-dk:#4C1D95;
    --purple-light:#EDE9FE;--purple-pale:#F5F3FF;--purple-glow:rgba(124,58,237,.18);
    --ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;
    --canvas:#F3F1FA;--surface:#FFFFFF;--surface-2:#EEEAF8;
    --border:#E2DDEF;--border-md:#C9C2E0;
    --success:#059669;--warn:#D97706;--danger:#DC2626;--info:#2563EB;
    --r:14px;--r-sm:9px;--r-xs:5px;
    --sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);
    --sh-md:0 4px 18px rgba(60,20,120,.11);
    --sh-lg:0 20px 48px rgba(60,20,120,.18);
}
[data-theme="dark"] {
    --ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;
    --canvas:#0E0C18;--surface:#17142A;--surface-2:#1E1A30;
    --border:#2A2540;--border-md:#362F50;
    --purple-light:#1E1440;--purple-pale:#150F2E;
    --sh:0 1px 4px rgba(0,0,0,.4);--sh-md:0 4px 18px rgba(0,0,0,.5);--sh-lg:0 20px 48px rgba(0,0,0,.7);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;scroll-behavior:smooth}
body{font-family:'Sora',sans-serif;background:var(--canvas);color:var(--ink);min-height:100vh;transition:background .2s,color .2s;line-height:1.6}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--purple)}

/* ══ TOPBAR ══════════════════════════════════════════ */
.topbar {
    position: sticky; top: 0; z-index: 200;
    background: var(--surface); border-bottom: 1px solid var(--border);
    box-shadow: var(--sh); height: 64px;
    display: flex; align-items: center; padding: 0 28px; gap: 16px;
}
.tb-wordmark {
    display: flex; align-items: center; gap: 10px; text-decoration: none;
}
.tb-logo {
    width: 38px; height: 38px; border-radius: 10px;
    background: var(--purple-light); display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.tb-logo .material-icons-round { font-size: 20px !important; color: var(--purple); }
.tb-brand { font-family: 'Playfair Display', serif; font-size: 17px; font-weight: 700; color: var(--ink); }
.tb-sub { font-size: 9px; color: var(--ink-faint); font-family: 'Fira Code', monospace; margin-top: 1px; }
.tb-divider { width: 1px; height: 28px; background: var(--border); flex-shrink: 0; }
.live-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 3px 9px; border-radius: 99px;
    background: #FFFBEB; border: 1px solid #FDE68A;
    color: #92400E; font-size: 9px; font-weight: 700; font-family: 'Fira Code', monospace;
    letter-spacing: .06em; text-transform: uppercase; flex-shrink: 0;
}
.live-dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: #F59E0B; flex-shrink: 0;
    animation: livePulse 2s ease-in-out infinite;
}
@keyframes livePulse { 0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(245,158,11,.4)} 50%{opacity:.7;box-shadow:0 0 0 4px rgba(245,158,11,0)} }
.tb-date { font-size: 11px; color: var(--ink-faint); font-family: 'Fira Code', monospace; white-space: nowrap; }
.tb-right { margin-left: auto; display: flex; align-items: center; gap: 8px; }

/* ══ SHARED BUTTON SYSTEM ════════════════════════════ */
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; border-radius: var(--r-sm); border: none; cursor: pointer;
    font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 600;
    text-decoration: none; transition: all .15s; white-space: nowrap; flex-shrink: 0;
}
.btn .material-icons-round { font-size: 15px !important; }
.btn-purple { background: var(--purple); color: white; }
.btn-purple:hover { background: var(--purple-md); box-shadow: 0 4px 14px var(--purple-glow); transform: translateY(-1px); }
.btn-green { background: #059669; color: white; }
.btn-green:hover { background: #047857; box-shadow: 0 4px 14px rgba(5,150,105,.3); transform: translateY(-1px); }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--ink-muted); }
.btn-outline:hover { border-color: var(--purple); color: var(--purple); background: var(--purple-pale); }
.btn-ghost { background: transparent; color: var(--ink-muted); }
.btn-ghost:hover { background: var(--canvas); color: var(--ink); }
.btn-icon { width: 36px; height: 36px; padding: 0; justify-content: center; border-radius: var(--r-sm); }

/* ══ MAIN LAYOUT ═════════════════════════════════════ */
.page-wrap { max-width: 1440px; margin: 0 auto; padding: 20px 24px 60px; }

/* ══ SEARCH STRIP ════════════════════════════════════ */
.search-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 14px 16px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px;
}
.search-wrap { flex: 1; position: relative; display: flex; align-items: center; }
.search-wrap .material-icons-round {
    position: absolute; left: 10px; font-size: 16px !important; color: var(--ink-faint); pointer-events: none;
}
.search-input {
    width: 100%; padding: 9px 36px;
    border: 1px solid var(--border); border-radius: var(--r-sm);
    background: var(--canvas); color: var(--ink);
    font-family: 'Sora', sans-serif; font-size: 13px;
    outline: none; transition: border-color .15s, box-shadow .15s;
}
.search-input:focus { border-color: var(--purple); box-shadow: 0 0 0 3px var(--purple-glow); }
.search-input::placeholder { color: var(--ink-faint); }

/* ══ CATEGORY TABS ═══════════════════════════════════ */
.cat-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 14px 16px; margin-bottom: 16px;
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
}
.cat-tab {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 7px 14px; border-radius: var(--r-sm);
    border: 1px solid var(--border); background: var(--canvas); color: var(--ink-muted);
    cursor: pointer; font-family: 'Sora', sans-serif; font-size: 12px; font-weight: 600;
    transition: all .15s;
}
.cat-tab .material-icons-round { font-size: 14px !important; }
.cat-tab:hover { border-color: var(--border-md); color: var(--ink); background: var(--surface-2); }
.cat-tab.active { background: var(--purple); border-color: var(--purple); color: white; box-shadow: 0 2px 10px var(--purple-glow); }

/* 🔥 Trending tab */
.cat-tab.trending-btn {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    border-color: transparent; color: white;
    animation: trendGlow 2.5s ease-in-out infinite;
}
.cat-tab.trending-btn.active { box-shadow: 0 4px 16px rgba(239,68,68,.45); }
.cat-tab.trending-btn:hover { background: linear-gradient(135deg, #DC2626, #B91C1C); transform: scale(1.03); }
@keyframes trendGlow { 0%,100%{box-shadow:0 2px 8px rgba(239,68,68,.3)} 50%{box-shadow:0 4px 18px rgba(239,68,68,.6)} }

/* ══ LOCATION STRIP ══════════════════════════════════ */
.loc-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 11px 16px; margin-bottom: 16px;
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
}
.loc-label {
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .1em; color: var(--ink-faint); font-family: 'Fira Code', monospace;
    flex-shrink: 0;
}
.location-tab {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 5px 12px; border-radius: 99px;
    border: 1px solid var(--border); background: var(--canvas); color: var(--ink-muted);
    cursor: pointer; font-family: 'Sora', sans-serif; font-size: 11px; font-weight: 500;
    transition: all .15s;
}
.location-tab:hover { border-color: var(--border-md); color: var(--ink); }
.location-tab.active { background: #EFF6FF; border-color: #93C5FD; color: #1E40AF; font-weight: 600; }
.location-tab.disabled { opacity: .38; cursor: not-allowed; pointer-events: none; }

/* ══ STATS BAR ═══════════════════════════════════════ */
.stats-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    padding: 12px 18px; margin-bottom: 20px;
    display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap;
}
.stats-left { display: flex; align-items: center; gap: 18px; font-size: 11px; color: var(--ink-faint); flex-wrap: wrap; }
.stats-left .stat-item { display: flex; align-items: center; gap: 5px; font-family: 'Fira Code', monospace; }
.stats-left .stat-item .material-icons-round { font-size: 13px !important; }
.filter-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 99px;
    background: var(--purple-light); border: 1px solid #C4B5FD;
    color: var(--purple-md); font-size: 11px; font-weight: 700; font-family: 'Fira Code', monospace;
    white-space: nowrap;
}
.stats-right { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink-faint); flex-shrink: 0; }
.stats-right label { font-family: 'Fira Code', monospace; font-size: 10px; }
.stats-right select {
    padding: 5px 10px; border: 1px solid var(--border); border-radius: var(--r-xs);
    background: var(--canvas); color: var(--ink); font-size: 12px; font-family: 'Sora', sans-serif; cursor: pointer;
}

/* ══ LOADING ══════════════════════════════════════════ */
.loading { text-align: center; padding: 80px 20px; }
.loading-spinner-large {
    width: 44px; height: 44px;
    border: 3px solid var(--border-md); border-top-color: var(--purple);
    border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 16px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.loading-text { font-size: 14px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }

/* ══ NEWS GRID ════════════════════════════════════════ */
.news-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 18px; }

/* ══ NEWS CARD ════════════════════════════════════════ */
.news-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--r); box-shadow: var(--sh);
    overflow: hidden; cursor: pointer;
    transition: all .2s cubic-bezier(.4,0,.2,1);
}
.news-card:hover { transform: translateY(-3px); box-shadow: var(--sh-md); border-color: var(--border-md); }
.news-card-image {
    width: 100%; height: 210px; position: relative; overflow: hidden;
    background: linear-gradient(135deg, var(--purple-dk), var(--purple));
    display: flex; align-items: center; justify-content: center;
}
.news-card-image img { width: 100%; height: 100%; object-fit: cover; display: block; }
.news-card-image.has-image { background: var(--canvas); }
.image-placeholder { font-size: 3.5em; opacity: .35; }
.loading-spinner {
    position: absolute; top: 10px; right: 10px;
    width: 24px; height: 24px;
    border: 2px solid rgba(255,255,255,.25); border-top-color: white;
    border-radius: 50%; animation: spin .7s linear infinite;
}
.news-card-content { padding: 18px 20px; }
.card-badges { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; gap: 8px; }
.news-title {
    font-family: 'Playfair Display', serif; font-size: 15px; font-weight: 700;
    color: var(--ink); line-height: 1.45; margin-bottom: 10px;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
}
.news-meta { display: flex; flex-direction: column; gap: 4px; font-size: 11px; color: var(--ink-faint); font-family: 'Fira Code', monospace; }
.news-meta-row { display: flex; align-items: center; gap: 5px; }
.news-meta-row .material-icons-round { font-size: 12px !important; }

/* Card badges */
.card-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 9px; border-radius: 99px;
    font-size: 9px; font-weight: 700; font-family: 'Fira Code', monospace;
    text-transform: uppercase; letter-spacing: .06em; border: 1px solid;
}
.badge-hot { background:#FFF1F2; border-color:#FECDD3; color:#9F1239; }
.badge-trending { background:#FFF7ED; border-color:#FED7AA; color:#9A3412; }
.badge-rising { background:#EFF6FF; border-color:#BFDBFE; color:#1E40AF; }
.badge-sources { background:var(--purple-light); border-color:#C4B5FD; color:var(--purple-md); }
.badge-related { background:var(--purple-light); border-color:#C4B5FD; color:var(--purple-md); }

/* Developing news overlay badges */
.developing-news-badge {
    position: absolute; top: 10px; left: 10px;
    padding: 4px 10px; border-radius: var(--r-xs);
    background: var(--purple); color: white;
    font-size: 9px; font-weight: 700; font-family: 'Fira Code', monospace;
    text-transform: uppercase; letter-spacing: .08em;
    z-index: 10; box-shadow: 0 2px 8px var(--purple-glow);
}
.source-reference-badge {
    position: absolute; bottom: 10px; left: 10px; right: 10px;
    background: linear-gradient(135deg, #F59E0B, #D97706);
    color: white; padding: 6px 10px; border-radius: var(--r-xs);
    font-size: 9px; font-weight: 600; font-family: 'Fira Code', monospace;
    z-index: 10; box-shadow: 0 2px 8px rgba(245,158,11,.5);
    animation: srcBlink 1.8s ease-in-out infinite;
    display: flex; align-items: center; gap: 6px; overflow: hidden;
}
@keyframes srcBlink { 0%,100%{opacity:1;box-shadow:0 2px 8px rgba(245,158,11,.5)} 50%{opacity:.75;box-shadow:0 4px 16px rgba(245,158,11,.8)} }
.source-reference-badge .source-icon { flex-shrink: 0; animation: srcArrow 1s ease-in-out infinite; }
@keyframes srcArrow { 0%,100%{transform:translateX(0)} 50%{transform:translateX(-3px)} }
.source-reference-badge .source-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }

/* ══ MODAL ════════════════════════════════════════════ */
.modal {
    display: none; position: fixed; z-index: 1000;
    inset: 0; overflow: auto;
    background: rgba(0,0,0,.7); backdrop-filter: blur(4px);
}
.modal-content {
    background: var(--surface); margin: 36px auto;
    border-radius: var(--r); max-width: 1080px;
    box-shadow: var(--sh-lg); max-height: 90vh; overflow-y: auto;
    border: 1px solid var(--border);
}
.modal-header {
    position: sticky; top: 0; z-index: 10;
    background: var(--surface); border-bottom: 1px solid var(--border);
    padding: 18px 24px;
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
}
.modal-header-title {
    display: flex; align-items: center; gap: 10px;
}
.modal-header-title .material-icons-round { font-size: 20px !important; color: var(--purple); }
.modal-header h2 { font-family: 'Playfair Display', serif; font-size: 17px; color: var(--ink); }
.close {
    width: 34px; height: 34px; border-radius: var(--r-xs);
    background: transparent; border: 1px solid var(--border);
    color: var(--ink-faint); cursor: pointer; font-size: 20px; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s; line-height: 1;
}
.close:hover { background: var(--canvas); color: var(--ink); border-color: var(--border-md); }
.modal-body { padding: 28px; }

/* ══ MODAL ARTICLE SECTIONS ══════════════════════════ */
.main-article { margin-bottom: 28px; padding-bottom: 28px; border-bottom: 2px solid var(--border); }
.main-article-image { width: 100%; max-height: 420px; object-fit: cover; border-radius: var(--r-sm); margin-bottom: 20px; }
.main-article-title {
    font-family: 'Playfair Display', serif;
    font-size: 2em; margin-bottom: 14px; color: var(--ink); font-weight: 700; line-height: 1.3;
}
.main-article-meta {
    font-size: 12px; color: var(--ink-muted); line-height: 2;
    font-family: 'Fira Code', monospace; margin-bottom: 20px;
}
.article-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
.read-more-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 24px; background: var(--purple); color: white;
    text-decoration: none; border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 13px; border: none; cursor: pointer;
    transition: all .2s;
}
.read-more-btn:hover { background: var(--purple-md); transform: translateY(-2px); box-shadow: 0 6px 16px var(--purple-glow); }
.import-article-btn {
    display: inline-flex; align-items: center; gap: 7px;
    padding: 11px 24px; background: #059669; color: white;
    text-decoration: none; border-radius: var(--r-sm);
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 13px; border: none; cursor: pointer;
    transition: all .2s;
}
.import-article-btn:hover { background: #047857; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(5,150,105,.3); }

/* Source links */
.source-link {
    display: inline-flex; align-items: center; gap: 5px;
    color: var(--purple-md); text-decoration: none; font-weight: 600;
    padding: 3px 10px; background: var(--purple-light); border-radius: var(--r-xs);
    border: 1px solid #C4B5FD; font-family: 'Fira Code', monospace; font-size: 11px;
    transition: all .15s;
}
.source-link:hover { background: #DDD6FE; color: var(--purple-dk); transform: translateY(-1px); }
.main-source-link {
    display: inline-flex; align-items: center;
    color: var(--purple-md); text-decoration: none; font-weight: 600;
    padding: 2px 8px; background: var(--purple-light); border-radius: var(--r-xs);
    border: 1px solid #C4B5FD; font-family: 'Fira Code', monospace; font-size: 11px;
    transition: all .15s; margin: 1px;
}
.main-source-link:hover { background: #DDD6FE; color: var(--purple-dk); }

/* Developing news section */
.developing-news-section { margin-top: 28px; }
.developing-news-header {
    display: flex; align-items: center; justify-content: space-between;
    gap: 12px; margin-bottom: 20px; padding-bottom: 14px;
    border-bottom: 1px solid var(--border); flex-wrap: wrap;
}
.developing-news-header h3 {
    font-family: 'Playfair Display', serif; font-size: 17px; color: var(--ink); font-weight: 700;
    display: flex; align-items: center; gap: 8px; margin: 0;
}
.developing-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 99px;
    background: #FFF1F2; border: 1px solid #FECDD3;
    color: #9F1239; font-size: 9px; font-weight: 700;
    font-family: 'Fira Code', monospace; text-transform: uppercase; letter-spacing: .08em;
    animation: devBlink 2s ease-in-out infinite;
}
@keyframes devBlink { 0%,100%{opacity:1} 50%{opacity:.6} }
.developing-news-loading { text-align: center; padding: 40px; color: var(--ink-faint); font-family: 'Fira Code', monospace; font-size: 12px; }
.related-articles-grid { display: grid; gap: 12px; }
.related-article {
    background: var(--canvas); border: 1px solid var(--border);
    border-left: 3px solid var(--purple); border-radius: var(--r-sm);
    padding: 16px 18px; transition: all .15s;
}
.related-article:hover { border-color: var(--border-md); border-left-color: var(--purple); box-shadow: var(--sh); }
.related-article-title { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 8px; line-height: 1.4; }
.related-article-meta {
    display: flex; gap: 14px; flex-wrap: wrap;
    font-size: 10px; color: var(--ink-faint); font-family: 'Fira Code', monospace; margin-top: 8px;
}
.related-article-meta span { display: flex; align-items: center; gap: 4px; }
.related-article-actions { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
.related-read-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 16px; background: var(--purple); color: white;
    text-decoration: none; border-radius: var(--r-xs);
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 11px; border: none; cursor: pointer;
    transition: all .15s;
}
.related-read-btn:hover { background: var(--purple-md); transform: translateY(-1px); }
.related-import-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 16px; background: #059669; color: white;
    text-decoration: none; border-radius: var(--r-xs);
    font-family: 'Sora', sans-serif; font-weight: 600; font-size: 11px; border: none; cursor: pointer;
    transition: all .15s;
}
.related-import-btn:hover { background: #047857; transform: translateY(-1px); }
.no-related { text-align: center; padding: 40px; color: var(--ink-faint); font-family: 'Fira Code', monospace; font-size: 12px; }

/* ══ FOOTER ══════════════════════════════════════════ */
.page-footer {
    text-align: center; padding: 28px 20px;
    background: var(--surface); border-top: 1px solid var(--border);
    font-size: 11px; color: var(--ink-faint); font-family: 'Fira Code', monospace;
}

/* ══ RESPONSIVE ══════════════════════════════════════ */
@media(max-width:768px) {
    .topbar { padding: 0 14px; height: 56px; }
    .tb-date, .tb-divider { display: none; }
    .page-wrap { padding: 14px 14px 48px; }
    .news-grid { grid-template-columns: 1fr; }
    .modal-content { margin: 14px; max-width: calc(100% - 28px); }
    .main-article-title { font-size: 1.6em; }
    .article-actions { flex-direction: column; }
    .read-more-btn, .import-article-btn { width: 100%; justify-content: center; }
    .stats-card { flex-direction: column; align-items: flex-start; }
}
@media(max-width:560px) {
    .tb-brand { display: none; }
    .tb-sub { display: none; }
}
</style>
</head>
<body>

<!-- ══ TOPBAR ══════════════════════════════════════════ -->
<div class="topbar">
    <div class="tb-wordmark">
        <div class="tb-logo"><span class="material-icons-round">newspaper</span></div>
        <div>
            <div class="tb-brand">MediaStack News</div>
            <div class="tb-sub">AI-powered live aggregator</div>
        </div>
    </div>
    <div class="tb-divider"></div>
    <div class="live-pill"><span class="live-dot"></span>Live Data</div>
    <span class="tb-date">
        <span id="currentDate">—</span>
        &nbsp;·&nbsp;
        <span id="loadTime">—:—</span>
    </span>
    <div class="tb-right">
        <button class="btn btn-outline" onclick="importAllNews()">
            <span class="material-icons-round">save_alt</span>
            Import All&nbsp;<span id="importCount" style="font-family:'Fira Code',monospace">(0)</span>
        </button>
        <button class="btn btn-outline btn-icon" onclick="loadTestData()" title="Load test data">
            <span class="material-icons-round">science</span>
        </button>
        <button class="btn btn-purple" onclick="showDashboard()">
            <span class="material-icons-round">dashboard</span>Dashboard
        </button>
        <button class="btn btn-outline btn-icon" onclick="refreshNews()" title="Refresh">
            <span class="material-icons-round">refresh</span>
        </button>
        <button class="btn btn-outline btn-icon" id="darkBtn" onclick="toggleDark()" title="Toggle dark mode">
            <span class="material-icons-round">dark_mode</span>
        </button>
    </div>
</div>

<div class="page-wrap">

    <!-- ══ SEARCH STRIP ══════════════════════════════════ -->
    <div class="search-card">
        <div class="search-wrap">
            <span class="material-icons-round">search</span>
            <input class="search-input" type="text" id="searchInput"
                   placeholder="Search today's news by keyword…"/>
        </div>
        <button class="btn btn-purple" onclick="searchNews()">
            <span class="material-icons-round">search</span>Search
        </button>
    </div>

    <!-- ══ CATEGORY TABS ══════════════════════════════════ -->
    <div class="cat-card">
        <button class="cat-tab trending-btn active" onclick="selectPhilippinesTrending(this)">
            <span class="material-icons-round">whatshot</span>Philippines Trending
        </button>
        <button class="cat-tab" onclick="selectCategory('business', this)">
            <span class="material-icons-round">business_center</span>Business
        </button>
        <button class="cat-tab" onclick="selectCategory('entertainment', this)">
            <span class="material-icons-round">movie</span>Entertainment
        </button>
        <button class="cat-tab" onclick="selectCategory('health', this)">
            <span class="material-icons-round">favorite</span>Health
        </button>
        <button class="cat-tab" onclick="selectCategory('politics', this)">
            <span class="material-icons-round">account_balance</span>Politics
        </button>
        <button class="cat-tab" onclick="selectCategory('science', this)">
            <span class="material-icons-round">biotech</span>Science
        </button>
        <button class="cat-tab" onclick="selectCategory('sports', this)">
            <span class="material-icons-round">sports_soccer</span>Sports
        </button>
        <button class="cat-tab" onclick="selectCategory('technology', this)">
            <span class="material-icons-round">computer</span>Technology
        </button>
        <button class="cat-tab" onclick="selectCategory('weather', this)">
            <span class="material-icons-round">wb_sunny</span>Weather
        </button>
    </div>

    <!-- ══ LOCATION STRIP ═════════════════════════════════ -->
    <div class="loc-card">
        <span class="loc-label"><span class="material-icons-round" style="font-size:11px!important;vertical-align:middle">location_on</span> Location</span>
        <button class="location-tab disabled" onclick="selectLocation('worldwide', this)">🌐 Worldwide</button>
        <button class="location-tab active disabled" onclick="selectLocation('Philippines', this)">🇵🇭 Philippines</button>
        <button class="location-tab disabled" onclick="selectLocation('Luzon Philippines', this)">📍 Luzon</button>
        <button class="location-tab disabled" onclick="selectLocation('Visayas Philippines', this)">📍 Visayas</button>
        <button class="location-tab disabled" onclick="selectLocation('Mindanao Philippines', this)">📍 Mindanao</button>
    </div>

    <!-- ══ STATS BAR ══════════════════════════════════════ -->
    <div class="stats-card">
        <div class="stats-left">
            <span class="stat-item">
                <span class="live-dot"></span>
                Loaded at <strong id="loadTimeInline">—:—</strong>
            </span>
            <span id="filterDisplay" class="filter-pill">
                <span class="material-icons-round" style="font-size:12px!important">folder_open</span>
                <span id="categoryName" style="background:linear-gradient(90deg,#ef4444,#dc2626);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">🔥 Philippines Trending</span>
                &nbsp;·&nbsp;
                <span id="locationName">🇵🇭 Top Media</span>
            </span>
            <span class="stat-item">
                <span class="material-icons-round">article</span>
                <strong id="newsCount">0</strong>&nbsp;/&nbsp;<strong id="totalNews">100</strong>
            </span>
        </div>
        <div class="stats-right">
            <label>Show:</label>
            <select id="perPage" onchange="updatePerPage()">
                <option value="12">12</option>
                <option value="24">24</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
            <label>per page</label>
        </div>
    </div>

    <!-- ══ GRID ═══════════════════════════════════════════ -->
    <div id="loading" class="loading">
        <div class="loading-spinner-large"></div>
        <div class="loading-text">Loading fresh news…</div>
    </div>
    <div id="newsGrid" class="news-grid"></div>

</div><!-- /page-wrap -->

<!-- ══ ARTICLE MODAL ══════════════════════════════════ -->
<div id="articleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-header-title">
                <span class="material-icons-round">newspaper</span>
                <h2>Article Details</h2>
            </div>
            <span class="close" onclick="closeModal()">×</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
    </div>
</div>

<div class="page-footer">
    &copy; 2025 AI Generated News — Live Data &nbsp;·&nbsp; Purple Editorial Edition
</div>

<script>
'use strict';

let currentNews = [];
let currentCategory = 'Philippines Trending';
let currentLocation = 'Philippines';
let displayedCount = 12;
let currentFetchController = null;
let fetchTimeout = null;
let isTrendingActive = true;
let developingNewsArticles = [];
let showDevelopingInDashboard = false;
let currentMainArticle = null;

/* ── Dark mode ─────────────────────────────────── */
function toggleDark() {
    const h = document.documentElement;
    const d = h.dataset.theme === 'dark';
    h.dataset.theme = d ? 'light' : 'dark';
    localStorage.setItem('ms-theme', d ? 'light' : 'dark');
    const ico = document.getElementById('darkBtn')?.querySelector('.material-icons-round');
    if (ico) ico.textContent = d ? 'dark_mode' : 'light_mode';
}
(function () {
    const t = localStorage.getItem('ms-theme') || 'light';
    document.documentElement.dataset.theme = t;
    const ico = document.getElementById('darkBtn')?.querySelector('.material-icons-round');
    if (ico) ico.textContent = t === 'dark' ? 'light_mode' : 'dark_mode';
})();

/* ── Toast ─────────────────────────────────────── */
function showToast(message, type = 'info') {
    document.querySelectorAll('.ms-toast').forEach(t => t.remove());
    const colors = { success:'#059669', error:'#DC2626', warning:'#D97706', info:'#2563EB' };
    const icons  = { success:'check_circle', error:'error', warning:'warning', info:'info' };
    const toast = document.createElement('div');
    toast.className = 'ms-toast';
    toast.style.cssText = `position:fixed;top:72px;right:18px;background:${colors[type]};color:white;padding:13px 18px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.22);z-index:10000;display:flex;align-items:center;gap:10px;min-width:280px;max-width:480px;animation:slideInRight .25s ease-out;font-family:'Sora',sans-serif;font-size:13px;font-weight:500`;
    toast.innerHTML = `<span class="material-icons-round" style="font-size:18px!important">${icons[type]}</span><span style="flex:1">${message}</span><button onclick="this.parentElement.remove()" style="background:rgba(255,255,255,.2);border:none;color:white;width:22px;height:22px;border-radius:50%;cursor:pointer;font-size:14px;flex-shrink:0">×</button>`;
    document.body.appendChild(toast);
    setTimeout(() => toast?.remove(), 5000);
}

/* ── Helpers ───────────────────────────────────── */
function toggleLocationButtons(disable) {
    document.querySelectorAll('.location-tab').forEach(b => b.classList.toggle('disabled', disable));
}

function getCardStyle(category) {
    const map = {
        technology : { bg:'135deg,#1E1B4B,#4338CA', emoji:'💻' },
        business   : { bg:'135deg,#4A1942,#A21CAF', emoji:'💼' },
        sports     : { bg:'135deg,#0C4A6E,#0284C7', emoji:'⚽' },
        entertainment:{ bg:'135deg,#4A044E,#C026D3', emoji:'🎬' },
        science    : { bg:'135deg,#042F2E,#0F766E', emoji:'🔬' },
        health     : { bg:'135deg,#450A0A,#DC2626', emoji:'❤️' },
        politics   : { bg:'135deg,#1E3A5F,#1D4ED8', emoji:'🏛️' },
        weather    : { bg:'135deg,#1C3657,#0EA5E9', emoji:'🌤️' },
    };
    return map[category.toLowerCase()] || { bg:'135deg,#3B0764,#7C3AED', emoji:'📰' };
}

function truncateTitle(title, n = 40) {
    return title.length <= n ? title : title.slice(0, n) + '…';
}

function extractKeywords(title) {
    const pfx  = ['live updates:','breaking:','latest:','update:','news:','watch:','photos:','video:'];
    const stop = ['the','a','an','and','or','but','in','on','at','to','for','of','with','by','from','as','is','was','are','been','be','have','has','had','will','can','said','says','after','over','out','into'];
    let clean = title.toLowerCase();
    pfx.forEach(p => { clean = clean.replace(p, ''); });
    const words = clean.replace(/[^\w\s-]/g,' ').split(/\s+/).filter(w => w.length > 2 && !stop.includes(w));
    const proper = title.split(/\s+/).filter(w => w.length > 2 && w[0] === w[0].toUpperCase() && !pfx.some(p => w.toLowerCase().includes(p)));
    return proper.length > 0 ? proper.slice(0, 4).join(' ') : words.slice(0, 4).join(' ');
}

/* ── Stats / Pagination ────────────────────────── */
function updateStats() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const timeStr = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    document.getElementById('currentDate').textContent = dateStr;
    document.getElementById('loadTime').textContent = timeStr;
    const el = document.getElementById('loadTimeInline');
    if (el) el.textContent = timeStr;
    const actual = Math.min(displayedCount, currentNews.length);
    document.getElementById('newsCount').textContent = actual;
    document.getElementById('totalNews').textContent = currentNews.length;
    document.getElementById('importCount').textContent = `(${actual})`;
    updatePaginationOptions(currentNews.length);
}

function updatePaginationOptions(total) {
    const sel = document.getElementById('perPage');
    const opts = [12, 24, 50, 100];
    sel.innerHTML = '';
    opts.forEach(v => {
        if (v <= total || v === opts[0]) {
            const o = document.createElement('option');
            o.value = v; o.textContent = v; if (v === displayedCount) o.selected = true;
            sel.appendChild(o);
        }
    });
    if (total > 12) {
        const o = document.createElement('option');
        o.value = total; o.textContent = `All (${total})`; if (displayedCount >= total) o.selected = true;
        sel.appendChild(o);
    }
    if (displayedCount > total) displayedCount = total;
}

function updatePerPage() {
    displayedCount = parseInt(document.getElementById('perPage').value);
    isTrendingActive ? displayTrendingNews(currentNews.slice(0, displayedCount)) : displayNews(currentNews.slice(0, displayedCount));
    updateStats();
}

/* ── Location / Category ───────────────────────── */
function selectLocation(location, button) {
    if (isTrendingActive || button.classList.contains('disabled')) return;
    document.querySelectorAll('.location-tab').forEach(b => b.classList.remove('active'));
    button.classList.add('active');
    currentLocation = location;
    refreshFilterPill();
    if (currentFetchController) currentFetchController.abort();
    fetchNews(buildQuery());
}

function buildQuery() {
    const kw = {
        politics:'politics election government', weather:'weather forecast typhoon storm',
        technology:'technology tech AI software', business:'business economy finance',
        sports:'sports game championship', entertainment:'entertainment celebrity movie',
        science:'science research discovery', health:'health medical healthcare',
        'world news':'world news international', 'latest news':'latest breaking news'
    };
    let q = kw[currentCategory.toLowerCase()] || currentCategory;
    if (currentLocation !== 'worldwide') q += ' ' + currentLocation;
    return q;
}

function selectCategory(category, button) {
    isTrendingActive = false;
    toggleLocationButtons(false);
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    button.classList.add('active');
    currentCategory = category;
    document.getElementById('searchInput').value = '';
    refreshFilterPill();
    if (currentFetchController) currentFetchController.abort();
    fetchNews(buildQuery());
}

function refreshFilterPill() {
    const locBtn = document.querySelector('.location-tab.active');
    const locTxt = locBtn ? locBtn.textContent.trim() : '🇵🇭 Philippines';
    const catTxt = currentCategory.charAt(0).toUpperCase() + currentCategory.slice(1);
    const el = document.getElementById('filterDisplay');
    if (el) el.innerHTML = `<span class="material-icons-round" style="font-size:12px!important">folder_open</span><span id="categoryName">${catTxt}</span>&nbsp;·&nbsp;<span id="locationName">${locTxt}</span>`;
}

async function selectPhilippinesTrending(button) {
    isTrendingActive = true;
    toggleLocationButtons(true);
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.remove('active'));
    button.classList.add('active');
    currentCategory = 'Philippines Trending';
    const el = document.getElementById('filterDisplay');
    if (el) el.innerHTML = `<span class="material-icons-round" style="font-size:12px!important">whatshot</span><span id="categoryName" style="background:linear-gradient(90deg,#ef4444,#dc2626);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;font-weight:700">🔥 Philippines Trending</span>&nbsp;·&nbsp;<span id="locationName">🇵🇭 Top Media</span>`;

    const loading = document.getElementById('loading');
    const newsGrid = document.getElementById('newsGrid');
    loading.style.display = 'block';
    loading.innerHTML = `<div class="loading-spinner-large"></div><div class="loading-text">Analysing trending news from GMA, Inquirer, Philstar, Rappler…</div>`;
    newsGrid.innerHTML = '';

    try {
        const r = await fetch('trending_philippines.php');
        const data = await r.json();
        if (data.success && data.trendingTopics.length > 0) {
            currentNews = data.trendingTopics;
            displayTrendingNews(currentNews.slice(0, displayedCount));
            loading.style.display = 'none';
            updateStats();
            showToast(`Found ${data.trendingTopics.length} trending topics from ${data.totalArticles} articles`, 'success');
        } else throw new Error(data.message || 'No trending topics found');
    } catch (err) {
        loading.innerHTML = `<div style="text-align:center;padding:48px"><div style="font-size:2.2em;margin-bottom:14px">⚠️</div><div style="font-size:14px;color:var(--danger);font-weight:700;font-family:'Fira Code',monospace">Failed to load trending news</div><div style="font-size:12px;color:var(--ink-faint);margin-top:8px">${err.message}</div></div>`;
        showToast('Failed to load trending news: ' + err.message, 'error');
    }
}

/* ── Card badge builders ───────────────────────── */
function trendBadgeHTML(count) {
    if (count >= 4) return `<span class="card-badge badge-hot"><span style="margin-right:3px">🔥</span>HOT</span>`;
    if (count >= 3) return `<span class="card-badge badge-trending">📈 TRENDING</span>`;
    return `<span class="card-badge badge-rising">📰 RISING</span>`;
}

function devOverlayHTML(article) {
    if (!article.isDevelopingNews) return { top:'', bottom:'' };
    return {
        top: '<div class="developing-news-badge">🔗 Related</div>',
        bottom: `<div class="source-reference-badge"><span class="source-icon">👈</span><span class="source-text">From: ${truncateTitle(article.mainArticleTitle || 'Main Article')}</span></div>`
    };
}

/* ── Display: Trending grid ────────────────────── */
function displayTrendingNews(articles) {
    const grid = document.getElementById('newsGrid');
    grid.innerHTML = '';
    articles.forEach((article, i) => {
        const card = document.createElement('div');
        card.className = 'news-card';
        card.onclick = () => viewArticleByIndex(i);
        const pub = new Date(article.pubDate);
        const fDate = pub.toLocaleDateString('en-US', { month:'short', day:'numeric' });
        const fTime = pub.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
        const { top, bottom } = devOverlayHTML(article);
        card.innerHTML = `
            <div class="news-card-image" id="img-${i}" style="background:linear-gradient(135deg,#7F1D1D,#DC2626)" data-index="${i}">
                ${top}${bottom}
                <span class="image-placeholder">🔥</span>
                <div class="loading-spinner"></div>
            </div>
            <div class="news-card-content">
                <div class="card-badges">
                    ${trendBadgeHTML(article.sourceCount)}
                    <span class="card-badge badge-sources">${article.sourceCount || 1} sources</span>
                </div>
                <h2 class="news-title">${article.title}</h2>
                <div class="news-meta">
                    <div class="news-meta-row"><span class="material-icons-round">newspaper</span>${article.sources || article.author}</div>
                    <div class="news-meta-row"><span class="material-icons-round">calendar_today</span>${fDate}&nbsp;·&nbsp;<span class="material-icons-round" style="margin-left:4px">schedule</span>${fTime}</div>
                </div>
            </div>`;
        grid.appendChild(card);
    });
    setupInstantImageLoading();
}

/* ── Display: Regular grid ─────────────────────── */
function displayNews(articles) {
    const grid = document.getElementById('newsGrid');
    grid.innerHTML = '';
    articles.forEach((article, i) => {
        const card = document.createElement('div');
        card.className = 'news-card';
        card.onclick = () => viewArticleByIndex(i);
        const pub = new Date(article.pubDate);
        const fDate = pub.toLocaleDateString('en-US', { month:'short', day:'numeric' });
        const fTime = pub.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
        const { bg, emoji } = getCardStyle(currentCategory);
        const { top, bottom } = devOverlayHTML(article);
        card.innerHTML = `
            <div class="news-card-image" id="img-${i}" style="background:linear-gradient(${bg})" data-index="${i}">
                ${top}${bottom}
                <span class="image-placeholder">${emoji}</span>
                <div class="loading-spinner"></div>
            </div>
            <div class="news-card-content">
                <h2 class="news-title">${article.title}</h2>
                <div class="news-meta">
                    <div class="news-meta-row"><span class="material-icons-round">newspaper</span>${article.author}</div>
                    <div class="news-meta-row"><span class="material-icons-round">calendar_today</span>${fDate}&nbsp;·&nbsp;<span class="material-icons-round" style="margin-left:4px">schedule</span>${fTime}</div>
                </div>
            </div>`;
        grid.appendChild(card);
    });
    setupInstantImageLoading();
}

/* ── Image loading ─────────────────────────────── */
function setupInstantImageLoading() {
    const containers = document.querySelectorAll('.news-card-image');
    Promise.allSettled(Array.from(containers).map(c => loadImageForArticle(parseInt(c.dataset.index))));
}

async function loadImageForArticle(index) {
    const article = currentNews[index];
    const box = document.getElementById(`img-${index}`);
    const spinner = box?.querySelector('.loading-spinner');
    const { emoji } = getCardStyle(currentCategory);
    if (!box || !article) return;

    function restoreBadges(box) {
        const d = box.querySelector('.developing-news-badge');
        const s = box.querySelector('.source-reference-badge');
        return { d: d?.cloneNode(true), s: s?.cloneNode(true) };
    }
    function setBadges(box, d, s) {
        if (d) box.appendChild(d);
        if (s) box.appendChild(s);
    }

    try {
        let imageUrl = null;
        if (article.imageUrl?.trim()) { imageUrl = article.imageUrl; }
        else if (currentCategory === 'Philippines Trending') {
            try {
                const ctrl = new AbortController();
                const tid = setTimeout(() => ctrl.abort(), 5000);
                const r = await fetch(`rss_images.php?action=get-rss-images&query=${encodeURIComponent(article.title + ' ' + (article.source||'') + ' Philippines')}&limit=3`, { signal: ctrl.signal });
                clearTimeout(tid);
                if (r.ok) {
                    const d = await r.json();
                    if (d.success && d.articles?.length) { for (const a of d.articles) { if (a.imageUrl) { imageUrl = a.imageUrl; break; } } }
                }
            } catch(e) {}
        }
        if (!imageUrl) {
            const ctrl = new AbortController();
            const tid = setTimeout(() => ctrl.abort(), 5000);
            const r = await fetch(`image_search.php?action=search-image&title=${encodeURIComponent(article.source ? article.title + ' ' + article.source + ' Philippines news' : article.title)}`, { signal: ctrl.signal });
            clearTimeout(tid);
            if (r.ok) { const d = await r.json(); if (d.success && d.imageUrl) imageUrl = d.imageUrl; }
        }

        if (spinner) spinner.remove();
        const { d, s } = restoreBadges(box);

        if (imageUrl) {
            const img = document.createElement('img');
            img.src = imageUrl;
            img.onerror = () => { box.innerHTML = ''; setBadges(box,d,s); box.innerHTML += `<span class="image-placeholder">${emoji}</span>`; };
            img.onload = () => box.classList.add('has-image');
            box.innerHTML = ''; setBadges(box, d, s); box.appendChild(img);
        } else {
            box.innerHTML = ''; setBadges(box, d, s); box.innerHTML += `<span class="image-placeholder">${emoji}</span>`;
        }
    } catch (err) {
        if (spinner) spinner.remove();
        const { d, s } = restoreBadges(box);
        if (box) { box.innerHTML = ''; setBadges(box, d, s); box.innerHTML += `<span class="image-placeholder">${emoji}</span>`; }
    }
}

/* ── RSS Fetch ─────────────────────────────────── */
async function fetchNews(query = 'latest news') {
    const loading = document.getElementById('loading');
    const grid = document.getElementById('newsGrid');
    if (currentFetchController) currentFetchController.abort();
    if (fetchTimeout) clearTimeout(fetchTimeout);
    loading.style.display = 'block';
    loading.innerHTML = `<div class="loading-spinner-large"></div><div class="loading-text">Loading fresh news…</div>`;
    grid.innerHTML = '';
    currentFetchController = new AbortController();
    const { signal } = currentFetchController;
    try {
        fetchTimeout = setTimeout(() => { if (!signal.aborted) currentFetchController.abort(); }, 15000);
        const r = await fetch(`rss_proxy.php?q=${encodeURIComponent(query)}`, { signal, headers:{ 'Accept':'application/xml,text/xml,*/*' } });
        clearTimeout(fetchTimeout);
        if (!r.ok) throw new Error(`HTTP ${r.status}: ${r.statusText}`);
        const xml = await r.text();
        const articles = parseXMLFeed(xml);
        if (articles.length > 0) {
            currentNews = articles.sort((a,b) => new Date(b.pubDate) - new Date(a.pubDate));
            displayNews(currentNews.slice(0, displayedCount));
            loading.style.display = 'none';
            updateStats();
            return;
        }
        throw new Error('No articles found');
    } catch (err) {
        if (err.name !== 'AbortError') {
            loading.innerHTML = `<div class="loading-spinner-large"></div><div class="loading-text">Feed unavailable&nbsp;·&nbsp;<span style="color:var(--purple)">Loading sample data…</span></div>`;
            setTimeout(() => loadTestData(), 1000);
        }
    }
}

function parseXMLFeed(xml) {
    const doc = new DOMParser().parseFromString(xml, 'text/xml');
    if (doc.querySelector('parsererror')) throw new Error('Invalid XML');
    const articles = [];
    doc.querySelectorAll('item').forEach(item => {
        let title = item.querySelector('title')?.textContent || '';
        let link  = item.querySelector('link')?.textContent || '#';
        const pubDate = item.querySelector('pubDate')?.textContent || '';
        if (!title || !link) return;
        let source = 'Unknown';
        const dash = title.lastIndexOf(' - ');
        if (dash > 0) { source = title.slice(dash + 3).trim(); title = title.slice(0, dash).trim(); }
        articles.push({ title, link, pubDate, author: source });
    });
    return articles;
}

/* ── Search ────────────────────────────────────── */
function searchNews() {
    const q = document.getElementById('searchInput').value.trim() || 'latest news';
    isTrendingActive = false;
    toggleLocationButtons(false);
    document.querySelectorAll('.cat-tab, .location-tab').forEach(b => b.classList.remove('active'));
    currentCategory = q; currentLocation = 'worldwide';
    const el = document.getElementById('filterDisplay');
    if (el) el.innerHTML = `<span class="material-icons-round" style="font-size:12px!important">search</span><span id="categoryName">Search: ${q}</span>&nbsp;·&nbsp;<span id="locationName">🌐 Worldwide</span>`;
    if (currentFetchController) currentFetchController.abort();
    fetchNews(q);
}

/* ── Developing news toggle ────────────────────── */
function toggleDevelopingNewsInDashboard() {
    showDevelopingInDashboard = document.getElementById('showDevelopingCheckbox').checked;
    if (showDevelopingInDashboard) {
        addDevelopingNewsToDashboard();
        showToast(`Added ${developingNewsArticles.length} related articles to dashboard`, 'success');
    } else {
        removeDevelopingNewsFromDashboard();
        showToast('Removed related articles from dashboard', 'info');
    }
}

function addDevelopingNewsToDashboard() {
    if (!developingNewsArticles.length) return;
    const marked = developingNewsArticles.map(a => ({ ...a, isDevelopingNews:true, mainArticleTitle: currentMainArticle?.title||'Unknown', mainArticleIndex: currentMainArticle?.originalIndex||-1 }));
    currentNews = currentNews.filter(a => !a.isDevelopingNews);
    currentNews = [...marked, ...currentNews];
    isTrendingActive ? displayTrendingNews(currentNews.slice(0,displayedCount)) : displayNews(currentNews.slice(0,displayedCount));
    updateStats(); setupInstantImageLoading();
}

function removeDevelopingNewsFromDashboard() {
    currentNews = currentNews.filter(a => !a.isDevelopingNews);
    isTrendingActive ? displayTrendingNews(currentNews.slice(0,displayedCount)) : displayNews(currentNews.slice(0,displayedCount));
    updateStats(); setupInstantImageLoading();
}

/* ── Article view routing ──────────────────────── */
function viewArticleByIndex(index) {
    const a = currentNews[index];
    if (a.isDevelopingNews) viewSimpleDevelopingArticle(index);
    else if (a.relatedArticles?.length) viewTrendingArticle(index);
    else viewArticleWithDevelopingNews(index);
}

/* ── Fetch related articles ────────────────────── */
async function fetchDevelopingNews(mainArticle) {
    try {
        const ctrl = new AbortController();
        const tid = setTimeout(() => ctrl.abort(), 8000);
        const r = await fetch(`rss_proxy.php?q=${encodeURIComponent(extractKeywords(mainArticle.title))}`, { signal: ctrl.signal });
        clearTimeout(tid);
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        const articles = parseXMLFeed(await r.text());
        return articles.filter(a => a.title !== mainArticle.title).slice(0, 5);
    } catch(e) { return []; }
}

/* ── Modal: simple developing article ─────────── */
function viewSimpleDevelopingArticle(index) {
    const a = currentNews[index];
    const pub = new Date(a.pubDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    const imgSrc = document.getElementById(`img-${index}`)?.querySelector('img')?.src;
    document.getElementById('modalBody').innerHTML = `
        <div class="main-article">
            ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${a.title}">` : ''}
            <div style="display:flex;gap:8px;margin-bottom:14px">
                <span style="background:var(--purple);color:white;padding:6px 14px;border-radius:var(--r-xs);font-weight:700;font-size:11px;font-family:'Fira Code',monospace">🔗 RELATED ARTICLE</span>
            </div>
            <h2 class="main-article-title">${a.title}</h2>
            <div class="main-article-meta"><strong>Source:</strong> ${a.author||'Unknown'}<br><strong>Published:</strong> ${pub}</div>
            <div class="article-actions">
                <a href="${a.link}" target="_blank" class="read-more-btn"><span class="material-icons-round">open_in_new</span>Read</a>
                <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(a).replace(/'/g,"&apos;")})'>
                    <span class="material-icons-round">save_alt</span>Import
                </button>
            </div>
        </div>
        <div style="padding:18px;background:var(--canvas);border-radius:var(--r-sm);border:1px solid var(--border);border-left:3px solid var(--purple)">
            <p style="font-size:12px;color:var(--ink-muted);line-height:1.7;font-family:'Fira Code',monospace">💡 This article was added from the Developing News section. Uncheck "Show in Dashboard" in the original article to remove it.</p>
        </div>`;
    document.getElementById('articleModal').style.display = 'block';
}

/* ── Modal: trending article ───────────────────── */
function viewTrendingArticle(index) {
    const a = currentNews[index];
    currentMainArticle = { title:a.title, originalIndex:index, link:a.link, author:a.sources||a.author };
    developingNewsArticles = a.relatedArticles || [];
    const pub = new Date(a.pubDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    const imgSrc = document.getElementById(`img-${index}`)?.querySelector('img')?.src;
    const seen = new Set();
    const srcLinks = a.relatedArticles.reduce((acc, r) => {
        const k = r.source.toLowerCase().trim();
        if (!seen.has(k)) { seen.add(k); acc += `<a href="${r.link}" target="_blank" class="main-source-link" onclick="event.stopPropagation()">${r.source}</a> `; }
        return acc;
    }, '');
    document.getElementById('modalBody').innerHTML = `
        <div class="main-article">
            ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${a.title}">` : ''}
            <div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
                <span style="background:linear-gradient(135deg,#EF4444,#DC2626);color:white;padding:6px 14px;border-radius:var(--r-xs);font-weight:700;font-size:11px;font-family:'Fira Code',monospace">🔥 TRENDING</span>
                <span class="card-badge badge-sources" style="padding:6px 14px;font-size:11px">${a.sourceCount} media outlets</span>
            </div>
            <h2 class="main-article-title">${a.title}</h2>
            <div class="main-article-meta"><strong>Sources:</strong> ${srcLinks}<br><strong>Published:</strong> ${pub}</div>
            <div class="article-actions">
                <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(a).replace(/'/g,"&apos;")})'>
                    <span class="material-icons-round">save_alt</span>Import Article
                </button>
            </div>
        </div>
        <div class="developing-news-section">
            <div class="developing-news-header">
                <h3><span class="material-icons-round" style="color:#DC2626;vertical-align:middle">newspaper</span> Coverage from Different Sources <span class="developing-badge">${a.sourceCount} SOURCES</span></h3>
                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;color:var(--ink);font-weight:600">
                    <input type="checkbox" id="showDevelopingCheckbox" onchange="toggleDevelopingNewsInDashboard()" ${showDevelopingInDashboard?'checked':''} style="width:16px;height:16px;cursor:pointer;accent-color:var(--purple)">
                    Show in Dashboard
                </label>
            </div>
            <div class="related-articles-grid">
                ${a.relatedArticles.map(r => {
                    const rd = new Date(r.pubDate);
                    const fd = rd.toLocaleDateString('en-US',{month:'short',day:'numeric'});
                    const ft = rd.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
                    return `<div class="related-article">
                        <h4 class="related-article-title">${r.title}</h4>
                        <div class="related-article-meta">
                            <a href="${r.link}" target="_blank" class="source-link" onclick="event.stopPropagation()"><span class="material-icons-round" style="font-size:12px!important">newspaper</span>${r.source}</a>
                            <span><span class="material-icons-round" style="font-size:11px!important">calendar_today</span>${fd}</span>
                            <span><span class="material-icons-round" style="font-size:11px!important">schedule</span>${ft}</span>
                        </div>
                        <div class="related-article-actions">
                            <button class="related-import-btn"
                                data-title="${r.title.replace(/"/g,'&quot;').replace(/'/g,'&#39;')}"
                                data-link="${r.link}" data-author="${r.source||''}" data-pubdate="${r.pubDate}"
                                onclick="event.stopPropagation();importFromButton(this)">
                                <span class="material-icons-round">save_alt</span>Import
                            </button>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
    document.getElementById('articleModal').style.display = 'block';
    if (showDevelopingInDashboard) addDevelopingNewsToDashboard();
}

/* ── Modal: regular article with developing news ─ */
async function viewArticleWithDevelopingNews(index) {
    const a = currentNews[index];
    currentMainArticle = { title:a.title, originalIndex:index, link:a.link, author:a.author };
    const pub = new Date(a.pubDate).toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric', hour:'2-digit', minute:'2-digit' });
    const imgSrc = document.getElementById(`img-${index}`)?.querySelector('img')?.src;

    document.getElementById('modalBody').innerHTML = `
        <div class="main-article">
            ${imgSrc ? `<img src="${imgSrc}" class="main-article-image" alt="${a.title}">` : ''}
            <h2 class="main-article-title">${a.title}</h2>
            <div class="main-article-meta"><strong>Source:</strong> ${a.author||'Unknown'}<br><strong>Published:</strong> ${pub}</div>
            <div class="article-actions">
                <a href="${a.link}" target="_blank" class="read-more-btn"><span class="material-icons-round">open_in_new</span>Read</a>
                <button class="import-article-btn" onclick='importSingleArticle(${JSON.stringify(a).replace(/'/g,"&apos;")})'>
                    <span class="material-icons-round">save_alt</span>Import
                </button>
            </div>
        </div>
        <div class="developing-news-section">
            <div class="developing-news-header">
                <h3><span class="material-icons-round" style="color:#DC2626;vertical-align:middle">radio_button_checked</span> Developing News <span class="developing-badge">LIVE</span></h3>
                <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12px;color:var(--ink);font-weight:600">
                    <input type="checkbox" id="showDevelopingCheckbox" onchange="toggleDevelopingNewsInDashboard()" ${showDevelopingInDashboard?'checked':''} style="width:15px;height:15px;cursor:pointer;accent-color:var(--purple)">
                    Show in Dashboard
                </label>
            </div>
            <div class="developing-news-loading"><div class="loading-spinner-large" style="width:36px;height:36px"></div><div style="margin-top:12px">Loading related articles…</div></div>
        </div>`;
    document.getElementById('articleModal').style.display = 'block';

    const related = await fetchDevelopingNews(a);
    developingNewsArticles = related;
    const devSection = document.querySelector('.developing-news-section');
    const hdrHTML = `
        <div class="developing-news-header">
            <h3><span class="material-icons-round" style="color:#DC2626;vertical-align:middle">radio_button_checked</span> Developing News <span class="developing-badge">LIVE</span></h3>
            <label style="display:flex;align-items:center;gap:7px;cursor:pointer;font-size:12px;color:var(--ink);font-weight:600">
                <input type="checkbox" id="showDevelopingCheckbox" onchange="toggleDevelopingNewsInDashboard()" ${showDevelopingInDashboard?'checked':''} style="width:15px;height:15px;cursor:pointer;accent-color:var(--purple)">
                Show in Dashboard
            </label>
        </div>`;
    if (related.length > 0) {
        devSection.innerHTML = hdrHTML + '<div class="related-articles-grid">' + related.map(r => {
            const rd = new Date(r.pubDate);
            const fd = rd.toLocaleDateString('en-US',{month:'short',day:'numeric'});
            const ft = rd.toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'});
            return `<div class="related-article">
                <h4 class="related-article-title">${r.title}</h4>
                <div class="related-article-meta">
                    <span><span class="material-icons-round" style="font-size:11px!important">newspaper</span>${r.author}</span>
                    <span><span class="material-icons-round" style="font-size:11px!important">calendar_today</span>${fd}</span>
                    <span><span class="material-icons-round" style="font-size:11px!important">schedule</span>${ft}</span>
                </div>
                <div class="related-article-actions">
                    <button class="related-read-btn" onclick="event.stopPropagation();window.open('${r.link}','_blank')">
                        <span class="material-icons-round">open_in_new</span>Read
                    </button>
                    <button class="related-import-btn"
                        data-title="${r.title.replace(/"/g,'&quot;').replace(/'/g,'&#39;')}"
                        data-link="${r.link}" data-author="${r.author||''}" data-pubdate="${r.pubDate}"
                        onclick="event.stopPropagation();importFromButton(this)">
                        <span class="material-icons-round">save_alt</span>Import
                    </button>
                </div>
            </div>`;
        }).join('') + '</div>';
        if (showDevelopingInDashboard) addDevelopingNewsToDashboard();
    } else {
        devSection.innerHTML = hdrHTML + '<div class="no-related">No related articles found at this time.</div>';
    }
}

/* ── Import helpers ────────────────────────────── */
function saveArticle(article, btn) {
    if (!article || !btn) return;
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>Importing…';
    btn.style.opacity = '0.7';
    fetch('save.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ title:article.title, content:article.description||article.title, category:currentCategory, source:article.author||article.source||'Unknown', author:article.author||article.source||'', published_at:article.pubDate, url:article.link, image:'', description:article.description||'' })
    })
    .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
    .then(data => {
        if (data.success) {
            showToast(data.message || 'Article imported!', 'success');
            btn.innerHTML = '<span class="material-icons-round">check_circle</span>Imported';
            btn.style.background = '#059669'; btn.style.color = 'white';
            btn.style.opacity = '1'; btn.disabled = true; btn.style.cursor = 'not-allowed';
        } else if (data.message?.includes('already exists')) {
            showToast('Already imported', 'warning');
            btn.innerHTML = '<span class="material-icons-round">info</span>Already Imported';
            btn.style.background = '#D97706'; btn.style.color = 'white';
            btn.style.opacity = '1'; btn.disabled = true;
        } else {
            showToast(data.message || 'Failed to import', 'error');
            btn.disabled = false; btn.innerHTML = orig; btn.style.opacity = '1';
        }
    })
    .catch(err => {
        showToast('Failed: ' + err.message, 'error');
        btn.disabled = false; btn.innerHTML = orig; btn.style.opacity = '1';
    });
}

function importSingleArticle(article) {
    const tmp = document.createElement('button');
    tmp.style.display = 'none';
    document.body.appendChild(tmp);
    saveArticle(article, tmp);
    setTimeout(() => tmp.parentNode?.removeChild(tmp), 200);
}

function importFromButton(btn) {
    saveArticle({ title:btn.dataset.title, link:btn.dataset.link, author:btn.dataset.author, pubDate:btn.dataset.pubdate, description:'' }, btn);
}

function saveAllVisibleArticles() {
    const articles = currentNews.slice(0, displayedCount);
    if (!articles.length) { showToast('No articles to import', 'warning'); return; }
    if (!confirm(`Import all ${articles.length} visible articles?`)) return;
    let saved = 0, failed = 0, exists = 0;
    const btns = document.querySelectorAll('.import-article-btn,.related-import-btn');
    btns.forEach(b => { b.disabled = true; b.style.opacity = '0.5'; });
    showToast(`Starting import of ${articles.length} articles…`, 'info');
    articles.forEach((a, i) => {
        setTimeout(() => {
            fetch('save.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ title:a.title, content:a.description||a.title, category:currentCategory, source:a.author||'Unknown', author:a.author||'', published_at:a.pubDate, url:a.link, description:a.description||'' }) })
            .then(r => r.json())
            .then(d => {
                if (d.success) saved++;
                else if (d.message?.includes('already exists')) exists++;
                else failed++;
                if (saved + failed + exists === articles.length) {
                    let msg = '';
                    if (saved) msg += `✓ ${saved} imported`;
                    if (exists) msg += `${msg?', ':''}${exists} existed`;
                    if (failed) msg += `${msg?', ':''}${failed} failed`;
                    showToast(msg, failed === 0 ? 'success' : saved > 0 ? 'warning' : 'error');
                    btns.forEach(b => { if (!b.innerHTML.includes('Imported') && !b.innerHTML.includes('Already')) { b.disabled = false; b.style.opacity = '1'; } });
                }
            })
            .catch(() => {
                failed++;
                if (saved + failed + exists === articles.length) {
                    showToast(`${failed} failed`, 'error');
                    btns.forEach(b => { b.disabled = false; b.style.opacity = '1'; });
                }
            });
        }, i * 300);
    });
}

/* ── Test data ─────────────────────────────────── */
function loadTestData() {
    isTrendingActive = false; toggleLocationButtons(false);
    const arts = [
        { title:"Philippines Economy Shows Strong Growth in Q4 2025", link:"https://example.com/economy", pubDate:new Date().toUTCString(), author:"Philippine Daily Inquirer" },
        { title:"New AI Technology Revolutionizes Healthcare in Manila", link:"https://example.com/ai-health", pubDate:new Date(Date.now()-3600000).toUTCString(), author:"TechCrunch Philippines" },
        { title:"Typhoon Pepito Weakens, Moving Away from Luzon", link:"https://example.com/typhoon", pubDate:new Date(Date.now()-7200000).toUTCString(), author:"ABS-CBN News" },
        { title:"Philippine Basketball Team Wins Regional Championship", link:"https://example.com/bball", pubDate:new Date(Date.now()-10800000).toUTCString(), author:"Rappler Sports" },
        { title:"New Infrastructure Projects Announced for Metro Manila", link:"https://example.com/infra", pubDate:new Date(Date.now()-14400000).toUTCString(), author:"Business World" },
        { title:"Local Scientists Discover New Marine Species in Palawan", link:"https://example.com/marine", pubDate:new Date(Date.now()-18000000).toUTCString(), author:"Science Daily PH" },
        { title:"Filipino Startup Raises $10M in Series A Funding", link:"https://example.com/startup", pubDate:new Date(Date.now()-21600000).toUTCString(), author:"Tech in Asia" },
        { title:"Department of Health Launches New Vaccination Program", link:"https://example.com/vaccine", pubDate:new Date(Date.now()-25200000).toUTCString(), author:"Manila Bulletin" },
        { title:"Cebu Tourism Industry Reports Record Numbers", link:"https://example.com/tourism", pubDate:new Date(Date.now()-28800000).toUTCString(), author:"SunStar Cebu" },
        { title:"New Film Festival Showcases Filipino Cinema Excellence", link:"https://example.com/film", pubDate:new Date(Date.now()-32400000).toUTCString(), author:"Variety Asia" },
        { title:"Philippine Stock Exchange Reaches All-Time High", link:"https://example.com/pse", pubDate:new Date(Date.now()-36000000).toUTCString(), author:"Bloomberg Philippines" },
        { title:"Innovative Education Platform Launches in Mindanao", link:"https://example.com/edu", pubDate:new Date(Date.now()-39600000).toUTCString(), author:"EdTech Magazine" },
    ];
    const loading = document.getElementById('loading');
    loading.innerHTML = `<div style="background:var(--surface);border:1px solid var(--border);border-left:3px solid var(--success);border-radius:var(--r);padding:16px 20px;max-width:640px;margin:0 auto 24px;display:flex;align-items:center;gap:14px;box-shadow:var(--sh-md)">
        <span class="material-icons-round" style="color:var(--success);font-size:24px!important">check_circle</span>
        <div style="flex:1"><div style="font-size:13px;font-weight:700;color:var(--ink);font-family:'Sora',sans-serif">Test Data Loaded</div><div style="font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px">12 sample articles · RSS feed temporarily unavailable</div></div>
        <button onclick="this.closest('[style]').remove()" style="background:none;border:none;cursor:pointer;color:var(--ink-faint);line-height:1;font-size:18px">×</button></div>`;
    loading.style.display = 'block';
    currentNews = arts;
    displayNews(currentNews.slice(0, displayedCount));
    updateStats();
    setTimeout(() => document.getElementById('newsGrid')?.scrollIntoView({ behavior:'smooth', block:'start' }), 300);
}

/* ── Modal close ───────────────────────────────── */
function closeModal() { document.getElementById('articleModal').style.display = 'none'; }
window.onclick = e => { if (e.target === document.getElementById('articleModal')) closeModal(); };
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

/* ── Nav helpers ───────────────────────────────── */
function importAllNews() { saveAllVisibleArticles(); }
function showDashboard() { window.location.href = 'https://mbcnewsmedia.mbcradio.net/user/user_dashboard.php'; }
function refreshNews() {
    if (isTrendingActive) { const b = document.querySelector('.cat-tab.trending-btn'); if(b) selectPhilippinesTrending(b); }
    else fetchNews(buildQuery());
}

/* ── Search on Enter ───────────────────────────── */
document.getElementById('searchInput').addEventListener('keypress', e => { if (e.key === 'Enter') searchNews(); });

/* ── Init ──────────────────────────────────────── */
(function init() {
    const now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    document.getElementById('loadTime').textContent = now.toLocaleTimeString('en-US', { hour:'2-digit', minute:'2-digit' });
    const el = document.getElementById('loadTimeInline');
    if (el) el.textContent = document.getElementById('loadTime').textContent;
})();

window.onload = () => {
    const btn = document.querySelector('.cat-tab.trending-btn');
    if (btn) selectPhilippinesTrending(btn).catch(() => loadTestData());
};
</script>
</body>
</html>