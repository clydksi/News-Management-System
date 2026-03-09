<?php
require dirname(__DIR__, 2) . '/auth.php';
require dirname(__DIR__, 2) . '/db.php';

// ═══════════════════════════════════════════════════════════════════
// CRITICAL FIX: Auto-populate department_id in session if missing
// ═══════════════════════════════════════════════════════════════════
if (!isset($_SESSION['department_id']) && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT department_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) { $_SESSION['department_id'] = (int)$user['department_id']; }
}

// ============================================
// SECURITY: CSRF Protection
// ============================================
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid CSRF token. Please refresh the page.']));
    }
}

// Load AI configuration
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require $configPath;
} else {
    define('AI_PROVIDERS', [
        'anthropic' => [
            'name' => 'Claude (Anthropic)',
            'icon' => '🤖',
            'api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
            'models' => [
                'claude-sonnet-4-20250514' => [
                    'name' => 'Claude Sonnet 4',
                    'description' => 'Best balance of speed and quality',
                    'max_tokens' => 4000,
                    'recommended' => true
                ]
            ],
            'default_model' => 'claude-sonnet-4-20250514'
        ]
    ]);
    define('DEFAULT_AI_PROVIDER', 'anthropic');
    define('AI_ASSISTANT_ENABLED', !empty(getenv('ANTHROPIC_API_KEY')));
    define('ENABLED_PROVIDERS', AI_ASSISTANT_ENABLED ? ['anthropic'] : []);
}

$error = null;
$success = null;
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// ============================================
// SECURITY: Rate Limiting
// ============================================
function checkAIRateLimit($userId) {
    // ENHANCEMENT: File-based rate limiting has two weaknesses:
    //   1. Race condition — two simultaneous requests can both pass the check before either
    //      writes the updated file. Fix: open with LOCK_EX flock() before read/write.
    //   2. Files live in sys_get_temp_dir() which may be cleared by the OS on restart,
    //      losing all rate limit state. Consider storing counters in the DB or Redis instead.
    // ENHANCEMENT: The 50/hour limit is hard-coded. Move it to config.php or system_settings
    //   so admins can tune it per-role (e.g., editors get 100/hour, writers get 30/hour).
    // ENHANCEMENT: Rate limiting is per user ID only. A bad actor with multiple accounts can
    //   bypass it. Add a secondary per-IP limit (store $_SERVER['REMOTE_ADDR'] hash in cache).
    $cacheFile = sys_get_temp_dir() . "/ai_limit_{$userId}.json";
    $requests = file_exists($cacheFile) ? (json_decode(file_get_contents($cacheFile), true) ?: []) : [];
    $currentTime = time();
    $requests = array_filter($requests, fn($t) => $t > $currentTime - 3600);
    if (count($requests) >= 50) {
        $resetTime = min($requests) + 3600;
        $minutesLeft = ceil(($resetTime - $currentTime) / 60);
        throw new Exception("Rate limit exceeded. Try again in {$minutesLeft} minutes. (50 requests/hour)");
    }
    $requests[] = $currentTime;
    file_put_contents($cacheFile, json_encode($requests));
    return count($requests);
}

function getAIRateLimitInfo($userId) {
    // ENHANCEMENT: This function reads and filters the cache file separately from
    //   checkAIRateLimit(), duplicating logic. Extract a shared helper
    //   getFilteredRequests($userId) to keep both functions DRY.
    $cacheFile = sys_get_temp_dir() . "/ai_limit_{$userId}.json";
    if (!file_exists($cacheFile)) return ['used' => 0, 'limit' => 50, 'remaining' => 50];
    $requests = json_decode(file_get_contents($cacheFile), true) ?: [];
    $requests = array_filter($requests, fn($t) => $t > time() - 3600);
    $used = count($requests);
    return ['used' => $used, 'limit' => 50, 'remaining' => 50 - $used];
}

function getCategoryIcon($categoryName) {
    $n = strtolower($categoryName);
    $icons = [
        'technology'=>'computer','tech'=>'computer','business'=>'business_center',
        'finance'=>'paid','health'=>'health_and_safety','medical'=>'medical_services',
        'sports'=>'sports_soccer','entertainment'=>'movie','politics'=>'account_balance',
        'science'=>'science','education'=>'school','lifestyle'=>'emoji_people',
        'travel'=>'flight','food'=>'restaurant','fashion'=>'checkroom',
        'automotive'=>'directions_car','real estate'=>'home','environment'=>'eco',
        'art'=>'palette','music'=>'music_note','gaming'=>'sports_esports',
        'news'=>'newspaper','opinion'=>'chat_bubble','weather'=>'wb_sunny','local'=>'location_on'
    ];
    foreach ($icons as $k => $v) { if (strpos($n, $k) !== false) return $v; }
    return 'folder';
}

function validateFilePath($filePath, $allowedDir = 'uploads/attachments/') {
    $filePath = str_replace(['../', '..\\'], '', $filePath);
    $basePath = dirname(__DIR__);
    $fullAllowedDir = realpath($basePath . '/' . $allowedDir);
    $fullFilePath = realpath($basePath . '/' . $filePath);
    if (!$fullFilePath || !$fullAllowedDir) return false;
    return strpos($fullFilePath, $fullAllowedDir) === 0;
}

$userStats = $pdo->prepare("SELECT COUNT(*) AS total_articles, COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) AS today_articles, COALESCE(AVG(LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1), 0) AS avg_words FROM news WHERE created_by = ?");
$userStats->execute([$_SESSION['user_id']]);
$stats = $userStats->fetch();
$rateLimitInfo = getAIRateLimitInfo($_SESSION['user_id']);

// ============================================
// Handle file attachment uploads
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_attachment'])) {
    header('Content-Type: application/json');
    if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['error' => 'No file uploaded or upload error occurred']); exit;
    }
    try {
        $uploadDir = dirname(__DIR__) . '/uploads/attachments/';
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) throw new Exception('Failed to create upload directory');
        $file = $_FILES['attachment'];
        $fileName = basename($file['name']); $fileSize = $file['size']; $fileTmp = $file['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip','rar','7z','jpg','jpeg','png','gif','webp','svg','mp4','avi','mov','wmv','flv','mp3','wav','ogg','flac'];
        if (!in_array($ext, $allowedExts)) throw new Exception('File type not allowed.');
        if ($fileSize > 50 * 1024 * 1024) throw new Exception('File size exceeds 50MB limit');
        $finfo = finfo_open(FILEINFO_MIME_TYPE); $mimeType = finfo_file($finfo, $fileTmp); finfo_close($finfo);
        $newFileName = uniqid('attach_', true) . '.' . $ext;
        $filePath = $uploadDir . $newFileName;
        $relativeFilePath = 'uploads/attachments/' . $newFileName;
        if (!move_uploaded_file($fileTmp, $filePath)) throw new Exception('Failed to move uploaded file');
        echo json_encode(['success' => true, 'file' => ['name' => $fileName, 'path' => $relativeFilePath, 'size' => $fileSize, 'type' => $mimeType, 'extension' => $ext]]);
    } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

// ============================================
// MULTI-PROVIDER AI ASSISTANT - AJAX Handler
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ai_action'])) {
    header('Content-Type: application/json');
    try {
        // CSRF is already validated for all POST requests by the catch-all check above (lines 21-26).
        // csrf_verify() returns void so !csrf_verify() is always true — do NOT use it in an if().

        if (!AI_ASSISTANT_ENABLED) throw new Exception('AI Assistant is not configured. Please set up your API keys in config.php');
        checkAIRateLimit($_SESSION['user_id']);

        $provider = $_POST['provider'] ?? DEFAULT_AI_PROVIDER;
        $model    = $_POST['model']    ?? null;

        // Validated allow-list for mode
        $mode = $_POST['mode'] ?? 'writing';
        if (!in_array($mode, ['chat', 'writing'])) throw new Exception('Invalid mode.');

        $action = $_POST['ai_action'];
        $prompt = trim($_POST['prompt'] ?? '');

        // Cap prompt to 4,000 chars to control token spend
        if (mb_strlen($prompt) > 4000) throw new Exception('Prompt too long (max 4,000 characters).');

        // Cap context to 3,000 chars silently — avoids inflating costs on long articles
        $context = trim($_POST['context'] ?? '');
        if (mb_strlen($context) > 3000) $context = mb_substr($context, 0, 3000) . '…';

        // Guard against malformed JSON in options
        $options = json_decode($_POST['options'] ?? '{}', true);
        if (json_last_error() !== JSON_ERROR_NONE) $options = [];

        if (empty($prompt)) throw new Exception('Prompt is required');
        if (!isset(AI_PROVIDERS[$provider])) throw new Exception('Invalid AI provider');
        $providerConfig = AI_PROVIDERS[$provider];
        if (empty($providerConfig['api_key']) || strpos($providerConfig['api_key'], 'your_') === 0)
            throw new Exception($providerConfig['name'] . ' is not configured. Please add your API key.');
        if (!$model) $model = $providerConfig['default_model'];
        $modelConfig = $providerConfig['models'][$model] ?? null;
        if (!$modelConfig) throw new Exception('Invalid model selected');

        if ($mode === 'chat') {
            $systemMessage = 'You are an intelligent AI assistant helping journalists and content creators with research, fact-checking, brainstorming, and answering questions. Be concise, accurate, and cite relevant context when available.';
            $fullPrompt = $prompt;
            if (!empty($context)) $fullPrompt = "Context from current article:\n{$context}\n\nQuestion: {$prompt}";
        } else {
            $systemMessages = [
                'generate'   => 'You are a professional journalist and content writer for a news CMS. Create engaging, well-structured articles with a compelling lead paragraph, clear subheadings (using ##), and a concise conclusion. Use active voice and journalistic style. Return the content in Markdown format.',

                'improve'    => 'You are an expert editor. Improve clarity, flow, and readability while preserving the author\'s voice and core message. Fix awkward phrasing, tighten sentences, and strengthen transitions. Return only the improved text in Markdown format.',

                'summarize'  => 'You are a summarisation specialist. Create a concise summary capturing the key facts, main arguments, and essential takeaways. Use a brief intro sentence followed by bullet points (- item). Keep the total under 150 words unless instructed otherwise.',

                'expand'     => 'You are a content expansion specialist. Expand the given content with relevant context, supporting details, real-world examples, and statistics where appropriate. Maintain the original tone and structure. Return the expanded text in Markdown format.',

                'grammar'    => 'You are an expert copy-editor. Fix all grammatical errors, spelling mistakes, punctuation issues, and sentence structure problems. Do NOT change the meaning or rewrite sentences unnecessarily. Return only the corrected text, preserving the original formatting.',

                'seo'        => 'You are an SEO content specialist. Optimise the content for search engines by naturally incorporating the main topic keyword, improving heading structure with ## subheadings, and ensuring the content answers likely search intent. At the top, prepend a line: **META:** (a meta description of 150–160 characters). At the bottom, append: **KEYWORDS:** (5 comma-separated focus keywords).',

                'brainstorm' => 'You are a creative brainstorming partner for journalists. Generate a diverse, numbered list of fresh angles, story ideas, or approaches based on the prompt. Include unconventional perspectives and underreported angles. Provide at least 8 distinct ideas with a one-sentence explanation for each.',

                'research'   => 'You are a research assistant for journalists. Provide structured, factual information on the topic with clear ## section headings: Background, Key Facts, Notable Figures or Organisations, Recent Developments, and Suggested Sources to Verify. Be thorough but concise.',

                'fact_check' => 'You are a fact-checking expert. Analyse each claim provided, assess whether it is Likely True, Unverified, Misleading, or Likely False, and briefly explain your reasoning. Format as a numbered list: "1. [Claim] — [Verdict]: [Explanation]". End with a summary of overall credibility.',
            ];

            // Log if an unknown action is received
            if (!isset($systemMessages[$action])) {
                error_log("[AI] Unknown action received: {$action} — falling back to 'generate'");
            }
            $systemMessage = $systemMessages[$action] ?? $systemMessages['generate'];

            $toneModifiers = [
                'professional' => ' Write in a professional, formal tone.',
                'casual'       => ' Write in a casual, conversational tone.',
                'journalistic' => ' Write in a journalistic, objective news style.',
            ];
            $lengthConstraints = [
                'brief'  => ' Keep under 200 words.',
                'medium' => ' Aim for 400–600 words.',
                'long'   => ' Provide 800–1200 words.',
            ];
            if (!empty($options['tone']))   $systemMessage .= $toneModifiers[$options['tone']]     ?? '';
            if (!empty($options['length'])) $systemMessage .= $lengthConstraints[$options['length']] ?? '';

            $fullPrompt = $prompt;
            if (!empty($context)) $fullPrompt = "Context:\n{$context}\n\nRequest: {$prompt}";
        }

        $maxTokens = $mode === 'chat' ? 2000 : match($options['length'] ?? 'medium') {
            'brief'  => 500,
            'medium' => 1500,
            'long'   => min(4000, $modelConfig['max_tokens']),
            default  => 1500
        };

        // Action-based temperature — precise tasks get low temp, creative tasks get high temp
        $temperature = match($action) {
            'grammar', 'fact_check'          => 0.3,
            'seo', 'research', 'summarize'   => 0.5,
            'improve', 'expand'              => 0.6,
            'generate', 'brainstorm'         => 0.9,
            default => ($mode === 'chat' ? 0.7 : 0.7),
        };

        switch ($provider) {
            case 'anthropic': $result = callAnthropicAPI($providerConfig['api_key'], $model, $systemMessage, $fullPrompt, $maxTokens, $temperature); break;
            case 'openai':    $result = callOpenAIAPI($providerConfig['api_key'], $model, $systemMessage, $fullPrompt, $maxTokens, $temperature); break;
            case 'google':    $result = callGoogleGeminiAPI($providerConfig['api_key'], $model, $systemMessage, $fullPrompt, $maxTokens, $temperature); break;
            default: throw new Exception('Unsupported AI provider');
        }

        $aiResponse = $result['response'];
        if (empty($aiResponse)) throw new Exception('Empty response from AI. Please try again.');

        echo json_encode([
            'success'    => true,
            'response'   => $aiResponse,
            'provider'   => $provider,
            'model'      => $model,
            'mode'       => $mode,
            'action'     => $action,
            'word_count' => str_word_count($aiResponse),
            'usage'      => $result['usage'],
            'rate_limit' => getAIRateLimitInfo($_SESSION['user_id']),
        ]);
    } catch (Exception $e) {
        error_log("AI Generation Error: " . $e->getMessage());
        echo json_encode(['error' => $e->getMessage(), 'error_type' => get_class($e), 'rate_limit' => getAIRateLimitInfo($_SESSION['user_id'])]);
    }
    exit;
}

