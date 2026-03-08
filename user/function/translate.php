<?php
/**
 * translate.php
 * Translates a news article into a selected Filipino dialect using OpenAI ChatGPT API.
 * Usage: function/translate.php?id=ARTICLE_ID
 *
 * Improvements v2:
 *  - Unified title+body translation in one API call (consistent terminology)
 *  - Upgraded to gpt-4o for better minor-dialect accuracy
 *  - response_format: json_object to prevent malformed output
 *  - Temperature lowered to 0.1 (less hallucination, more literal)
 *  - HTML stripped + cleaned before sending to API
 *  - Richer, journalist-persona dialect prompts
 */

session_start();
require dirname(__DIR__, 2) . '/db.php';

// ─── CONFIG ────────────────────────────────────────────────────────────────
$_env = parse_ini_file(dirname(__DIR__, 2) . '/.env') ?: [];
define('OPENAI_API_KEY', $_env['OPENAI_API_KEY'] ?? '');
define('OPENAI_MODEL',   'gpt-4o'); // Upgraded from gpt-4o-mini for better minor-dialect accuracy

// ─── SUPPORTED FILIPINO DIALECTS ───────────────────────────────────────────
const DIALECTS = [
    'tagalog' => [
        'label'    => 'Tagalog',
        'native'   => 'Filipino',
        'region'   => 'NCR, CALABARZON, MIMAROPA',
        'speakers' => '28M',
        'flag'     => '🏙️',
        'color'    => '#2563eb',
        'prompt'   => 'You are an expert Filipino journalist and translator based in Metro Manila. Translate the provided news article (title and body) into natural, journalistic Filipino/Tagalog as spoken in Metro Manila and surrounding regions. Use standard Tagalog vocabulary appropriate for a national broadsheet. Preserve all proper nouns, organization names, place names, and technical terms in their original form. Keep numbers, dates, and statistics exactly as written. Maintain a formal, factual news tone throughout.',
    ],
    'cebuano' => [
        'label'    => 'Cebuano',
        'native'   => 'Bisaya',
        'region'   => 'Cebu, Davao, Visayas',
        'speakers' => '21M',
        'flag'     => '🌊',
        'color'    => '#0891b2',
        'prompt'   => 'You are an expert Cebuano journalist and translator from Cebu City. Translate the provided news article (title and body) into authentic Cebuano (Bisaya) as spoken in Cebu, Davao, and the Visayas. Use natural Cebuano expressions appropriate for a regional newspaper. Preserve all proper nouns, organization names, place names, and technical terms in their original form. Avoid unnecessary code-switching to Tagalog. Maintain a formal news tone.',
    ],
    'ilocano' => [
        'label'    => 'Ilocano',
        'native'   => 'Ilokano',
        'region'   => 'Ilocos Region, CAR',
        'speakers' => '9M',
        'flag'     => '⛰️',
        'color'    => '#059669',
        'prompt'   => 'You are an expert Ilocano journalist and translator from the Ilocos Region. Translate the provided news article (title and body) into authentic Ilocano (Ilokano) as spoken in Ilocos Norte, Ilocos Sur, La Union, and the Cordillera. Use vocabulary natural to a local Ilocano broadsheet. Preserve proper nouns, names, organization names, and technical terms untranslated. Maintain journalistic clarity and formality.',
    ],
    'hiligaynon' => [
        'label'    => 'Hiligaynon',
        'native'   => 'Ilonggo',
        'region'   => 'Western Visayas',
        'speakers' => '9M',
        'flag'     => '🎶',
        'color'    => '#d97706',
        'prompt'   => 'You are an expert Hiligaynon journalist and translator from Iloilo City. Translate the provided news article (title and body) into authentic Hiligaynon (Ilonggo) as spoken in Iloilo, Bacolod, and Western Visayas. Use natural Ilonggo expressions suitable for a regional newspaper. Preserve proper nouns, organization names, and technical terms in their original form. Avoid unnecessary Tagalog borrowing. Maintain a formal, journalistic tone.',
    ],
    'bicolano' => [
        'label'    => 'Bicolano',
        'native'   => 'Bikol',
        'region'   => 'Bicol Region',
        'speakers' => '6M',
        'flag'     => '🌋',
        'color'    => '#dc2626',
        'prompt'   => 'You are an expert Bicolano journalist and translator from Naga City. Translate the provided news article (title and body) into authentic Central Bikol (Bicolano) as spoken in Naga, Legazpi, and the Bicol Region. Use vocabulary natural to a Bicolano regional newspaper. Preserve proper nouns, names, organization names, and technical terms untranslated. Maintain journalistic formality and clarity throughout.',
    ],
    'waray' => [
        'label'    => 'Waray',
        'native'   => 'Winaray',
        'region'   => 'Eastern Visayas',
        'speakers' => '4M',
        'flag'     => '🌺',
        'color'    => '#9333ea',
        'prompt'   => 'You are an expert Waray-Waray journalist and translator from Tacloban City. Translate the provided news article (title and body) into authentic Waray (Winaray) as spoken in Tacloban, Leyte, and Samar. Use vocabulary natural to a local Eastern Visayas newspaper. Avoid code-switching to Tagalog unless absolutely necessary. Preserve all proper nouns, organization names, place names, and technical terms in their original form. Maintain a formal news tone.',
    ],
    'kapampangan' => [
        'label'    => 'Kapampangan',
        'native'   => 'Amanu',
        'region'   => 'Pampanga, Tarlac',
        'speakers' => '3M',
        'flag'     => '🦅',
        'color'    => '#0f766e',
        'prompt'   => 'You are an expert Kapampangan journalist and translator from San Fernando, Pampanga. Translate the provided news article (title and body) into authentic Kapampangan (Amanu) as spoken in Pampanga and Tarlac. Use vocabulary natural to a local Kapampangan newspaper. Preserve all proper nouns, organization names, place names, and technical terms in their original form. Avoid unnecessary Tagalog borrowing. Maintain a formal, journalistic tone.',
    ],
    'pangasinan' => [
        'label'    => 'Pangasinan',
        'native'   => 'Pangasinense',
        'region'   => 'Pangasinan Province',
        'speakers' => '2M',
        'flag'     => '🐟',
        'color'    => '#7c3aed',
        'prompt'   => 'You are an expert Pangasinense journalist and translator from Dagupan City. Translate the provided news article (title and body) into authentic Pangasinan (Pangasinense) as spoken in Pangasinan Province. Use vocabulary natural to a local Pangasinan broadsheet. Preserve all proper nouns, organization names, place names, and technical terms in their original form. Avoid code-switching to Tagalog unless no Pangasinan equivalent exists. Maintain a formal news tone.',
    ],
];

// ─── HELPERS ───────────────────────────────────────────────────────────────

/**
 * Strip HTML tags and normalize whitespace for clean API input.
 * Converts <br> and </p> to newlines to preserve paragraph structure.
 */
function prepareContent(string $html): string
{
    $text = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $text = preg_replace('/<\/p>/i', "\n\n", $text);
    $text = strip_tags($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/[ \t]+/', ' ', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", trim($text));
    return $text;
}

/**
 * Translate title AND body together in one unified API call.
 * Returns ['success'=>bool, 'title'=>string, 'body'=>string, 'error'=>string]
 */
function translateWithChatGPT(string $title, string $body, string $dialectKey): array
{
    $dialect = DIALECTS[$dialectKey];

    $systemPrompt = $dialect['prompt'] . '

STRICT OUTPUT RULES:
- Translate the title and body as one unified piece — keep all terminology consistent between them.
- Preserve all proper nouns, personal names, place names, and organization names exactly as written.
- Preserve all numbers, dates, percentages, and statistics exactly as written.
- Do NOT add translator notes, explanations, or language labels.
- Respond ONLY with valid JSON in this exact structure (no markdown, no code fences):
{"title":"<translated title>","body":"<translated body>"}';

    $userContent = json_encode(
        ['title' => $title, 'body' => $body],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    $payload = [
        'model'           => OPENAI_MODEL,
        'messages'        => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => $userContent],
        ],
        'temperature'     => 0.1,                          // Low = consistent, literal, fewer hallucinations
        'max_tokens'      => 4096,
        'response_format' => ['type' => 'json_object'],    // Force valid JSON — no stray text
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT    => 120,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'error' => 'Network error: ' . $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? 'Unknown API error';
        return ['success' => false, 'error' => "OpenAI ({$httpCode}): {$errMsg}"];
    }

    $rawContent = $data['choices'][0]['message']['content'] ?? '{}';
    $result     = json_decode($rawContent, true);

    if (empty($result['title']) || empty($result['body'])) {
        return ['success' => false, 'error' => 'Model returned an unexpected output format. Please try again.'];
    }

    return [
        'success' => true,
        'title'   => trim($result['title']),
        'body'    => trim($result['body']),
    ];
}