// ============================================
// Shared cURL helper with 1-retry on 5xx
// ============================================
function callCurl(string $url, array $headers, array $body, int $timeout = 60): array {
    $encoded = json_encode($body);
    $lastError = 'Request failed';
    for ($attempt = 0; $attempt <= 1; $attempt++) {
        if ($attempt > 0) sleep(1);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $encoded,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($curlError) { $lastError = 'Network error: ' . $curlError; continue; }
        if ($httpCode >= 500 && $attempt < 1) { $lastError = "Server error {$httpCode}"; continue; }
        return [$response, $httpCode];
    }
    throw new Exception($lastError);
}

function callAnthropicAPI(string $apiKey, string $model, string $systemMessage, string $prompt, int $maxTokens, float $temperature): array {
    [$response, $httpCode] = callCurl(
        'https://api.anthropic.com/v1/messages',
        ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'],
        ['model' => $model, 'max_tokens' => $maxTokens, 'system' => $systemMessage,
         'messages' => [['role' => 'user', 'content' => $prompt]], 'temperature' => $temperature]
    );
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $msg = $err['error']['message'] ?? 'API request failed';
        if ($httpCode === 401) throw new Exception('Invalid Anthropic API key');
        if ($httpCode === 429) throw new Exception('Anthropic API rate limit exceeded');
        throw new Exception("Anthropic API Error ({$httpCode}): {$msg}");
    }
    $data = json_decode($response, true);
    return ['response' => $data['content'][0]['text'] ?? '', 'usage' => ['input_tokens' => $data['usage']['input_tokens'] ?? 0, 'output_tokens' => $data['usage']['output_tokens'] ?? 0]];
}

function callOpenAIAPI(string $apiKey, string $model, string $systemMessage, string $prompt, int $maxTokens, float $temperature): array {
    [$response, $httpCode] = callCurl(
        'https://api.openai.com/v1/chat/completions',
        ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        ['model' => $model, 'messages' => [['role' => 'system', 'content' => $systemMessage], ['role' => 'user', 'content' => $prompt]],
         'max_completion_tokens' => $maxTokens, 'temperature' => $temperature]
    );
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $msg = $err['error']['message'] ?? 'API request failed';
        if ($httpCode === 401) throw new Exception('Invalid OpenAI API key');
        if ($httpCode === 429) throw new Exception('OpenAI API rate limit exceeded');
        throw new Exception("OpenAI API Error ({$httpCode}): {$msg}");
    }
    $data = json_decode($response, true);
    return ['response' => $data['choices'][0]['message']['content'] ?? '', 'usage' => ['input_tokens' => $data['usage']['prompt_tokens'] ?? 0, 'output_tokens' => $data['usage']['completion_tokens'] ?? 0]];
}

function callGoogleGeminiAPI(string $apiKey, string $model, string $systemMessage, string $prompt, int $maxTokens, float $temperature): array {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    [$response, $httpCode] = callCurl(
        $url,
        ['Content-Type: application/json'],
        // Gemini 1.5+ proper system_instruction field (not concatenated into user prompt)
        ['system_instruction' => ['parts' => [['text' => $systemMessage]]],
         'contents'           => [['parts' => [['text' => $prompt]]]],
         'generationConfig'   => ['maxOutputTokens' => $maxTokens, 'temperature' => $temperature]]
    );
    if ($httpCode !== 200) {
        $err = json_decode($response, true);
        $msg = $err['error']['message'] ?? 'API request failed';
        if ($httpCode === 400) throw new Exception('Invalid Google API key or request');
        if ($httpCode === 429) throw new Exception('Google API rate limit exceeded');
        throw new Exception("Google API Error ({$httpCode}): {$msg}");
    }
    $data = json_decode($response, true);
    return ['response' => $data['candidates'][0]['content']['parts'][0]['text'] ?? '', 'usage' => ['input_tokens' => $data['usageMetadata']['promptTokenCount'] ?? 0, 'output_tokens' => $data['usageMetadata']['candidatesTokenCount'] ?? 0]];
}

// ============================================
// Handle form submission
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ai_action']) && !isset($_POST['upload_attachment'])) {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $category_id = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
    $department_id = $_SESSION['role'] === 'admin' ? intval($_POST['department_id'] ?? $_SESSION['department_id']) : $_SESSION['department_id'];
    $validationErrors = [];
    if (empty($title)) $validationErrors[] = "Title is required.";
    elseif (strlen($title) < 10) $validationErrors[] = "Title must be at least 10 characters.";
    elseif (strlen($title) > 200) $validationErrors[] = "Title must be less than 200 characters.";
    if (empty($content)) $validationErrors[] = "Content is required.";
    else { $wordCount = str_word_count($content); if ($wordCount < 50) $validationErrors[] = "Content must be at least 50 words. Current: {$wordCount} words."; }
    if (!empty($validationErrors)) $error = implode(' ', $validationErrors);
    $thumbnail = null;
    if (!$error && isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = dirname(__DIR__) . '/uploads/';
        if (!file_exists($uploadDir) && !mkdir($uploadDir, 0755, true)) { $error = "Failed to create upload directory"; }
        if (!$error) {
            $fileTmp = $_FILES['thumbnail']['tmp_name']; $fileName = basename($_FILES['thumbnail']['name']);
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) $error = "Invalid file type. Allowed: jpg, jpeg, png, gif, webp";
            if (!$error) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $mime = finfo_file($finfo, $fileTmp); finfo_close($finfo); if (!str_starts_with($mime, 'image/')) $error = "Invalid image file"; }
            if (!$error) { $newName = uniqid('thumb_', true) . '.' . $ext; if (move_uploaded_file($fileTmp, $uploadDir . $newName)) $thumbnail = 'uploads/' . $newName; else $error = "Failed to move uploaded file."; }
        }
    }
    if (!$error) {
        $stmt = $pdo->prepare("INSERT INTO news (title, content, category_id, department_id, thumbnail, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$title, $content, $category_id, $department_id, $thumbnail, $_SESSION['user_id']]);
        $newsId = $pdo->lastInsertId();
        if (!empty($_POST['attachments'])) {
            $attachments = json_decode($_POST['attachments'], true);
            if (is_array($attachments)) {
                $attachStmt = $pdo->prepare("INSERT INTO attachments (news_id, file_name, file_path, file_size, file_type, uploaded_at, uploaded_by) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                foreach ($attachments as $att) {
                    if (($att['name'] ?? null) && ($att['path'] ?? null) && validateFilePath($att['path']))
                        $attachStmt->execute([$newsId, $att['name'], $att['path'], $att['size'] ?? 0, $att['type'] ?? null, $_SESSION['user_id']]);
                }
            }
        }
        header("Location: ../user_dashboard.php?success=" . urlencode("Article published successfully!"));
        exit;
    }
}
?><!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>AI Writer — New Article</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=Sora:wght@400;500;600;700&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
<style>
:root{
    --purple:#7C3AED;--purple-md:#6D28D9;--purple-dark:#4C1D95;
    --purple-light:#EDE9FE;--purple-pale:#F5F3FF;--purple-glow:rgba(124,58,237,.18);
    --ink:#13111A;--ink-muted:#4A4560;--ink-faint:#8E89A8;
    --canvas:#F3F1FA;--surface:#FFFFFF;--surface-2:#EEEAF8;
    --border:#E2DDEF;--border-md:#C9C2E0;
    --sb:#130F23;--sb2:#1A1535;--sb-txt:#D4CFE8;--sb-muted:#6B6485;
    --sb-act:rgba(124,58,237,.22);--sb-bd:rgba(255,255,255,.07);
    --r:13px;--r-sm:9px;--r-xs:5px;
    --sh:0 1px 3px rgba(60,20,120,.07),0 1px 2px rgba(60,20,120,.04);
    --sh-md:0 4px 16px rgba(60,20,120,.10);
    --sh-lg:0 12px 36px rgba(60,20,120,.16);
    --sh-xl:0 24px 60px rgba(0,0,0,.22);
    --success:#059669;--warn:#D97706;--danger:#DC2626;--info:#2563EB;
    --orange:#F97316;--orange-light:#FFF7ED;--orange-border:#FED7AA;
}
[data-theme="dark"]{
    --ink:#EAE6F8;--ink-muted:#9E98B8;--ink-faint:#635D7A;
    --canvas:#0E0C18;--surface:#17142A;--surface-2:#1E1A30;
    --border:#2A2540;--border-md:#362F50;
    --purple-light:#1E1440;--purple-pale:#150F2E;
    --sb:#0A0815;--sb2:#110D22;
    --sh:0 1px 3px rgba(0,0,0,.4);--sh-md:0 4px 16px rgba(0,0,0,.45);
    --sh-lg:0 12px 36px rgba(0,0,0,.55);--sh-xl:0 24px 60px rgba(0,0,0,.65);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{height:100%;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;text-rendering:optimizeLegibility;font-synthesis:none}
body{height:100%;overflow:hidden;font-family:'Sora',sans-serif;font-size:15px;line-height:1.65;background:var(--canvas);color:var(--ink);transition:background .2s,color .2s}
::-webkit-scrollbar{width:5px;height:5px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-md);border-radius:99px}
::-webkit-scrollbar-thumb:hover{background:var(--purple)}

/* Layout */
.app{display:flex;flex-direction:column;height:100vh;overflow:hidden}
.banner{flex-shrink:0}
.main-wrap{display:flex;flex:1;overflow:hidden;gap:0}

/* Sidebar Panel */
.ai-panel{
    width:340px;flex-shrink:0;background:var(--sb);display:flex;flex-direction:column;
    border-right:1px solid var(--sb-bd);overflow:hidden;
    transition:width .28s cubic-bezier(.4,0,.2,1);
}
.ai-panel.collapsed{width:56px}
.panel-hd{
    padding:14px 16px;border-bottom:1px solid var(--sb-bd);flex-shrink:0;
    display:flex;align-items:center;gap:10px;height:58px;
}
.panel-mark{width:32px;height:32px;background:var(--purple);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.panel-mark .material-icons-round{font-size:17px!important;color:white}
.panel-title{font-family:'Playfair Display',serif;font-size:15px;color:#EAE6F8;font-weight:700;white-space:nowrap;overflow:hidden;opacity:1;transition:opacity .2s}
.ai-panel.collapsed .panel-title{opacity:0;width:0}
.panel-hd-acts{display:flex;gap:6px;margin-left:auto;flex-shrink:0}
.ph-btn{width:30px;height:30px;border-radius:7px;border:none;cursor:pointer;background:rgba(255,255,255,.08);color:var(--sb-txt);display:flex;align-items:center;justify-content:center;transition:background .15s}
.ph-btn:hover{background:rgba(255,255,255,.16)}
.ph-btn .material-icons-round{font-size:16px!important}
.ph-btn-publish{background:var(--purple);color:white;padding:0 12px;width:auto;gap:5px;font-size:12px;font-weight:600;font-family:'Sora',sans-serif;white-space:nowrap}
.ph-btn-publish:hover{background:var(--purple-md)}
.panel-scroll{flex:1;overflow-y:auto;padding:14px 14px 24px;display:flex;flex-direction:column;gap:14px}
.ai-panel.collapsed .panel-scroll{padding:8px;display:flex;flex-direction:column;gap:8px;align-items:center}

/* Panel sections */
.ps-label{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--sb-muted);margin-bottom:6px;display:flex;align-items:center;gap:5px}
.ps-label .material-icons-round{font-size:12px!important}
select.ps-select{
    width:100%;padding:9px 12px;border:1px solid var(--sb-bd);border-radius:var(--r-sm);
    background:rgba(255,255,255,.05);color:var(--sb-txt);font-family:'Sora',sans-serif;
    font-size:12px;outline:none;cursor:pointer;appearance:none;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%236B6485' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 10px center;
    transition:border-color .15s,background .15s;
}
select.ps-select:focus{border-color:var(--purple);background:rgba(124,58,237,.12)}
select.ps-select option{background:var(--sb2);color:var(--sb-txt)}
select.ps-select.ps-select-primary{border-color:rgba(124,58,237,.4);background:rgba(124,58,237,.1)}
.ps-hint{font-size:10px;color:var(--sb-muted);margin-top:4px;font-family:'Fira Code',monospace}

/* Mode toggle */
.mode-toggle{display:flex;background:rgba(255,255,255,.06);border-radius:var(--r-sm);padding:3px;gap:3px}
.mode-btn{flex:1;padding:7px 10px;border-radius:7px;border:none;cursor:pointer;font-family:'Sora',sans-serif;font-size:11px;font-weight:600;color:var(--sb-muted);background:transparent;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:5px}
.mode-btn .material-icons-round{font-size:14px!important}
.mode-btn.active{background:var(--purple);color:white;box-shadow:0 2px 8px rgba(124,58,237,.4)}

/* Rate limit bar */
.rl-bar-wrap{background:rgba(255,255,255,.08);border:1px solid var(--sb-bd);border-radius:var(--r-sm);padding:10px 12px}
.rl-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:7px}
.rl-label{font-size:10px;color:var(--sb-muted);font-family:'Fira Code',monospace}
.rl-value{font-size:11px;font-weight:700;color:var(--sb-txt);font-family:'Fira Code',monospace}
.rl-track{height:4px;background:rgba(255,255,255,.1);border-radius:99px;overflow:hidden}
.rl-fill{height:100%;background:linear-gradient(90deg,var(--purple),#A78BFA);border-radius:99px;transition:width .4s ease}

/* Quick actions grid */
.qa-grid{display:grid;grid-template-columns:1fr 1fr;gap:6px}
.qa-btn{padding:8px 6px;border-radius:var(--r-sm);border:1px solid var(--sb-bd);background:rgba(255,255,255,.04);color:var(--sb-txt);font-size:10px;font-weight:500;cursor:pointer;transition:all .18s;font-family:'Sora',sans-serif;text-align:left;display:flex;align-items:center;gap:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.qa-btn:hover{background:var(--purple);border-color:var(--purple);color:white;transform:translateY(-1px)}

/* Prompt textarea */
.prompt-area{
    width:100%;padding:10px 12px;border:1px solid var(--sb-bd);border-radius:var(--r-sm);
    background:rgba(255,255,255,.04);color:var(--sb-txt);font-family:'Sora',sans-serif;
    font-size:12px;line-height:1.6;resize:none;outline:none;
    transition:border-color .15s,background .15s;
}
.prompt-area:focus{border-color:var(--purple);background:rgba(124,58,237,.08)}
.prompt-area::placeholder{color:var(--sb-muted)}

/* Settings row */
.settings-row{display:flex;gap:8px}
.settings-row > div{flex:1}

/* Generate button */
.gen-btn{
    width:100%;padding:11px;border-radius:var(--r-sm);border:none;cursor:pointer;
    background:linear-gradient(135deg,var(--purple),var(--purple-md));color:white;
    font-family:'Sora',sans-serif;font-size:13px;font-weight:600;
    display:flex;align-items:center;justify-content:center;gap:7px;
    transition:all .2s;box-shadow:0 4px 12px var(--purple-glow);
}
.gen-btn:hover{transform:translateY(-1px);box-shadow:0 6px 20px var(--purple-glow)}
.gen-btn:active{transform:translateY(0)}
.gen-btn:disabled{opacity:.55;cursor:not-allowed;transform:none}
.gen-btn .material-icons-round{font-size:18px!important}

/* Loading indicator */
.ai-loading{background:rgba(124,58,237,.12);border:1px solid rgba(124,58,237,.25);border-radius:var(--r-sm);padding:12px;display:flex;align-items:center;gap:10px}
.spin-ring{width:18px;height:18px;border:2px solid rgba(124,58,237,.3);border-top-color:var(--purple);border-radius:50%;animation:spin .8s linear infinite;flex-shrink:0}
@keyframes spin{to{transform:rotate(360deg)}}
.ai-loading-txt{font-size:11px;color:var(--sb-txt);font-family:'Fira Code',monospace;flex:1}
.cancel-btn{padding:4px 10px;border-radius:6px;border:1px solid rgba(239,68,68,.4);background:rgba(239,68,68,.15);color:#FCA5A5;font-family:'Sora',sans-serif;font-size:10px;font-weight:600;cursor:pointer;transition:background .15s;white-space:nowrap}
.cancel-btn:hover{background:rgba(239,68,68,.3)}

/* Context toggle */
.ctx-toggle{display:flex;align-items:center;gap:5px;font-size:10px;color:var(--sb-muted);cursor:pointer;margin-top:5px;user-select:none}
.ctx-toggle input[type=checkbox]{accent-color:var(--purple);width:12px;height:12px;cursor:pointer}
.ctx-toggle .material-icons-round{font-size:12px!important}

/* Response box */
.response-wrap{background:rgba(255,255,255,.04);border:1px solid var(--sb-bd);border-radius:var(--r-sm);overflow:hidden}
.response-hd{padding:10px 12px;border-bottom:1px solid var(--sb-bd);display:flex;align-items:center;justify-content:space-between}
.response-hd-l{display:flex;align-items:center;gap:7px;font-size:11px;font-weight:600;color:var(--sb-txt)}
.response-hd-l .material-icons-round{font-size:15px!important;color:var(--purple)}
.r-badge{padding:2px 8px;border-radius:99px;font-size:9px;font-weight:700;font-family:'Fira Code',monospace}
.r-badge-prov{background:rgba(167,139,250,.2);color:#A78BFA}
.r-badge-words{background:rgba(124,58,237,.2);color:#A78BFA}
/* Markdown rendering — white-space:pre-wrap removed; markdown provides its own structure */
.response-body{padding:12px;font-size:12px;line-height:1.7;color:var(--sb-txt);max-height:300px;overflow-y:auto;font-family:'Sora',sans-serif}
.response-body p{margin:.35em 0}
.response-body h1,.response-body h2,.response-body h3{font-family:'Playfair Display',serif;color:var(--sb-txt);margin:.6em 0 .25em;font-weight:700}
.response-body h1{font-size:14px}.response-body h2{font-size:13px}.response-body h3{font-size:12px}
.response-body ul,.response-body ol{padding-left:1.3em;margin:.3em 0}
.response-body li{margin-bottom:3px}
.response-body strong{font-weight:700}
.response-body em{font-style:italic}
.response-body code{font-family:'Fira Code',monospace;font-size:11px;background:rgba(255,255,255,.1);padding:1px 5px;border-radius:3px}
.response-body pre{background:rgba(0,0,0,.25);padding:10px 12px;border-radius:var(--r-sm);overflow-x:auto;margin:.5em 0}
.response-body pre code{background:none;padding:0}
.response-body blockquote{border-left:3px solid var(--purple);padding-left:10px;color:var(--sb-muted);margin:.4em 0}
.response-body a{color:#A78BFA;text-decoration:underline}
.response-acts{padding:10px 12px;border-top:1px solid var(--sb-bd);display:flex;gap:5px;flex-wrap:wrap}
.r-act{flex:1;min-width:44px;padding:7px 5px;border-radius:var(--r-sm);border:none;cursor:pointer;font-size:10px;font-weight:600;font-family:'Sora',sans-serif;display:flex;align-items:center;justify-content:center;gap:4px;transition:all .15s}
.r-act .material-icons-round{font-size:13px!important}
.r-act-copy{background:rgba(255,255,255,.08);color:var(--sb-txt)}.r-act-copy:hover{background:rgba(255,255,255,.14)}
.r-act-insert{background:rgba(16,185,129,.2);color:#34D399}.r-act-insert:hover{background:rgba(16,185,129,.3)}
.r-act-replace{background:rgba(249,115,22,.2);color:#FB923C}.r-act-replace:hover{background:rgba(249,115,22,.3)}
.r-act-regen{background:rgba(124,58,237,.2);color:#A78BFA}.r-act-regen:hover{background:rgba(124,58,237,.35)}

/* Chat mode panel */
.chat-info{background:linear-gradient(135deg,rgba(124,58,237,.15),rgba(139,92,246,.1));border:1px solid rgba(124,58,237,.25);border-radius:var(--r-sm);padding:12px}
.chat-info-title{font-size:12px;font-weight:700;color:var(--sb-txt);margin-bottom:6px;display:flex;align-items:center;gap:6px}
.chat-info-title .material-icons-round{font-size:15px!important;color:#A78BFA}
.chat-info ul{list-style:none;font-size:11px;color:var(--sb-muted);line-height:1.9}
.chat-info ul li::before{content:'→ ';color:var(--purple)}

/* ═══ EDITOR PANE ═══ */
.editor-pane{
    flex:1;display:flex;flex-direction:column;overflow:hidden;
    background:var(--surface);
}

/* Editor topbar */
.ed-topbar{
    padding:0 28px;height:58px;border-bottom:1px solid var(--border);
    display:flex;align-items:center;justify-content:space-between;gap:14px;flex-shrink:0;
    background:var(--surface);box-shadow:var(--sh);
}
.ed-tb-l{display:flex;align-items:center;gap:12px}
.ed-tb-r{display:flex;align-items:center;gap:8px}
.back-btn{
    display:flex;align-items:center;gap:6px;padding:7px 12px;border-radius:var(--r-sm);
    border:1px solid var(--border);background:transparent;color:var(--ink-muted);
    cursor:pointer;font-family:'Sora',sans-serif;font-size:12px;font-weight:500;
    transition:all .15s;text-decoration:none;
}
.back-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.back-btn .material-icons-round{font-size:16px!important}
.ed-title-tag{font-family:'Playfair Display',serif;font-size:16px;color:var(--ink)}
.ed-sub-tag{font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:1px}
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:var(--r-sm);border:none;cursor:pointer;font-family:'Sora',sans-serif;font-size:12px;font-weight:600;transition:all .15s;text-decoration:none;white-space:nowrap}
.btn .material-icons-round{font-size:15px!important}
.btn-outline{background:transparent;border:1px solid var(--border);color:var(--ink-muted)}.btn-outline:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.btn-purple{background:var(--purple);color:white}.btn-purple:hover{background:var(--purple-md);box-shadow:0 4px 12px var(--purple-glow)}
.btn-icon{width:34px;height:34px;padding:0;justify-content:center}

/* Stats bar */
.stats-bar{
    padding:8px 28px;border-bottom:1px solid var(--border);
    display:flex;gap:18px;align-items:center;flex-wrap:wrap;flex-shrink:0;
    background:var(--canvas);
}
.stat-chip{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.stat-chip strong{color:var(--ink);font-weight:600}
.stat-chip .material-icons-round{font-size:13px!important;color:var(--purple)}
.stat-chip.warning strong{color:#D97706}
.stat-chip.ok strong{color:#059669}

/* Editor scroll */
.editor-scroll{flex:1;overflow-y:auto;padding:28px 40px 80px}
@media(max-width:900px){.editor-scroll{padding:18px 18px 60px}}

/* Form elements */
.art-title-input{
    width:100%;border:none;background:transparent;outline:none;
    font-family:'Playfair Display',serif;font-size:30px;font-weight:700;
    color:var(--ink);line-height:1.3;padding:0;
}
.art-title-input::placeholder{color:var(--border-md)}
[data-theme="dark"] .art-title-input::placeholder{color:var(--border);opacity:.6}

.form-row{margin-bottom:20px}
.form-label{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--ink);margin-bottom:7px}
.form-label .material-icons-round{font-size:15px!important;color:var(--purple)}
.form-select{
    width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--r-sm);
    background:var(--canvas);color:var(--ink);font-family:'Sora',sans-serif;font-size:13px;
    appearance:none;outline:none;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24'%3E%3Cpath fill='%238E89A8' d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;
    transition:border-color .15s,box-shadow .15s;
}
.form-select:focus{border-color:var(--purple);box-shadow:0 0 0 3px var(--purple-glow)}

/* Thumbnail area */
.thumb-btn{
    display:inline-flex;align-items:center;gap:7px;padding:8px 14px;
    border:1px dashed var(--border-md);border-radius:var(--r-sm);background:transparent;
    color:var(--ink-faint);cursor:pointer;font-family:'Sora',sans-serif;font-size:12px;
    font-weight:500;transition:all .2s;
}
.thumb-btn:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.thumb-btn .material-icons-round{font-size:17px!important}
.thumb-preview{position:relative;display:inline-flex;flex-direction:column;gap:6px;margin-top:10px}
.thumb-preview img{width:160px;height:160px;object-fit:cover;border-radius:var(--r);border:2px solid var(--border);box-shadow:var(--sh-md)}
.thumb-remove{
    position:absolute;top:-8px;right:-8px;width:24px;height:24px;border-radius:50%;
    background:#DC2626;color:white;border:none;cursor:pointer;
    display:flex;align-items:center;justify-content:center;box-shadow:var(--sh);transition:background .15s;
}
.thumb-remove:hover{background:#B91C1C}
.thumb-remove .material-icons-round{font-size:13px!important}
.thumb-caption{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace}

/* Content area */
.content-area{
    width:100%;min-height:300px;border:none;background:transparent;outline:none;resize:none;
    font-family:'Sora',sans-serif;font-size:15px;line-height:1.85;color:var(--ink-muted);padding:0;
}
.content-area::placeholder{color:var(--border-md)}
[data-theme="dark"] .content-area::placeholder{color:var(--border);opacity:.5}

/* Divider */
.ed-divider{height:1px;background:linear-gradient(to right,transparent,var(--border),transparent);margin:16px 0}

/* Attachments section */
.attach-list{display:flex;flex-direction:column;gap:6px;margin-top:10px}
.attach-item{
    display:flex;align-items:center;gap:10px;padding:10px 12px;
    background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm);
    animation:attachIn .2s ease;
}
@keyframes attachIn{from{opacity:0;transform:translateX(-6px)}to{opacity:1;transform:none}}
.attach-item .material-icons-round{font-size:20px!important;color:var(--purple);flex-shrink:0}
.attach-name{font-size:12px;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1}
.attach-size{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace}
.attach-rm{width:26px;height:26px;border-radius:6px;border:none;background:transparent;color:var(--ink-faint);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0}
.attach-rm:hover{background:#FFF1F2;color:#DC2626}
.attach-rm .material-icons-round{font-size:16px!important}

/* Banners */
.warn-banner{
    background:linear-gradient(135deg,#FFF7ED,#FFFBEB);border-bottom:1px solid #FED7AA;
    padding:10px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;
    flex-shrink:0;
}
.warn-banner .material-icons-round{font-size:17px!important;color:#D97706;flex-shrink:0}
.warn-txt{font-size:12px;color:#92400E;flex:1}
.warn-link{font-size:12px;font-weight:600;color:#92400E;text-decoration:none;border-bottom:1px solid #D97706}
.warn-link:hover{color:#78350F}
.err-banner{background:#FFF1F2;border-bottom:1px solid #FECDD3;padding:10px 20px;display:flex;align-items:center;gap:10px;flex-shrink:0}
.err-banner .material-icons-round{font-size:17px!important;color:#DC2626;flex-shrink:0}
.err-banner p{font-size:12px;color:#9F1239}
[data-theme="dark"] .warn-banner{background:linear-gradient(135deg,#1A1005,#1A1500);border-color:#431407}
[data-theme="dark"] .warn-txt,[data-theme="dark"] .warn-link{color:#FCD34D}
[data-theme="dark"] .err-banner{background:#1F0A0A;border-color:#7F1D1D}
[data-theme="dark"] .err-banner p{color:#FCA5A5}

/* ═══ MODALS ═══ */
.modal-bg{display:none;position:fixed;inset:0;background:rgba(0,0,0,.72);backdrop-filter:blur(6px);z-index:50;align-items:center;justify-content:center;padding:20px}
.modal-bg.open{display:flex;animation:fadeBg .2s ease}
@keyframes fadeBg{from{opacity:0}to{opacity:1}}
.modal-box{background:var(--surface);border-radius:16px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--sh-xl);animation:modalIn .25s cubic-bezier(.4,0,.2,1)}
@keyframes modalIn{from{transform:translateY(12px) scale(.98);opacity:0}to{transform:none;opacity:1}}
.m-hd{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0}
.m-hi{display:flex;align-items:center;gap:10px}
.m-hi-icon{width:36px;height:36px;border-radius:9px;display:flex;align-items:center;justify-content:center}
.m-hi-title{font-family:'Playfair Display',serif;font-size:17px;color:var(--ink)}
.m-hi-sub{font-size:10px;color:var(--ink-faint);font-family:'Fira Code',monospace;margin-top:2px}
.m-close{width:30px;height:30px;border-radius:50%;border:1px solid var(--border);background:transparent;cursor:pointer;color:var(--ink-faint);display:flex;align-items:center;justify-content:center;transition:all .15s}
.m-close:hover{background:var(--canvas);color:var(--ink);border-color:var(--purple)}
.m-close .material-icons-round{font-size:16px!important}
.m-scroll{overflow-y:auto;flex:1}
.m-body{padding:20px 24px}
.m-foot{padding:14px 22px;border-top:1px solid var(--border);background:var(--canvas);flex-shrink:0;display:flex;gap:8px;justify-content:flex-end}

/* Drop zone */
.drop-zone{
    border:2px dashed var(--border-md);border-radius:var(--r);padding:40px 24px;
    text-align:center;cursor:pointer;transition:all .2s;
}
.drop-zone:hover,.drop-zone.dragging{border-color:var(--purple);background:var(--purple-pale)}
.dz-icon{width:64px;height:64px;border-radius:50%;background:var(--purple-light);display:flex;align-items:center;justify-content:center;margin:0 auto 14px}
.dz-icon .material-icons-round{font-size:30px!important;color:var(--purple)}
.dz-title{font-family:'Playfair Display',serif;font-size:16px;color:var(--ink);margin-bottom:6px}
.dz-sub{font-size:12px;color:var(--ink-faint);line-height:1.6}

/* Upload progress */
.up-prog{background:var(--purple-pale);border:1px solid #C4B5FD;border-radius:var(--r-sm);padding:12px 16px;display:flex;align-items:center;gap:12px}
.up-prog .material-icons-round{font-size:16px!important;color:var(--purple-md)}

/* Setup modal */
.setup-provider{border-radius:var(--r-sm);padding:14px 16px;margin-bottom:12px}
.setup-provider h3{font-size:14px;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:7px}
.setup-provider ol{list-style:decimal inside;font-size:12px;color:var(--ink-muted);line-height:1.9}
.setup-provider a{color:var(--purple);text-decoration:none}
.setup-provider a:hover{text-decoration:underline}
.setup-provider code{background:var(--canvas);padding:1px 5px;border-radius:3px;font-family:'Fira Code',monospace;font-size:11px}
.prov-anthropic{background:#EFF6FF;border:1px solid #BFDBFE}
.prov-openai{background:#ECFDF5;border:1px solid #A7F3D0}
.prov-google{background:var(--purple-pale);border:1px solid #C4B5FD}
[data-theme="dark"] .prov-anthropic{background:#0E1829;border-color:#1E3A5F}
[data-theme="dark"] .prov-openai{background:#052E1C;border-color:#065F46}
[data-theme="dark"] .prov-google{background:var(--purple-pale);border-color:#2A1A5E}

/* ═══ TOASTS ═══ */
.toast-stack{position:fixed;top:70px;right:16px;z-index:9999;display:flex;flex-direction:column;gap:6px;pointer-events:none}
.toast{display:flex;align-items:center;gap:9px;padding:10px 14px;border-radius:var(--r-sm);font-family:'Sora',sans-serif;font-size:12px;font-weight:500;min-width:220px;max-width:320px;box-shadow:var(--sh-lg);pointer-events:all;animation:toastIn .22s ease;border:1px solid}
@keyframes toastIn{from{transform:translateX(10px);opacity:0}to{transform:none;opacity:1}}
.toast.success{background:#ECFDF5;color:#065F46;border-color:#A7F3D0}
.toast.error{background:#FFF1F2;color:#9F1239;border-color:#FECDD3}
.toast.warning{background:#FFFBEB;color:#92400E;border-color:#FDE68A}
.toast.info{background:var(--purple-pale);color:var(--purple-md);border-color:#C4B5FD}
.toast .material-icons-round{font-size:16px!important;flex-shrink:0}
.toast-msg{flex:1}
.toast-x{cursor:pointer;opacity:.5;font-size:14px;line-height:1}.toast-x:hover{opacity:1}

/* ═══ SHAKE ANIMATION ═══ */
@keyframes shake{0%,100%{transform:translateX(0)}10%,30%,50%,70%,90%{transform:translateX(-5px)}20%,40%,60%,80%{transform:translateX(5px)}}
.shake{animation:shake .5s ease-in-out}

/* ═══ COLLAPSED SIDEBAR ICONS ═══ */
.collapsed-icons{display:none;flex-direction:column;gap:8px;align-items:center}
.ai-panel.collapsed .collapsed-icons{display:flex}
.ci-btn{width:38px;height:38px;border-radius:9px;border:none;cursor:pointer;background:rgba(255,255,255,.07);color:var(--sb-muted);display:flex;align-items:center;justify-content:center;transition:all .15s;position:relative}
.ci-btn:hover{background:var(--sb-act);color:var(--sb-txt)}
.ci-btn .material-icons-round{font-size:18px!important}
.ci-btn.active{background:var(--purple);color:white}
.ci-tooltip{
    position:absolute;left:calc(100% + 8px);top:50%;transform:translateY(-50%);
    background:var(--sb2);color:var(--sb-txt);padding:4px 10px;border-radius:6px;
    font-size:11px;font-weight:500;font-family:'Sora',sans-serif;white-space:nowrap;
    pointer-events:none;opacity:0;transition:opacity .15s;border:1px solid var(--sb-bd);z-index:100;
}
.ci-btn:hover .ci-tooltip{opacity:1}

/* ═══ RESPONSIVE ═══ */
@media(max-width:768px){
    .ai-panel{position:fixed;top:0;left:0;height:100%;z-index:40;width:320px;transform:translateX(-100%);transition:transform .28s cubic-bezier(.4,0,.2,1)}
    .ai-panel.mobile-open{transform:none}
    .ai-panel.collapsed{width:320px}
    .ed-topbar{padding:0 16px}
    .stats-bar{padding:8px 16px;gap:12px}
    .editor-scroll{padding:16px 16px 60px}
    .art-title-input{font-size:22px}
    .mobile-panel-toggle{display:flex!important}
}
.mobile-panel-toggle{display:none;width:34px;height:34px;border-radius:var(--r-sm);border:1px solid var(--border);background:transparent;color:var(--ink-muted);cursor:pointer;align-items:center;justify-content:center;transition:all .15s}
.mobile-panel-toggle:hover{border-color:var(--purple);color:var(--purple);background:var(--purple-pale)}
.mobile-panel-toggle .material-icons-round{font-size:18px!important}
.mob-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:35;backdrop-filter:blur(3px)}
.mob-overlay.show{display:block}

/* ═══ PRINT ═══ */
@media print{.ai-panel,.ed-topbar,.stats-bar{display:none!important}.editor-pane{overflow:visible}.editor-scroll{padding:0;overflow:visible}}
</style>
<!-- Markdown renderer + sanitiser — defer so they NEVER block page initialisation -->
<script defer src="https://cdn.jsdelivr.net/npm/marked@12/marked.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
</head>
<body>

<script>const AI_CONFIG = <?= json_encode(['enabled' => AI_ASSISTANT_ENABLED, 'providers' => AI_PROVIDERS, 'enabled_providers' => ENABLED_PROVIDERS ?? [], 'default_provider' => DEFAULT_AI_PROVIDER ?? 'anthropic']) ?>;</script>

<div class="app">
    <!-- Banners -->
    <?php if (!AI_ASSISTANT_ENABLED): ?>
    <div class="warn-banner banner">
        <span class="material-icons-round">warning</span>
        <p class="warn-txt"><strong>AI Assistant not configured.</strong> Add your API keys to <code style="background:rgba(0,0,0,.07);padding:1px 5px;border-radius:3px;font-family:monospace">config.php</code> to enable AI features.</p>
        <a href="#" onclick="document.getElementById('setupModal').classList.add('open');return false" class="warn-link">Setup Guide</a>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="err-banner banner">
        <span class="material-icons-round">error</span>
        <p><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <div class="main-wrap">
        <!-- Mobile overlay -->
        <div class="mob-overlay" id="mobOverlay"></div>

        <!-- ═══ AI PANEL ═══ -->
        <aside class="ai-panel" id="aiPanel">
            <div class="panel-hd">
                <div class="panel-mark"><span class="material-icons-round">auto_awesome</span></div>
                <span class="panel-title">AI Writer</span>
                <div class="panel-hd-acts">
                    <button class="ph-btn" id="collapseBtn" title="Collapse panel">
                        <span class="material-icons-round">chevron_left</span>
                    </button>
                    <button class="ph-btn" id="attachPanelBtn" title="Attachments">
                        <span class="material-icons-round">attach_file</span>
                    </button>
                    <button class="ph-btn ph-btn-publish" id="publishBtn">
                        <span class="material-icons-round">publish</span>Publish
                    </button>
                </div>
            </div>

            <!-- Collapsed state icons -->
            <div class="collapsed-icons" id="collapsedIcons">
                <button class="ci-btn" id="expandBtn" title="Expand panel">
                    <span class="material-icons-round">chevron_right</span>
                    <span class="ci-tooltip">Expand AI Panel</span>
                </button>
                <button class="ci-btn active" onclick="toggleCollapse()" title="AI Writing">
                    <span class="material-icons-round">auto_awesome</span>
                    <span class="ci-tooltip">AI Writing</span>
                </button>
                <button class="ci-btn" onclick="document.getElementById('attachmentsModal').classList.add('open')" title="Attachments">
                    <span class="material-icons-round">attach_file</span>
                    <span class="ci-tooltip">Attachments</span>
                </button>
                <button class="ci-btn" id="publishBtnCollapsed" title="Publish">
                    <span class="material-icons-round">publish</span>
                    <span class="ci-tooltip">Publish Article</span>
                </button>
                <button class="ci-btn" onclick="toggleDark()" title="Toggle theme">
                    <span class="material-icons-round" id="darkIconCollapsed">dark_mode</span>
                    <span class="ci-tooltip">Toggle Dark Mode</span>
                </button>
            </div>

            <div class="panel-scroll" id="panelScroll">
                <!-- Rate Limit -->
                <?php if (AI_ASSISTANT_ENABLED): ?>
                <div class="rl-bar-wrap">
                    <div class="rl-row">
                        <span class="rl-label">API Usage (hourly)</span>
                        <span class="rl-value" id="rateLimitBadge"><?= $rateLimitInfo['remaining'] ?> / <?= $rateLimitInfo['limit'] ?> left</span>
                    </div>
                    <div class="rl-track">
                        <div class="rl-fill" id="rateLimitBar" style="width:<?= ($rateLimitInfo['remaining'] / $rateLimitInfo['limit'] * 100) ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Provider -->
                <div>
                    <div class="ps-label"><span class="material-icons-round">token</span>AI Provider</div>
                    <select id="aiProvider" class="ps-select ps-select-primary"></select>
                </div>

                <!-- Model -->
                <div>
                    <div class="ps-label"><span class="material-icons-round">memory</span>Model</div>
                    <select id="aiModel" class="ps-select"></select>
                    <div class="ps-hint" id="modelDescription">Select a provider to see available models</div>
                </div>

                <!-- Mode -->
                <div>
                    <div class="ps-label"><span class="material-icons-round">psychology</span>Assistant Mode</div>
                    <div class="mode-toggle">
                        <button class="mode-btn active" data-mode="writing" onclick="setMode('writing')">
                            <span class="material-icons-round">edit_note</span>Writing
                        </button>
                        <button class="mode-btn" data-mode="chat" onclick="setMode('chat')">
                            <span class="material-icons-round">forum</span>Chat
                        </button>
                    </div>
                </div>

                <!-- Writing mode actions -->
                <div id="writingModePanel">
                    <div class="ps-label">Action</div>
                    <select id="aiAction" class="ps-select">
                        <option value="generate">✨ Generate Content</option>
                        <option value="improve">📈 Improve Writing</option>
                        <option value="summarize">📋 Summarize</option>
                        <option value="expand">📏 Expand Content</option>
                        <option value="grammar">✔️ Fix Grammar</option>
                        <option value="seo">🔍 SEO Optimize</option>
                        <option value="brainstorm">💡 Brainstorm Ideas</option>
                        <option value="research">🔬 Research Topic</option>
                        <option value="fact_check">✅ Fact Check</option>
                    </select>
                </div>

                <!-- Chat mode panel -->
                <div id="chatModePanel" style="display:none">
                    <div class="chat-info">
                        <div class="chat-info-title"><span class="material-icons-round">chat</span>Ask Me Anything</div>
                        <ul>
                            <li>General questions &amp; research</li>
                            <li>Story ideas &amp; angles</li>
                            <li>Technical explanations</li>
                            <li>Fact checking &amp; verification</li>
                            <li>Interview questions</li>
                        </ul>
                    </div>
                    <div style="margin-top:10px">
                        <div class="ps-label">Quick Actions</div>
                        <div class="qa-grid">
                            <button class="qa-btn" data-prompt="What are the latest statistics on "><span class="material-icons-round" style="font-size:12px!important;flex-shrink:0">bar_chart</span>Statistics</button>
                            <button class="qa-btn" data-prompt="Give me 10 story angles about "><span class="material-icons-round" style="font-size:12px!important;flex-shrink:0">lightbulb</span>Story Ideas</button>
                            <button class="qa-btn" data-prompt="What questions should I ask about "><span class="material-icons-round" style="font-size:12px!important;flex-shrink:0">contact_support</span>Interview Qs</button>
                            <button class="qa-btn" data-prompt="Fact check: "><span class="material-icons-round" style="font-size:12px!important;flex-shrink:0">fact_check</span>Fact Check</button>
                        </div>
                    </div>
                </div>

                <!-- Prompt -->
                <div>
                    <div class="ps-label" id="promptLabel"><span class="material-icons-round">edit</span>Your Prompt</div>
                    <textarea id="aiPrompt" class="prompt-area" rows="4" placeholder="Describe what you want AI to help with…"></textarea>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:5px">
                        <div class="ps-hint" style="display:flex;align-items:center;gap:4px">
                            <span class="material-icons-round" style="font-size:11px!important">keyboard</span>
                            Ctrl+Enter to generate
                        </div>
                        <label class="ctx-toggle" title="Send article title + content as context">
                            <input type="checkbox" id="includeContext" checked>
                            <span class="material-icons-round">article</span>
                            Include context
                        </label>
                    </div>
                </div>

                <!-- Settings row -->
                <div id="writingSettings" class="settings-row">
                    <div>
                        <div class="ps-label">Tone</div>
                        <select id="aiTone" class="ps-select">
                            <option value="">Default</option>
                            <option value="professional">Professional</option>
                            <option value="casual">Casual</option>
                            <option value="journalistic">News Style</option>
                        </select>
                    </div>
                    <div>
                        <div class="ps-label">Length</div>
                        <select id="aiLength" class="ps-select">
                            <option value="brief">Short</option>
                            <option value="medium" selected>Medium</option>
                            <option value="long">Long</option>
                        </select>
                    </div>
                </div>

                <!-- Generate button -->
                <button id="generateBtn" class="gen-btn">
                    <span class="material-icons-round">auto_awesome</span>
                    <span id="generateBtnText">Generate</span>
                </button>

                <!-- Loading -->
                <div id="aiLoading" style="display:none">
                    <div class="ai-loading">
                        <div class="spin-ring"></div>
                        <span class="ai-loading-txt">Generating response…</span>
                        <button id="cancelBtn" class="cancel-btn" onclick="aiState.controller?.abort()" style="display:none">
                            Cancel
                        </button>
                    </div>
                </div>

                <!-- Response -->
                <div id="aiResponseBox" style="display:none">
                    <div class="response-wrap">
                        <div class="response-hd">
                            <div class="response-hd-l">
                                <span class="material-icons-round">smart_toy</span>
                                AI Response
                            </div>
                            <div style="display:flex;gap:5px">
                                <span id="providerBadge" class="r-badge r-badge-prov"></span>
                                <span id="aiWordCount" class="r-badge r-badge-words"></span>
                            </div>
                        </div>
                        <div id="aiResponseContent" class="response-body"></div>
                        <div class="response-acts">
                            <button id="copyResponse" class="r-act r-act-copy">
                                <span class="material-icons-round">content_copy</span>Copy
                            </button>
                            <button id="insertResponse" class="r-act r-act-insert">
                                <span class="material-icons-round">add</span>Insert
                            </button>
                            <button id="replaceResponse" class="r-act r-act-replace">
                                <span class="material-icons-round">sync</span>Replace
                            </button>
                            <button id="regenerateBtn" class="r-act r-act-regen" onclick="regenResponse()" style="display:none" title="Regenerate with same prompt">
                                <span class="material-icons-round">refresh</span>Regen
                            </button>
                        </div>
                    </div>
                </div>

                <div style="padding-top:4px;border-top:1px solid var(--sb-bd)">
                    <p style="font-size:10px;color:var(--sb-muted);text-align:center;font-family:'Fira Code',monospace">
                        Multi-provider AI · <?= count(ENABLED_PROVIDERS ?? []) ?> provider<?= count(ENABLED_PROVIDERS ?? []) !== 1 ? 's' : '' ?> configured
                    </p>
                </div>
            </div>
        </aside>

        <!-- ═══ EDITOR PANE ═══ -->
        <div class="editor-pane">
            <!-- Topbar -->
            <header class="ed-topbar">
                <div class="ed-tb-l">
                    <button class="mobile-panel-toggle" id="mobilePanelToggle">
                        <span class="material-icons-round">auto_awesome</span>
                    </button>
                    <a href="../user_dashboard.php" class="back-btn">
                        <span class="material-icons-round">arrow_back</span>
                        Dashboard
                    </a>
                    <div>
                        <div class="ed-title-tag">New Article</div>
                        <div class="ed-sub-tag">AI-powered writing tool</div>
                    </div>
                </div>
                <div class="ed-tb-r">
                    <button onclick="toggleDark()" class="btn btn-outline btn-icon" id="darkBtnTop">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                    <button id="attachBtnTop" class="btn btn-outline">
                        <span class="material-icons-round">attach_file</span>
                        <span>Attach</span>
                        <span id="attachBadgeTop" style="display:none;background:var(--purple);color:white;font-size:9px;font-weight:700;padding:2px 6px;border-radius:99px;font-family:'Fira Code',monospace" id="attachBadge">0</span>
                    </button>
                    <button id="publishBtnTop" class="btn btn-purple">
                        <span class="material-icons-round">publish</span>
                        Publish
                    </button>
                </div>
            </header>

            <!-- Stats bar -->
            <div class="stats-bar" id="statsBar">
                <div class="stat-chip" id="wordChip">
                    <span class="material-icons-round">short_text</span>
                    <strong id="wordCount">0</strong> words
                </div>
                <div class="stat-chip">
                    <span class="material-icons-round">text_fields</span>
                    <strong id="charCount">0</strong> chars
                </div>
                <div class="stat-chip">
                    <span class="material-icons-round">schedule</span>
                    <strong id="readTime">0</strong> min read
                </div>
                <div class="stat-chip" id="attachChip" style="display:none">
                    <span class="material-icons-round">attachment</span>
                    <strong id="attachCount">0</strong> files
                </div>
                <div id="draftIndicator" style="display:none;margin-left:auto" class="stat-chip">
                    <span class="material-icons-round" style="color:#059669">cloud_done</span>Draft saved
                </div>
            </div>

            <!-- Editor scroll -->
            <div class="editor-scroll">
                <form id="articleForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>"/>
                    <input type="hidden" name="department_id" id="departmentId" value="<?= $_SESSION['department_id'] ?>"/>
                    <input type="hidden" name="attachments" id="attachmentsData" value=""/>

                    <!-- Title -->
                    <div style="margin-bottom:22px">
                        <input id="articleTitle" name="title" type="text"
                               class="art-title-input"
                               placeholder="Article Title…"
                               autocomplete="off"/>
                        <div style="height:2px;background:linear-gradient(to right,var(--purple),transparent);margin-top:6px;border-radius:1px;opacity:.4"></div>
                    </div>

                    <!-- Thumbnail -->
                    <div class="form-row">
                        <input type="file" id="thumbnailInput" name="thumbnail" accept="image/*" style="display:none"/>
                        <button type="button" id="thumbnailBtn" class="thumb-btn">
                            <span class="material-icons-round">add_photo_alternate</span>
                            Add Thumbnail
                        </button>
                        <div id="thumbnailPreview" class="thumb-preview" style="display:none">
                            <img src="" alt="Thumbnail preview"/>
                            <button type="button" id="removeThumbnail" class="thumb-remove">
                                <span class="material-icons-round">close</span>
                            </button>
                            <span class="thumb-caption">Thumbnail preview — 160×160px display</span>
                        </div>
                    </div>

                    <!-- Category -->
                    <div class="form-row">
                        <label class="form-label" for="categorySelect">
                            <span class="material-icons-round">label</span>
                            Category
                        </label>
                        <?php if (empty($categories)): ?>
                        <div style="padding:10px 14px;background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm);font-size:12px;color:var(--ink-muted)">
                            No categories available. Please contact an administrator.
                        </div>
                        <?php else: ?>
                        <select id="categorySelect" name="category_id" class="form-select">
                            <option value="">Choose a category…</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
                    </div>

                    <div class="ed-divider"></div>

                    <!-- Attachments list in editor -->
                    <div id="attachmentsList" style="display:none;margin-bottom:20px">
                        <label class="form-label">
                            <span class="material-icons-round">attachment</span>
                            Attachments
                        </label>
                        <div id="attachmentsContainer" class="attach-list"></div>
                    </div>

                    <!-- Content -->
                    <textarea id="articleContent" name="content"
                              class="content-area"
                              placeholder="Start writing your article…&#10;&#10;Tip: Use the AI panel on the left to generate, improve, or expand your content."></textarea>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Toast stack -->
<div class="toast-stack" id="toastStack"></div>

<!-- ═══ MODALS ═══ -->

<!-- Attachments Modal -->
<div class="modal-bg" id="attachmentsModal">
    <div class="modal-box" style="max-width:640px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-icon" style="background:var(--purple-light)"><span class="material-icons-round" style="color:var(--purple)">attach_file</span></div>
                <div><div class="m-hi-title">File Attachments</div><div class="m-hi-sub">Upload documents, images, and media</div></div>
            </div>
            <button class="m-close" onclick="closeModal('attachmentsModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <div class="drop-zone" id="dropZone">
                    <input type="file" id="fileInput" style="display:none" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.7z,.jpg,.jpeg,.png,.gif,.webp,.svg,.mp4,.avi,.mov,.mp3,.wav"/>
                    <div class="dz-icon"><span class="material-icons-round">cloud_upload</span></div>
                    <div class="dz-title">Drop files here or click to browse</div>
                    <div class="dz-sub">PDF, Word, Excel, PowerPoint, Images, Videos, Audio, Archives<br/>Maximum 50MB per file</div>
                </div>
                <div id="uploadProgress" style="display:none;margin-top:12px">
                    <div class="up-prog">
                        <span class="material-icons-round" style="animation:spin .8s linear infinite">refresh</span>
                        <div>
                            <p style="font-size:12px;font-weight:600;color:var(--ink)">Uploading files…</p>
                            <p style="font-size:11px;color:var(--ink-faint);font-family:'Fira Code',monospace" id="uploadStatus">Preparing…</p>
                        </div>
                    </div>
                </div>
                <div id="attachedFilesPreview" style="margin-top:12px;display:flex;flex-direction:column;gap:6px"></div>
            </div>
        </div>
        <div class="m-foot">
            <button onclick="closeModal('attachmentsModal')" class="btn btn-outline">Close</button>
        </div>
    </div>
</div>

<!-- Setup Modal -->
<?php if (!AI_ASSISTANT_ENABLED): ?>
<div class="modal-bg" id="setupModal">
    <div class="modal-box" style="max-width:600px">
        <div class="m-hd">
            <div class="m-hi">
                <div class="m-hi-icon" style="background:#FFF7ED"><span class="material-icons-round" style="color:#D97706">settings</span></div>
                <div><div class="m-hi-title">Multi-Provider AI Setup</div><div class="m-hi-sub">Configure your API keys to enable AI</div></div>
            </div>
            <button class="m-close" onclick="closeModal('setupModal')"><span class="material-icons-round">close</span></button>
        </div>
        <div class="m-scroll">
            <div class="m-body">
                <div class="setup-provider prov-anthropic">
                    <h3><span style="font-size:18px">🤖</span> Claude (Anthropic)</h3>
                    <ol>
                        <li>Visit <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></li>
                        <li>Create account → API Keys → Create Key</li>
                        <li>Add to config.php: <code>anthropic['api_key']</code></li>
                    </ol>
                </div>
                <div class="setup-provider prov-openai">
                    <h3><span style="font-size:18px">🟢</span> ChatGPT (OpenAI)</h3>
                    <ol>
                        <li>Visit <a href="https://platform.openai.com/" target="_blank">platform.openai.com</a></li>
                        <li>API Keys → Create new secret key</li>
                        <li>Add to config.php: <code>openai['api_key']</code></li>
                    </ol>
                </div>
                <div class="setup-provider prov-google">
                    <h3><span style="font-size:18px">✨</span> Gemini (Google)</h3>
                    <ol>
                        <li>Visit <a href="https://aistudio.google.com/" target="_blank">aistudio.google.com</a></li>
                        <li>Get API Key → Create API key</li>
                        <li>Add to config.php: <code>google['api_key']</code></li>
                    </ol>
                </div>
                <div style="padding:12px 14px;background:var(--canvas);border:1px solid var(--border);border-radius:var(--r-sm);margin-top:4px">
                    <p style="font-size:12px;color:var(--ink-muted);line-height:1.7"><strong>Note:</strong> Configure one or multiple providers. The system automatically detects and enables configured providers.</p>
                </div>
            </div>
        </div>
        <div class="m-foot">
            <button onclick="closeModal('setupModal')" class="btn btn-purple">Got it</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// ═══════════════════════════════════════════════════════════
// STATE
// ═══════════════════════════════════════════════════════════
const aiState = {
    mode: 'writing',
    provider: AI_CONFIG.default_provider,
    model: null,
    lastResponse: '',
    lastPrompt: '',        // stores the prompt used for last generation (enables Regenerate)
    isGenerating: false,
    controller: null,      // AbortController for in-flight fetch cancellation
    attachments: [],
    history: [],           // persisted to localStorage below
    draftTimer: null,
    csrfToken: '<?= htmlspecialchars(csrf_token()) ?>',
    panelCollapsed: false
};

// ═══════════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initPanelCollapse();    // restore collapsed state before panel renders
    initAIProviders();
    setupEventListeners();
    updateStats();
    loadDraft();
    initAutoSave();
    restoreAIHistory();     // restore generation history from localStorage
});

// ═══════════════════════════════════════════════════════════
// DARK MODE
// ═══════════════════════════════════════════════════════════
function initDarkMode() {
    const t = localStorage.getItem('theme') || 'light';
    document.documentElement.dataset.theme = t;
    updateDarkIcons(t === 'dark');
}
function toggleDark() {
    const dark = document.documentElement.dataset.theme === 'dark';
    document.documentElement.dataset.theme = dark ? 'light' : 'dark';
    localStorage.setItem('theme', dark ? 'light' : 'dark');
    updateDarkIcons(!dark);
}
function updateDarkIcons(isDark) {
    const ico = isDark ? 'light_mode' : 'dark_mode';
    const el1 = document.getElementById('darkBtnTop'); if (el1) el1.querySelector('.material-icons-round').textContent = ico;
    const el2 = document.getElementById('darkIconCollapsed'); if (el2) el2.textContent = ico;
}

// ═══════════════════════════════════════════════════════════
// PANEL COLLAPSE — with localStorage persistence
// ═══════════════════════════════════════════════════════════
function initPanelCollapse() {
    const saved = localStorage.getItem('ai_panel_collapsed') === '1';
    if (saved) {
        aiState.panelCollapsed = true;
        document.getElementById('aiPanel')?.classList.add('collapsed');
        const colBtn = document.getElementById('collapseBtn');
        if (colBtn) colBtn.querySelector('.material-icons-round').textContent = 'chevron_right';
    }
}
function toggleCollapse() {
    const panel = document.getElementById('aiPanel');
    aiState.panelCollapsed = !aiState.panelCollapsed;
    panel.classList.toggle('collapsed', aiState.panelCollapsed);
    const colBtn = document.getElementById('collapseBtn');
    if (colBtn) colBtn.querySelector('.material-icons-round').textContent = aiState.panelCollapsed ? 'chevron_right' : 'chevron_left';
    localStorage.setItem('ai_panel_collapsed', aiState.panelCollapsed ? '1' : '0');
}
document.getElementById('collapseBtn')?.addEventListener('click', toggleCollapse);
document.getElementById('expandBtn')?.addEventListener('click', toggleCollapse);

// Mobile panel
document.getElementById('mobilePanelToggle')?.addEventListener('click', () => {
    document.getElementById('aiPanel').classList.toggle('mobile-open');
    document.getElementById('mobOverlay').classList.toggle('show');
});
document.getElementById('mobOverlay')?.addEventListener('click', () => {
    document.getElementById('aiPanel').classList.remove('mobile-open');
    document.getElementById('mobOverlay').classList.remove('show');
});

// ═══════════════════════════════════════════════════════════
// PROVIDERS & MODELS
// ═══════════════════════════════════════════════════════════
function initAIProviders() {
    const provSelect = document.getElementById('aiProvider');
    const modelSelect = document.getElementById('aiModel');
    if (!AI_CONFIG.enabled || AI_CONFIG.enabled_providers.length === 0) {
        provSelect.innerHTML = '<option value="">No providers configured</option>';
        modelSelect.innerHTML = '<option value="">Configure providers first</option>';
        document.getElementById('generateBtn').disabled = true;
        return;
    }
    provSelect.innerHTML = '';
    AI_CONFIG.enabled_providers.forEach(id => {
        const p = AI_CONFIG.providers[id];
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = p.icon + ' ' + p.name;
        if (id === AI_CONFIG.default_provider) opt.selected = true;
        provSelect.appendChild(opt);
    });
    aiState.provider = provSelect.value;
    updateModels(aiState.provider);
    provSelect.addEventListener('change', function() { aiState.provider = this.value; updateModels(aiState.provider); });
    modelSelect.addEventListener('change', function() { aiState.model = this.value; updateModelDesc(); });
}

function updateModels(providerId) {
    const sel = document.getElementById('aiModel');
    const p = AI_CONFIG.providers[providerId];
    if (!p) { sel.innerHTML = '<option value="">Invalid provider</option>'; return; }
    sel.innerHTML = '';
    Object.entries(p.models).forEach(([id, cfg]) => {
        const opt = document.createElement('option');
        opt.value = id;
        opt.textContent = cfg.name + (cfg.recommended ? ' ⭐' : '');
        opt.dataset.description = cfg.description;
        if (cfg.recommended) opt.selected = true;
        sel.appendChild(opt);
    });
    aiState.model = sel.value;
    updateModelDesc();
}

function updateModelDesc() {
    const sel = document.getElementById('aiModel');
    const desc = document.getElementById('modelDescription');
    const opt = sel.options[sel.selectedIndex];
    if (desc && opt?.dataset.description) desc.textContent = opt.dataset.description;
}

// ═══════════════════════════════════════════════════════════
// MODE
// ═══════════════════════════════════════════════════════════
function setMode(mode) {
    aiState.mode = mode;
    document.querySelectorAll('.mode-btn').forEach(b => b.classList.toggle('active', b.dataset.mode === mode));
    const isWriting = mode === 'writing';
    document.getElementById('writingModePanel').style.display = isWriting ? '' : 'none';
    document.getElementById('chatModePanel').style.display = isWriting ? 'none' : '';
    document.getElementById('writingSettings').style.display = isWriting ? '' : 'none';
    const pl = document.getElementById('promptLabel');
    if (pl) pl.innerHTML = isWriting
        ? '<span class="material-icons-round">edit</span>Your Prompt'
        : '<span class="material-icons-round">chat</span>Your Question';
    const bt = document.getElementById('generateBtnText');
    if (bt) bt.textContent = isWriting ? 'Generate' : 'Ask';
    const pr = document.getElementById('aiPrompt');
    if (pr) pr.placeholder = isWriting ? 'Describe what you want AI to help with…' : 'Ask me anything…';
}

// ═══════════════════════════════════════════════════════════
// EVENT LISTENERS
// ═══════════════════════════════════════════════════════════
function setupEventListeners() {
    // Quick chat buttons
    document.querySelectorAll('.qa-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('aiPrompt').value = this.dataset.prompt;
            document.getElementById('aiPrompt').focus();
        });
    });

    document.getElementById('generateBtn')?.addEventListener('click', handleGenerate);
    document.getElementById('aiPrompt')?.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); handleGenerate(); }
    });

    document.getElementById('copyResponse')?.addEventListener('click', copyResponse);
    document.getElementById('insertResponse')?.addEventListener('click', insertResponse);
    document.getElementById('replaceResponse')?.addEventListener('click', replaceResponse);

    [document.getElementById('publishBtn'), document.getElementById('publishBtnTop'), document.getElementById('publishBtnCollapsed')]
        .forEach(btn => btn?.addEventListener('click', handlePublish));

    // Thumbnail
    document.getElementById('thumbnailBtn')?.addEventListener('click', () => document.getElementById('thumbnailInput').click());
    document.getElementById('thumbnailInput')?.addEventListener('change', function(e) {
        if (e.target.files?.[0]) {
            const r = new FileReader();
            r.onload = ev => {
                document.getElementById('thumbnailPreview').querySelector('img').src = ev.target.result;
                document.getElementById('thumbnailPreview').style.display = '';
            };
            r.readAsDataURL(e.target.files[0]);
        }
    });
    document.getElementById('removeThumbnail')?.addEventListener('click', () => {
        document.getElementById('thumbnailInput').value = '';
        document.getElementById('thumbnailPreview').style.display = 'none';
    });

    // Attachments
    [document.getElementById('attachPanelBtn'), document.getElementById('attachBtnTop')]
        .forEach(btn => btn?.addEventListener('click', () => document.getElementById('attachmentsModal').classList.add('open')));

    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    dropZone?.addEventListener('click', () => fileInput.click());
    dropZone?.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragging'); });
    dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('dragging'));
    dropZone?.addEventListener('drop', e => { e.preventDefault(); dropZone.classList.remove('dragging'); handleFileUpload(e.dataTransfer.files); });
    fileInput?.addEventListener('change', e => handleFileUpload(e.target.files));

    // Stats
    const debounced = debounce(updateStats, 300);
    document.getElementById('articleContent')?.addEventListener('input', debounced);
    document.getElementById('articleTitle')?.addEventListener('input', debounced);

    // Modal backdrops
    document.querySelectorAll('.modal-bg').forEach(m => {
        m.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('open'); });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-bg.open').forEach(m => m.classList.remove('open'));
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveDraft(); showToast('Draft saved', 'success'); }
    });
}

function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }

// ═══════════════════════════════════════════════════════════
// AI GENERATE
// ═══════════════════════════════════════════════════════════
async function handleGenerate() {
    if (aiState.isGenerating) return;
    const prompt = document.getElementById('aiPrompt').value.trim();
    const mode = aiState.mode;
    if (!prompt) {
        showToast('Please enter a ' + (mode === 'chat' ? 'question' : 'prompt'), 'warning');
        document.getElementById('aiPrompt').focus();
        return;
    }
    const action = mode === 'writing' ? document.getElementById('aiAction').value : 'chat';
    const tone   = mode === 'writing' ? document.getElementById('aiTone').value   : '';
    const length = mode === 'writing' ? document.getElementById('aiLength').value : '';

    // Only include article context when the checkbox is checked
    const includeCtx = document.getElementById('includeContext')?.checked !== false;
    const title   = document.getElementById('articleTitle')?.value  || '';
    const content = document.getElementById('articleContent')?.value || '';
    const context = includeCtx ? [title, content].filter(Boolean).join('\n\n') : '';

    // Abort any in-flight request before starting a new one
    if (aiState.controller) aiState.controller.abort();
    aiState.controller = new AbortController();

    aiState.isGenerating = true;
    showLoading(true);

    try {
        const fd = new FormData();
        fd.append('csrf_token', aiState.csrfToken);
        fd.append('provider',   aiState.provider);
        fd.append('model',      aiState.model);
        fd.append('mode',       mode);
        fd.append('ai_action',  action);
        fd.append('prompt',     prompt);
        fd.append('context',    context);
        fd.append('options',    JSON.stringify({ tone, length }));

        const res = await fetch('', { method: 'POST', body: fd, signal: aiState.controller.signal });
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        const data = await res.json();

        if (data.error) {
            showToast(data.error, 'error', 5000);
            if (data.rate_limit) updateRateLimitUI(data.rate_limit);
        } else {
            // Store last prompt for Regenerate, then clear the textarea
            aiState.lastPrompt = prompt;
            document.getElementById('aiPrompt').value = '';

            // Persist history (capped at 20, scoped to user)
            aiState.history.push({ ...data, prompt, timestamp: Date.now() });
            if (aiState.history.length > 20) aiState.history.shift();
            try { localStorage.setItem('ai_history_<?= $_SESSION['user_id'] ?>', JSON.stringify(aiState.history)); } catch(e) {}

            displayResponse(data.response, data.word_count, data.provider, data.model);
            showToast((mode === 'chat' ? 'Answer from ' : 'Generated by ') + AI_CONFIG.providers[data.provider]?.name, 'success');
            if (data.rate_limit) updateRateLimitUI(data.rate_limit);
        }
    } catch (err) {
        if (err.name === 'AbortError') return; // cancelled — no toast needed
        console.error('AI Error:', err);
        showToast(err.message || 'An unexpected error occurred.', 'error', 5000);
    } finally {
        aiState.controller = null;
        aiState.isGenerating = false;
        showLoading(false);
    }
}

// Restore AI generation history from localStorage on page load
function restoreAIHistory() {
    try {
        const saved = localStorage.getItem('ai_history_<?= $_SESSION['user_id'] ?>');
        if (saved) aiState.history = JSON.parse(saved);
    } catch(e) {}
}

function updateRateLimitUI(rl) {
    const badge = document.getElementById('rateLimitBadge');
    const bar = document.getElementById('rateLimitBar');
    if (badge) badge.textContent = rl.remaining + ' / ' + rl.limit + ' left';
    if (bar) {
        const pct = rl.remaining / rl.limit * 100;
        bar.style.width = pct + '%';
        bar.style.background = pct < 20 ? 'linear-gradient(90deg,#DC2626,#EF4444)'
            : pct < 50 ? 'linear-gradient(90deg,#D97706,#F59E0B)'
            : 'linear-gradient(90deg,var(--purple),#A78BFA)';
    }
}

function displayResponse(response, wordCount, provider, model) {
    aiState.lastResponse = response;
    const box     = document.getElementById('aiResponseBox');
    const content = document.getElementById('aiResponseContent');
    const wc      = document.getElementById('aiWordCount');
    const pb      = document.getElementById('providerBadge');
    const regenBtn = document.getElementById('regenerateBtn');

    // Render as Markdown (marked.js + DOMPurify loaded from CDN in <head>)
    if (content) {
        try {
            const html = typeof marked !== 'undefined' ? marked.parse(response) : null;
            content.innerHTML = (typeof DOMPurify !== 'undefined' && html)
                ? DOMPurify.sanitize(html)
                : (html || escHtml(response).replace(/\n/g, '<br>'));
        } catch(e) {
            content.textContent = response;
        }
    }
    if (wc) wc.textContent = wordCount ? wordCount + ' words' : '';
    if (pb) { const pc = AI_CONFIG.providers[provider]; pb.textContent = pc ? pc.icon + ' ' + pc.name : provider; }
    if (regenBtn) regenBtn.style.display = '';  // show Regenerate once we have a response
    if (box) { box.style.display = ''; box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
}

// Regenerate: restore last prompt and fire again
function regenResponse() {
    if (!aiState.lastPrompt) return;
    document.getElementById('aiPrompt').value = aiState.lastPrompt;
    handleGenerate();
}

function showLoading(show) {
    const btn    = document.getElementById('generateBtn');
    const ld     = document.getElementById('aiLoading');
    const cancel = document.getElementById('cancelBtn');
    if (btn) {
        btn.disabled = show;
        btn.innerHTML = show
            ? '<div class="spin-ring" style="border-top-color:white;border-color:rgba(255,255,255,.3)"></div><span>Generating…</span>'
            : '<span class="material-icons-round">auto_awesome</span><span id="generateBtnText">' + (aiState.mode === 'chat' ? 'Ask' : 'Generate') + '</span>';
    }
    if (ld)     ld.style.display     = show ? '' : 'none';
    if (cancel) cancel.style.display = show ? '' : 'none';
}

function copyResponse() {
    if (!aiState.lastResponse) return;
    navigator.clipboard.writeText(aiState.lastResponse).then(() => {
        showToast('Copied to clipboard', 'success');
        const btn = document.getElementById('copyResponse');
        if (btn) { const orig = btn.innerHTML; btn.innerHTML = '<span class="material-icons-round">check</span>Copied'; setTimeout(() => btn.innerHTML = orig, 2000); }
    }).catch(() => showToast('Failed to copy', 'error'));
}

function insertResponse() {
    if (!aiState.lastResponse) return;
    const ca = document.getElementById('articleContent');
    if (!ca) return;
    // Insert at cursor position, not always at the end
    const start  = ca.selectionStart ?? ca.value.length;
    const end    = ca.selectionEnd   ?? ca.value.length;
    const before = ca.value.slice(0, start);
    const after  = ca.value.slice(end);
    const pad    = before.trim() ? '\n\n' : '';
    ca.value = before + pad + aiState.lastResponse + (after.trim() ? '\n\n' : '') + after;
    ca.selectionStart = ca.selectionEnd = start + pad.length + aiState.lastResponse.length;
    ca.focus();
    updateStats(); scheduleDraftSave(); showToast('Content inserted at cursor', 'success');
}

function replaceResponse() {
    if (!aiState.lastResponse) return;
    const ca  = document.getElementById('articleContent');
    const btn = document.getElementById('replaceResponse');
    if (!ca || !btn) return;
    // Two-click styled confirmation — no blocking confirm() dialog needed
    if (btn.dataset.confirming) {
        ca.value = aiState.lastResponse;
        btn.innerHTML = '<span class="material-icons-round">sync</span>Replace';
        btn.style.background = '';
        btn.style.color = '';
        delete btn.dataset.confirming;
        clearTimeout(btn._resetTimer);
        updateStats(); scheduleDraftSave(); showToast('Content replaced', 'success');
    } else {
        btn.innerHTML = '<span class="material-icons-round">warning</span>Confirm?';
        btn.style.background = 'rgba(239,68,68,.35)';
        btn.style.color = '#FCA5A5';
        btn.dataset.confirming = '1';
        btn._resetTimer = setTimeout(() => {
            btn.innerHTML = '<span class="material-icons-round">sync</span>Replace';
            btn.style.background = '';
            btn.style.color = '';
            delete btn.dataset.confirming;
        }, 3000);
    }
}

// ═══════════════════════════════════════════════════════════
// PUBLISH & VALIDATION
// ═══════════════════════════════════════════════════════════
function handlePublish(e) {
    e?.preventDefault();
    const title = document.getElementById('articleTitle').value.trim();
    const content = document.getElementById('articleContent').value.trim();
    const issues = [];
    if (!title) issues.push({ field: 'title', msg: 'Title is required' });
    else if (title.length < 10) issues.push({ field: 'title', msg: 'Title must be at least 10 characters' });
    else if (title.length > 200) issues.push({ field: 'title', msg: 'Title must be less than 200 characters' });
    if (!content) issues.push({ field: 'content', msg: 'Content is required' });
    else {
        const wc = content.split(/\s+/).filter(w => w.length > 0).length;
        if (wc < 50) issues.push({ field: 'content', msg: `Content too short (${wc} words). Minimum 50 required.` });
    }
    if (issues.length > 0) {
        issues.forEach((issue, i) => {
            setTimeout(() => {
                showToast(issue.msg, 'error', 4000);
                const field = document.getElementById(issue.field === 'title' ? 'articleTitle' : 'articleContent');
                if (field) { field.classList.add('shake'); setTimeout(() => field.classList.remove('shake'), 500); if (i === 0) field.focus(); }
            }, i * 400);
        });
        return;
    }
    try { localStorage.removeItem('draft_<?= $_SESSION['user_id'] ?>'); } catch (e) {}
    document.getElementById('articleForm').submit();
}

// ═══════════════════════════════════════════════════════════
// STATS
// ═══════════════════════════════════════════════════════════
function updateStats() {
    const content = document.getElementById('articleContent')?.value || '';
    const words = content.trim() ? content.trim().split(/\s+/).length : 0;
    const chars = content.length;
    const readTime = Math.max(1, Math.ceil(words / 200));
    document.getElementById('wordCount').textContent = words;
    document.getElementById('charCount').textContent = chars;
    document.getElementById('readTime').textContent = readTime;
    const wc = document.getElementById('wordChip');
    if (wc) {
        wc.className = 'stat-chip' + (words === 0 ? '' : words < 50 ? ' warning' : ' ok');
    }
}

// ═══════════════════════════════════════════════════════════
// AUTO-SAVE
// ═══════════════════════════════════════════════════════════
function initAutoSave() {
    document.getElementById('articleTitle')?.addEventListener('input', scheduleDraftSave);
    document.getElementById('articleContent')?.addEventListener('input', scheduleDraftSave);
}
function scheduleDraftSave() {
    clearTimeout(aiState.draftTimer);
    aiState.draftTimer = setTimeout(saveDraft, 3000);
}
function saveDraft() {
    const title = document.getElementById('articleTitle')?.value || '';
    const content = document.getElementById('articleContent')?.value || '';
    const categoryId = document.getElementById('categorySelect')?.value || '';
    if (!title && !content) return;
    try {
        localStorage.setItem('draft_<?= $_SESSION['user_id'] ?>', JSON.stringify({ title, content, category_id: categoryId, attachments: aiState.attachments, timestamp: Date.now() }));
        const di = document.getElementById('draftIndicator');
        if (di) { di.style.display = ''; setTimeout(() => di.style.display = 'none', 3000); }
    } catch (e) { console.error('Draft save failed:', e); }
}
function loadDraft() {
    try {
        const key = 'draft_<?= $_SESSION['user_id'] ?>';
        const saved = localStorage.getItem(key);
        if (!saved) return;
        const draft = JSON.parse(saved);
        if (Date.now() - draft.timestamp > 86400000) { localStorage.removeItem(key); return; }
        const ago = formatTimestamp(draft.timestamp);
        if (confirm('Found a saved draft from ' + ago + '. Restore it?')) {
            document.getElementById('articleTitle').value = draft.title || '';
            document.getElementById('articleContent').value = draft.content || '';
            if (draft.category_id) document.getElementById('categorySelect').value = draft.category_id;
            if (draft.attachments?.length) {
                aiState.attachments = draft.attachments;
                draft.attachments.forEach(a => addAttachmentToUI(a));
                updateAttachCount(); updateAttachmentsData();
            }
            updateStats(); showToast('Draft restored', 'success');
        } else { localStorage.removeItem(key); }
    } catch (e) { console.error('Draft load failed:', e); }
}
function formatTimestamp(ts) {
    const diff = Math.floor((Date.now() - ts) / 60000);
    if (diff < 60) return diff + ' minutes ago';
    if (diff < 1440) return Math.floor(diff / 60) + ' hours ago';
    return new Date(ts).toLocaleString();
}

// ═══════════════════════════════════════════════════════════
// FILE UPLOAD
// ═══════════════════════════════════════════════════════════
async function handleFileUpload(files) {
    if (!files?.length) return;
    const prog = document.getElementById('uploadProgress');
    const status = document.getElementById('uploadStatus');
    prog.style.display = '';
    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (status) status.textContent = `Uploading ${file.name} (${i+1}/${files.length})…`;
        try {
            const fd = new FormData();
            fd.append('csrf_token', aiState.csrfToken);
            fd.append('attachment', file);
            fd.append('upload_attachment', '1');
            const res = await fetch('', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.error) showToast(data.error, 'error');
            else if (data.success) { aiState.attachments.push(data.file); addAttachmentToUI(data.file); showToast(file.name + ' uploaded', 'success'); }
        } catch (err) { showToast('Upload failed: ' + err.message, 'error'); }
    }
    prog.style.display = 'none';
    document.getElementById('fileInput').value = '';
    updateAttachCount(); updateAttachmentsData(); scheduleDraftSave();
}

function addAttachmentToUI(file) {
    const icon = getFileIcon(file.extension);
    const size = formatFileSize(file.size);
    const html = `<div class="attach-item" data-fp="${escHtml(file.path)}">
        <span class="material-icons-round">${icon}</span>
        <div style="flex:1;min-width:0">
            <div class="attach-name">${escHtml(file.name)}</div>
            <div class="attach-size">${size}</div>
        </div>
        <button class="attach-rm" onclick="removeAttachment('${escHtml(file.path)}')">
            <span class="material-icons-round">delete</span>
        </button>
    </div>`;
    document.getElementById('attachedFilesPreview')?.insertAdjacentHTML('beforeend', html);
    document.getElementById('attachmentsContainer')?.insertAdjacentHTML('beforeend', html);
    document.getElementById('attachmentsList').style.display = '';
}

function removeAttachment(fp) {
    aiState.attachments = aiState.attachments.filter(a => a.path !== fp);
    document.querySelectorAll(`[data-fp="${fp}"]`).forEach(el => el.remove());
    updateAttachCount(); updateAttachmentsData(); scheduleDraftSave();
    if (!aiState.attachments.length) document.getElementById('attachmentsList').style.display = 'none';
    showToast('Attachment removed', 'success');
}

function updateAttachCount() {
    const n = aiState.attachments.length;
    document.getElementById('attachCount').textContent = n;
    const chip = document.getElementById('attachChip');
    if (chip) chip.style.display = n > 0 ? '' : 'none';
    const badge = document.getElementById('attachBadgeTop');
    if (badge) { badge.textContent = n; badge.style.display = n > 0 ? '' : 'none'; }
}

function updateAttachmentsData() {
    document.getElementById('attachmentsData').value = JSON.stringify(aiState.attachments);
}

function getFileIcon(ext) {
    const m = { pdf:'picture_as_pdf', doc:'description', docx:'description', txt:'description',
        xls:'table_chart', xlsx:'table_chart', csv:'table_chart', ppt:'slideshow', pptx:'slideshow',
        jpg:'image', jpeg:'image', png:'image', gif:'image', webp:'image', svg:'image',
        mp4:'videocam', avi:'videocam', mov:'videocam', mp3:'audio_file', wav:'audio_file',
        zip:'folder_zip', rar:'folder_zip', '7z':'folder_zip' };
    return m[(ext||'').toLowerCase()] || 'insert_drive_file';
}

function formatFileSize(bytes) {
    if (!bytes) return '0 B';
    const k = 1024, s = ['B','KB','MB','GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + s[i];
}

// ═══════════════════════════════════════════════════════════
// TOAST
// ═══════════════════════════════════════════════════════════
const TOAST_ICONS = { success:'check_circle', error:'error', warning:'warning', info:'info' };
function showToast(msg, type = 'info', dur = 3000) {
    const stack = document.getElementById('toastStack');
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="material-icons-round">${TOAST_ICONS[type]}</span><span class="toast-msg">${escHtml(msg)}</span><span class="toast-x" onclick="this.parentElement.remove()">&#x2715;</span>`;
    stack.appendChild(t);
    setTimeout(() => { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, dur);
}

// ═══════════════════════════════════════════════════════════
// UTILS
// ═══════════════════════════════════════════════════════════
function debounce(fn, ms) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
function escHtml(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }
</script>
</body>
</html>