function fetchArticle(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function saveTranslation(PDO $pdo, int $articleId, string $dialectKey, array $translated): bool
{
    $stmt = $pdo->prepare("
        UPDATE news
        SET translated_title = :title,
            translated_body  = :body,
            translated_lang  = :lang,
            translated_at    = NOW(),
            is_translated    = 1
        WHERE id = :id
    ");
    return $stmt->execute([
        ':title' => $translated['title'],
        ':body'  => $translated['body'],
        ':lang'  => DIALECTS[$dialectKey]['label'],
        ':id'    => $articleId,
    ]);
}

function wordCount(string $text): int
{
    return str_word_count(strip_tags($text));
}

function estimatedSeconds(int $words): int
{
    // gpt-4o is slightly slower than gpt-4o-mini — bumped estimate up slightly
    return max(15, (int)(20 + $words * 0.06));
}

// ─── ROUTING ───────────────────────────────────────────────────────────────
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($articleId <= 0) {
    $_SESSION['error'] = 'Invalid article ID.';
    header('Location: ../user_dashboard.php');
    exit;
}

if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$article = fetchArticle($pdo, $articleId);
if (!$article) {
    $_SESSION['error'] = 'Article not found.';
    header('Location: ../user_dashboard.php');
    exit;
}

$words   = wordCount(($article['content'] ?? '') . ' ' . ($article['title'] ?? ''));
$estSecs = estimatedSeconds($words);

// ─── POST: Process Translation ──────────────────────────────────────────────
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedDialect = $_POST['dialect'] ?? '';

    if (!array_key_exists($selectedDialect, DIALECTS)) {
        $error = 'Please select a valid dialect.';
    } else {
        // Strip HTML for cleaner, more accurate translation
        $cleanTitle = prepareContent($article['title']   ?? '');
        $cleanBody  = prepareContent($article['content'] ?? '');

        // Single unified call — title and body translated together for consistent terminology
        $result = translateWithChatGPT($cleanTitle, $cleanBody, $selectedDialect);

        if (!$result['success']) {
            $error = 'Translation failed — ' . $result['error'];
        } else {
            $saved = saveTranslation($pdo, $articleId, $selectedDialect, [
                'title' => $result['title'],
                'body'  => $result['body'],
            ]);
            if ($saved) {
                $_SESSION['success'] = "Article #{$articleId} translated to " . DIALECTS[$selectedDialect]['label'] . " successfully.";
                header('Location: ../user_dashboard.php?section=translated');
                exit;
            } else {
                $error = 'Translation completed but could not be saved to the database.';
            }
        }
    }
}

$alreadyTranslated = !empty($article['is_translated']) && $article['is_translated'] == 1;
$existingLang      = $article['translated_lang']  ?? '';
$existingTitle     = $article['translated_title'] ?? '';
$existingBody      = $article['translated_body']  ?? '';
$translatedAt      = $article['translated_at']    ?? '';
$excerpt           = substr(strip_tags($article['content'] ?? ''), 0, 240);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Translate — Article #<?= $articleId ?> · DZRH</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;700;900&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

:root{
    --ink:       #5A189A;
    --ink-2:     #3a3a3a;
    --ink-3:     #6b6b6b;
    --paper:     #ffffff;
    --paper-2:   #faf8ff;
    --paper-3:   #f0e8ff;
    --gold:      #7B2FBE;
    --gold-light:#f3e8ff;
    --border:    #E0AAFF;
    --shadow:    0 1px 3px rgba(0,0,0,.07),0 4px 16px rgba(0,0,0,.05);
    --shadow-lg: 0 8px 32px rgba(0,0,0,.11),0 2px 8px rgba(0,0,0,.07);
    --r:         10px;
}

html{scroll-behavior:smooth}

body{
    font-family:'DM Sans',sans-serif;
    background:var(--paper);
    color:var(--ink-2);
    min-height:100vh;
    line-height:1.6;
    font-size:15px;
}

/* ── MASTHEAD ─────────────────────── */
.masthead{
    background:var(--ink);
    padding:0 2rem;
    display:flex;
    align-items:center;
    justify-content:space-between;
    height:54px;
    position:sticky;
    top:0;
    z-index:100;
    border-bottom:3px solid var(--gold);
}
.logo{
    font-family:'Playfair Display',serif;
    font-weight:900;
    font-size:1.2rem;
    color:#fff;
    text-decoration:none;
    letter-spacing:.01em;
}
.logo em{color:#C77DFF;font-style:normal}
.logo small{font-family:'DM Sans',sans-serif;font-weight:300;font-size:.7em;color:rgba(255,255,255,.38);margin-left:.4rem}
.masthead-right{display:flex;align-items:center;gap:1rem}
.model-badge{
    display:inline-flex;
    align-items:center;
    gap:.3rem;
    padding:.2rem .65rem;
    background:rgba(199,125,255,.15);
    border:1px solid rgba(199,125,255,.3);
    border-radius:99px;
    font-family:'DM Mono',monospace;
    font-size:.65rem;
    color:#C77DFF;
    font-weight:500;
    letter-spacing:.04em;
}
.model-badge .mi{font-size:.7rem}
.back-link{
    display:flex;
    align-items:center;
    gap:.35rem;
    font-size:.8rem;
    font-weight:500;
    color:rgba(255,255,255,.55);
    text-decoration:none;
    transition:color .2s;
}
.back-link:hover{color:#C77DFF}
.back-link .mi{font-size:1rem}

/* ── META BAR ─────────────────────── */
.meta-bar{
    background:var(--paper-2);
    border-bottom:1px solid var(--border);
    padding:.55rem 2rem;
    display:flex;
    align-items:center;
    flex-wrap:wrap;
    gap:.4rem 1.2rem;
    font-family:'DM Mono',monospace;
    font-size:.7rem;
    color:var(--ink-3);
}
.meta-sep{color:var(--border)}
.pill{
    display:inline-flex;
    align-items:center;
    gap:.25rem;
    padding:.12rem .55rem;
    border-radius:99px;
    font-size:.67rem;
    font-weight:600;
    letter-spacing:.02em;
}
.pill-gold{background:var(--gold-light);color:#6b21a8;border:1px solid #d8b4fe}
.pill-green{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.pill .mi{font-size:.72rem}

/* ── LAYOUT ───────────────────────── */
.wrap{
    max-width:1160px;
    margin:0 auto;
    padding:2.5rem 2rem 5rem;
}
.ph{margin-bottom:1.5rem}
.ph h1{
    font-family:'Playfair Display',serif;
    font-size:clamp(1.75rem,3vw,2.5rem);
    font-weight:900;
    line-height:1.1;
    color:var(--ink);
}
.ph p{font-size:.88rem;color:var(--ink-3);margin-top:.35rem}

/* ── ENHANCEMENT NOTICE ───────────── */
.notice{
    display:flex;
    align-items:flex-start;
    gap:.65rem;
    padding:.8rem 1.1rem;
    background:var(--gold-light);
    border:1px solid #d8b4fe;
    border-radius:8px;
    margin-bottom:1.8rem;
    font-size:.78rem;
    color:#581c87;
    line-height:1.55;
}
.notice .mi{font-size:1rem;flex-shrink:0;margin-top:.1rem;color:var(--ink)}
.notice strong{color:var(--ink)}

.cols{
    display:grid;
    grid-template-columns:1fr 1.12fr;
    gap:2rem;
    align-items:start;
}
@media(max-width:820px){.cols{grid-template-columns:1fr}}

/* ── PANEL ────────────────────────── */
.panel{
    background:#fff;
    border:1px solid var(--border);
    border-radius:var(--r);
    overflow:hidden;
    box-shadow:var(--shadow);
}
.ph2{
    padding:.9rem 1.3rem;
    border-bottom:1px solid var(--border);
    background:var(--paper-2);
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.ph2-label{
    font-size:.68rem;
    font-weight:700;
    letter-spacing:.1em;
    text-transform:uppercase;
    color:var(--ink-3);
}
.ph2-sub{
    font-family:'DM Mono',monospace;
    font-size:.68rem;
    color:var(--ink-3);
}
.pb{padding:1.4rem}

/* ── ARTICLE CARD ─────────────────── */
.art-id{
    font-family:'DM Mono',monospace;
    font-size:.68rem;
    color:var(--ink-3);
    display:flex;
    align-items:center;
    gap:.35rem;
    margin-bottom:.55rem;
}
.art-id::before{
    content:'';
    width:7px;height:7px;
    background:var(--gold);
    border-radius:50%;
    display:inline-block;
    flex-shrink:0;
}
.art-title{
    font-family:'Playfair Display',serif;
    font-size:1.1rem;
    font-weight:700;
    line-height:1.35;
    color:var(--ink);
    margin-bottom:.7rem;
}
.art-excerpt{
    font-size:.83rem;
    color:var(--ink-3);
    line-height:1.65;
    border-left:3px solid var(--border);
    padding-left:.8rem;
    margin-bottom:.9rem;
}
.art-meta{
    display:flex;
    flex-wrap:wrap;
    gap:.4rem 1.1rem;
    font-size:.74rem;
    color:var(--ink-3);
    padding-top:.8rem;
    border-top:1px solid var(--paper-3);
}
.art-meta span{display:flex;align-items:center;gap:.28rem}
.art-meta .mi{font-size:.9rem}

/* ── EXISTING TRANSLATION ─────────── */
.ex-box{
    margin-top:1rem;
    border:1px solid var(--border);
    border-radius:8px;
    overflow:hidden;
}
.ex-head{
    padding:.55rem 1rem;
    background:var(--gold-light);
    border-bottom:1px solid #d8b4fe;
    display:flex;
    align-items:center;
    justify-content:space-between;
}
.ex-head p{font-size:.73rem;font-weight:600;color:#6b21a8}
.ex-head small{font-size:.66rem;color:#7e22ce}
.ex-body{
    padding:1rem;
    max-height:175px;
    overflow-y:auto;
    background:#fdf8ff;
}
.ex-body h4{
    font-family:'Playfair Display',serif;
    font-size:.93rem;
    font-weight:700;
    color:var(--ink);
    margin-bottom:.45rem;
    line-height:1.3;
}
.ex-body p{font-size:.78rem;color:var(--ink-2);line-height:1.6}

.warn-row{
    margin-top:.7rem;
    display:flex;
    align-items:center;
    gap:.45rem;
    font-size:.77rem;
    color:#92400e;
    background:#fffbeb;
    border:1px solid #fde68a;
    border-radius:7px;
    padding:.5rem .75rem;
}
.warn-row .mi{font-size:.95rem;color:#d97706;flex-shrink:0}

/* ── ERROR ────────────────────────── */
.err{
    display:flex;
    align-items:flex-start;
    gap:.55rem;
    padding:.85rem 1.1rem;
    background:#fef2f2;
    border:1px solid #fca5a5;
    border-radius:8px;
    margin-bottom:1.5rem;
    font-size:.83rem;
    color:#991b1b;
}
.err .mi{font-size:1.05rem;margin-top:.05rem;flex-shrink:0}

/* ── SEARCH ───────────────────────── */
.s-wrap{position:relative;margin-bottom:.9rem}
.s-icon{
    position:absolute;left:.75rem;top:50%;
    transform:translateY(-50%);
    font-size:1rem !important;
    color:var(--ink-3);
    pointer-events:none;
}
.s-input{
    width:100%;
    padding:.58rem .75rem .58rem 2.35rem;
    border:1px solid var(--border);
    border-radius:8px;
    font-family:'DM Sans',sans-serif;
    font-size:.84rem;
    color:var(--ink-2);
    background:var(--paper);
    outline:none;
    transition:border-color .2s,box-shadow .2s;
}
.s-input:focus{border-color:var(--ink);box-shadow:0 0 0 3px rgba(90,24,154,.1)}
.s-input::placeholder{color:var(--ink-3)}

/* ── DIALECT GRID ─────────────────── */
.d-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.55rem;
    max-height:360px;
    overflow-y:auto;
    padding-right:.15rem;
}
.d-grid::-webkit-scrollbar{width:4px}
.d-grid::-webkit-scrollbar-track{background:var(--paper-2)}
.d-grid::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}

.dc{
    cursor:pointer;
    border:1.5px solid var(--border);
    border-radius:8px;
    padding:.85rem .8rem;
    background:#fff;
    transition:border-color .15s,background .15s,box-shadow .15s,transform .15s;
    position:relative;
    overflow:hidden;
    user-select:none;
    outline:none;
}
.dc::after{
    content:'';
    position:absolute;
    top:0;left:0;right:0;
    height:3px;
    background:var(--dc, #999);
    transform:scaleX(0);
    transform-origin:left;
    transition:transform .18s ease;
}
.dc:hover{
    border-color:#c4b5fd;
    background:var(--paper-2);
    transform:translateY(-1px);
    box-shadow:0 4px 14px rgba(90,24,154,.1);
}
.dc:hover::after,.dc.sel::after{transform:scaleX(1)}
.dc.sel{
    border-color:var(--dc,#999);
    background:var(--paper-2);
    box-shadow:0 0 0 3px rgba(90,24,154,.08),0 4px 14px rgba(0,0,0,.08);
}
.dc.hidden{display:none}

.dc-top{
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    margin-bottom:.45rem;
}
.dc-flag{font-size:1.35rem;line-height:1}
.dc-chk{
    width:17px;height:17px;
    border-radius:50%;
    border:1.5px solid var(--border);
    display:flex;
    align-items:center;
    justify-content:center;
    transition:all .14s;
    flex-shrink:0;
}
.dc.sel .dc-chk{background:var(--dc,#999);border-color:var(--dc,#999)}
.dc-chk .mi{font-size:.68rem;color:transparent}
.dc.sel .dc-chk .mi{color:#fff}

.dc-label{font-size:.86rem;font-weight:700;color:var(--ink);line-height:1.2;margin-bottom:.12rem}
.dc-native{font-size:.7rem;color:var(--ink-3);font-style:italic;margin-bottom:.35rem}
.dc-region{font-size:.68rem;color:var(--ink-3);line-height:1.3}
.dc-spk{
    margin-top:.38rem;
    font-family:'DM Mono',monospace;
    font-size:.63rem;
    font-weight:500;
    color:var(--dc,var(--ink-3));
    display:flex;
    align-items:center;
    gap:.22rem;
}
.dc-spk .mi{font-size:.72rem}

.no-res{
    grid-column:span 2;
    text-align:center;
    padding:2rem;
    color:var(--ink-3);
    font-size:.83rem;
    display:none;
}
.no-res .mi{font-size:2rem;display:block;margin-bottom:.4rem;opacity:.35}

/* ── SELECTED INFO ────────────────── */
.sel-info{
    margin-top:.8rem;
    padding:.65rem .95rem;
    background:var(--paper-2);
    border:1px solid var(--border);
    border-radius:8px;
    font-size:.78rem;
    color:var(--ink-2);
    display:none;
    align-items:center;
    gap:.45rem;
}
.sel-info.vis{display:flex}
.sel-info strong{color:var(--ink)}
.sel-info .mi{font-size:.92rem;color:var(--ink)}
.sel-info .est{margin-left:auto;color:var(--ink-3);font-family:'DM Mono',monospace;font-size:.7rem}

/* ── BUTTONS ──────────────────────── */
.act-row{margin-top:1.1rem;display:flex;gap:.65rem}
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:.38rem;
    padding:.7rem 1.25rem;
    border-radius:8px;
    font-family:'DM Sans',sans-serif;
    font-size:.86rem;
    font-weight:600;
    cursor:pointer;
    text-decoration:none;
    transition:all .2s;
    border:1.5px solid transparent;
}
.btn-ghost{
    flex:0 0 auto;
    background:#fff;
    border-color:var(--border);
    color:var(--ink-2);
}
.btn-ghost:hover{background:var(--paper-2);border-color:#a78bfa}
.btn-ink{
    flex:1;
    background:var(--ink);
    color:#fff;
    border-color:var(--ink);
    position:relative;
    overflow:hidden;
}
.btn-ink:not(:disabled):hover{background:#7B2FBE;box-shadow:0 4px 18px rgba(90,24,154,.35)}
.btn-ink:disabled{opacity:.32;cursor:not-allowed;pointer-events:none}
.btn .mi{font-size:.95rem}
.hint{
    margin-top:.9rem;
    font-size:.7rem;
    color:var(--ink-3);
    line-height:1.55;
    text-align:center;
}

/* ── LOADING ──────────────────────── */
.overlay{
    display:none;
    position:fixed;
    inset:0;
    background:rgba(16,0,43,.93);
    z-index:9999;
    align-items:center;
    justify-content:center;
}
.overlay.on{display:flex}
.loading-card{
    background:#fff;
    border-radius:16px;
    padding:2.4rem 2.8rem;
    max-width:390px;
    width:92%;
    text-align:center;
    position:relative;
    overflow:hidden;
}
.loading-card::before{
    content:'';
    position:absolute;
    top:0;left:0;right:0;
    height:3px;
    background:linear-gradient(90deg,#C77DFF,#5A189A,#C77DFF);
    background-size:200% 100%;
    animation:sh 1.8s linear infinite;
}
@keyframes sh{0%{background-position:0 0}100%{background-position:200% 0}}

.spin-wrap{width:48px;height:48px;margin:0 auto 1.2rem;position:relative}
.spin-wrap::before,.spin-wrap::after{
    content:'';
    position:absolute;
    inset:0;
    border-radius:50%;
}
.spin-wrap::before{border:3px solid var(--paper-3)}
.spin-wrap::after{
    border:3px solid transparent;
    border-top-color:var(--ink);
    animation:sp .85s linear infinite;
}
@keyframes sp{to{transform:rotate(360deg)}}

.lc-badge{
    display:inline-flex;
    align-items:center;
    gap:.28rem;
    font-family:'DM Mono',monospace;
    font-size:.65rem;
    color:var(--ink);
    background:var(--gold-light);
    border:1px solid #d8b4fe;
    border-radius:99px;
    padding:.2rem .65rem;
    margin-bottom:.9rem;
}
.lc-badge .mi{font-size:.72rem}
.lc-title{
    font-family:'Playfair Display',serif;
    font-size:1.18rem;
    font-weight:700;
    color:var(--ink);
    margin-bottom:.35rem;
}
.lc-sub{font-size:.8rem;color:var(--ink-3);line-height:1.55;margin-bottom:1.1rem}
.prog-wrap{
    height:4px;
    background:var(--paper-3);
    border-radius:4px;
    overflow:hidden;
    margin-bottom:.5rem;
}
.prog-fill{
    height:100%;
    background:linear-gradient(90deg,#C77DFF,var(--ink));
    border-radius:4px;
    width:0%;
    transition:width .4s ease;
}
.prog-lbl{
    font-family:'DM Mono',monospace;
    font-size:.66rem;
    color:var(--ink-3);
}

@media(max-width:520px){
    .wrap{padding:1.5rem 1rem 3rem}
    .d-grid{grid-template-columns:1fr;max-height:280px}
    .act-row{flex-direction:column}
    .btn-ghost{order:2}
    .model-badge{display:none}
}
</style>
</head>
<body>

<!-- Masthead -->
<header class="masthead">
    <a href="../user_dashboard.php" class="logo">DZ<em>RH</em><small>CMS</small></a>
    <div class="masthead-right">
        <span class="model-badge">
            <span class="material-icons-round mi">auto_awesome</span>
            <?= OPENAI_MODEL ?>
        </span>
        <a href="../user_dashboard.php" class="back-link">
            <span class="material-icons-round mi">arrow_back</span>
            Dashboard
        </a>
    </div>
</header>

<!-- Meta Bar -->
<div class="meta-bar">
    <span>Article #<?= $articleId ?></span>
    <span class="meta-sep">·</span>
    <span><?= number_format($words) ?> words</span>
    <span class="meta-sep">·</span>
    <span>~<?= $estSecs ?>s estimated</span>
    <span class="meta-sep">·</span>
    <?php if ($alreadyTranslated): ?>
    <span class="pill pill-gold">
        <span class="material-icons-round mi">translate</span>
        <?= htmlspecialchars($existingLang) ?>
    </span>
    <?php else: ?>
    <span class="pill pill-green">
        <span class="material-icons-round mi">fiber_new</span>
        Not yet translated
    </span>
    <?php endif; ?>
</div>

<!-- Loading Overlay -->
<div class="overlay" id="overlay">
    <div class="loading-card">
        <div class="spin-wrap"></div>
        <span class="lc-badge">
            <span class="material-icons-round mi">auto_awesome</span>
            <?= OPENAI_MODEL ?> · unified call
        </span>
        <p class="lc-title">Translating&hellip;</p>
        <p class="lc-sub" id="lc-sub">Sending to OpenAI — this usually takes <?= $estSecs ?>s.</p>
        <div class="prog-wrap"><div class="prog-fill" id="prog"></div></div>
        <p class="prog-lbl" id="prog-lbl">0%</p>
    </div>
</div>

<div class="wrap">

    <!-- Page Header -->
    <div class="ph">
        <h1>Translate Article</h1>
        <p>Choose a Filipino dialect — <?= OPENAI_MODEL ?> will generate a full translation of the title and body.</p>
    </div>

    <!-- Enhancement Notice -->
    <div class="notice">
        <span class="material-icons-round mi">tips_and_updates</span>
        <span>
            <strong>Enhanced accuracy mode:</strong>
            Title and body are now translated together in a single call for consistent terminology.
            HTML is stripped before sending for cleaner input.
            Model upgraded to <strong><?= OPENAI_MODEL ?></strong> for better quality on minor dialects (Waray, Pangasinan, Kapampangan).
        </span>
    </div>

    <!-- Error -->
    <?php if ($error): ?>
    <div class="err">
        <span class="material-icons-round mi">error_outline</span>
        <div><strong>Translation failed.</strong> <?= htmlspecialchars($error) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" id="form">
    <div class="cols">

        <!-- LEFT — Article Preview -->
        <div>
            <div class="panel">
                <div class="ph2">
                    <span class="ph2-label">Article Preview</span>
                    <span class="ph2-sub">#<?= $articleId ?></span>
                </div>
                <div class="pb">
                    <p class="art-id">News Article</p>
                    <h2 class="art-title"><?= htmlspecialchars($article['title'] ?? 'Untitled') ?></h2>
                    <?php if ($excerpt): ?>
                    <p class="art-excerpt"><?= htmlspecialchars($excerpt) ?>&hellip;</p>
                    <?php endif; ?>
                    <div class="art-meta">
                        <span><span class="material-icons-round mi">article</span><?= number_format($words) ?> words</span>
                        <?php if (!empty($article['author'])): ?>
                        <span><span class="material-icons-round mi">person</span><?= htmlspecialchars($article['author']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($article['published_at'])): ?>
                        <span><span class="material-icons-round mi">schedule</span><?= date('M d, Y', strtotime($article['published_at'])) ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($alreadyTranslated && $existingTitle): ?>
                    <div class="ex-box">
                        <div class="ex-head">
                            <p>Existing: <?= htmlspecialchars($existingLang) ?></p>
                            <?php if ($translatedAt): ?><small><?= date('M d, Y', strtotime($translatedAt)) ?></small><?php endif; ?>
                        </div>
                        <div class="ex-body">
                            <h4><?= htmlspecialchars($existingTitle) ?></h4>
                            <?php if ($existingBody): ?>
                            <p><?= htmlspecialchars(substr(strip_tags($existingBody), 0, 300)) ?>&hellip;</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="warn-row">
                        <span class="material-icons-round mi">warning_amber</span>
                        Submitting will <strong>&nbsp;overwrite&nbsp;</strong> the existing <?= htmlspecialchars($existingLang) ?> translation.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- RIGHT — Dialect Selector -->
        <div>
            <div class="panel">
                <div class="ph2">
                    <span class="ph2-label">Select Dialect</span>
                    <span class="ph2-sub"><?= count(DIALECTS) ?> languages</span>
                </div>
                <div class="pb">

                    <div class="s-wrap">
                        <span class="material-icons-round s-icon">search</span>
                        <input class="s-input" id="search" type="text"
                               placeholder="Search dialect or region…" autocomplete="off">
                    </div>

                    <div class="d-grid" id="grid">
                        <?php foreach (DIALECTS as $key => $d): ?>
                        <div class="dc <?= (isset($_POST['dialect']) && $_POST['dialect'] === $key) ? 'sel' : '' ?>"
                             id="c-<?= $key ?>"
                             style="--dc:<?= $d['color'] ?>"
                             data-key="<?= $key ?>"
                             data-lbl="<?= strtolower($d['label']) ?>"
                             data-nat="<?= strtolower($d['native']) ?>"
                             data-reg="<?= strtolower($d['region']) ?>"
                             tabindex="0"
                             onclick="pick('<?= $key ?>')"
                             onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pick('<?= $key ?>')}">
                            <div class="dc-top">
                                <span class="dc-flag"><?= $d['flag'] ?></span>
                                <div class="dc-chk"><span class="material-icons-round mi">check</span></div>
                            </div>
                            <p class="dc-label"><?= htmlspecialchars($d['label']) ?></p>
                            <p class="dc-native"><?= htmlspecialchars($d['native']) ?></p>
                            <p class="dc-region"><?= htmlspecialchars($d['region']) ?></p>
                            <p class="dc-spk"><span class="material-icons-round mi">people</span><?= $d['speakers'] ?> speakers</p>
                            <input type="radio" name="dialect" value="<?= $key ?>" id="r-<?= $key ?>" style="display:none"
                                   <?= (isset($_POST['dialect']) && $_POST['dialect'] === $key) ? 'checked' : '' ?>>
                        </div>
                        <?php endforeach; ?>
                        <div class="no-res" id="no-res">
                            <span class="material-icons-round mi">search_off</span>
                            No dialects match your search
                        </div>
                    </div>

                    <div class="sel-info" id="sel-info">
                        <span class="material-icons-round mi">check_circle</span>
                        Selected: <strong id="sel-lbl">—</strong>
                        <span class="est" id="sel-est"></span>
                    </div>

                    <div class="act-row">
                        <a href="../user_dashboard.php" class="btn btn-ghost">
                            <span class="material-icons-round mi">close</span>Cancel
                        </a>
                        <button type="submit" class="btn btn-ink" id="submit-btn" disabled>
                            <span class="material-icons-round mi">translate</span>Translate Now
                        </button>
                    </div>

                    <p class="hint">AI translations should be reviewed before publishing. Submitting will overwrite any existing translation for this article.</p>
                </div>
            </div>
        </div>

    </div>
    </form>
</div>

<script>
(function(){
    const estSecs = <?= $estSecs ?>;
    let selected  = null;

    window.pick = function(key) {
        document.querySelectorAll('.dc').forEach(c=>c.classList.remove('sel'));
        const card = document.getElementById('c-'+key);
        const radio= document.getElementById('r-'+key);
        if(!card) return;

        card.classList.add('sel');
        radio.checked = true;
        selected = key;

        document.getElementById('sel-lbl').textContent =
            card.dataset.lbl.charAt(0).toUpperCase() + card.dataset.lbl.slice(1);
        document.getElementById('sel-est').textContent = '~'+estSecs+'s';
        document.getElementById('sel-info').classList.add('vis');
        document.getElementById('submit-btn').disabled = false;
    };

    // Restore selection after POST error
    <?php if(!empty($_POST['dialect']) && array_key_exists($_POST['dialect'], DIALECTS)): ?>
    pick('<?= $_POST['dialect'] ?>');
    <?php endif; ?>

    // Live search / filter
    document.getElementById('search').addEventListener('input', function(){
        const q = this.value.toLowerCase().trim();
        let visible = 0;
        document.querySelectorAll('.dc').forEach(card=>{
            const match = !q ||
                card.dataset.lbl.includes(q) ||
                card.dataset.nat.includes(q) ||
                card.dataset.reg.includes(q);
            card.classList.toggle('hidden', !match);
            if(match) visible++;
        });
        document.getElementById('no-res').style.display = (visible===0 && q) ? 'block' : 'none';
    });

    // Submit → show loading overlay with animated progress bar
    document.getElementById('form').addEventListener('submit', function(e){
        if(!selected){ e.preventDefault(); document.getElementById('search').focus(); return; }

        const card = document.getElementById('c-'+selected);
        const name = card ? card.querySelector('.dc-label').textContent : selected;

        document.getElementById('lc-sub').textContent =
            'Translating to '+name+' using <?= OPENAI_MODEL ?> — please wait, this usually takes '+estSecs+' seconds.';

        document.getElementById('overlay').classList.add('on');

        const fill = document.getElementById('prog');
        const lbl  = document.getElementById('prog-lbl');
        let pct    = 0;
        const step = 88 / (estSecs * 4);

        const t = setInterval(function(){
            pct = Math.min(88, pct + step + Math.random() * step * 0.4);
            fill.style.width = pct + '%';
            lbl.textContent  = Math.round(pct) + '%';
        }, 250);

        setTimeout(()=>clearInterval(t), estSecs * 1200);
    });
})();
</script>
</body>
</html>