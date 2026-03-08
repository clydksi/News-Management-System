<?php
/**
 * Article Content Fetcher
 * Fetches the full article content from a URL by scraping the page
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

function sendJson($success, $content = '', $error = '') {
    echo json_encode([
        'success' => $success,
        'content' => $content,
        'error' => $error
    ]);
    exit;
}

$url = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    sendJson(false, '', 'Invalid or missing URL');
}

// Fetch the page
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: no-cache'
    ]
]);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($html === false || $httpCode !== 200) {
    sendJson(false, '', 'Failed to fetch page: ' . ($curlError ?: "HTTP $httpCode"));
}

// Suppress HTML parsing warnings
libxml_use_internal_errors(true);

$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
$xpath = new DOMXPath($doc);

libxml_clear_errors();

$content = '';

// Strategy 1: Extract from <article> tag
$articles = $xpath->query('//article');
if ($articles->length > 0) {
    $paragraphs = [];
    foreach ($articles as $article) {
        $pTags = $xpath->query('.//p', $article);
        foreach ($pTags as $p) {
            $text = trim($p->textContent);
            if (strlen($text) > 30) {
                $paragraphs[] = $text;
            }
        }
    }
    if (count($paragraphs) > 0) {
        $content = implode("\n\n", $paragraphs);
    }
}

// Strategy 2: Look for common content containers
if (empty($content)) {
    $selectors = [
        '//*[contains(@class,"article-body")]//p',
        '//*[contains(@class,"article-content")]//p',
        '//*[contains(@class,"story-body")]//p',
        '//*[contains(@class,"post-content")]//p',
        '//*[contains(@class,"entry-content")]//p',
        '//*[contains(@class,"content-body")]//p',
        '//*[contains(@itemprop,"articleBody")]//p',
        '//main//p',
    ];

    foreach ($selectors as $selector) {
        $nodes = $xpath->query($selector);
        if ($nodes->length > 0) {
            $paragraphs = [];
            foreach ($nodes as $node) {
                $text = trim($node->textContent);
                if (strlen($text) > 30) {
                    $paragraphs[] = $text;
                }
            }
            if (count($paragraphs) >= 2) {
                $content = implode("\n\n", $paragraphs);
                break;
            }
        }
    }
}

// Strategy 3: Get all <p> tags from body and filter for substantial ones
if (empty($content)) {
    $allP = $xpath->query('//body//p');
    $paragraphs = [];
    foreach ($allP as $p) {
        $text = trim($p->textContent);
        if (strlen($text) > 50) {
            $paragraphs[] = $text;
        }
    }
    if (count($paragraphs) >= 2) {
        $content = implode("\n\n", $paragraphs);
    }
}

// Strategy 4: Fall back to meta description
if (empty($content)) {
    $metaDesc = $xpath->query('//meta[@name="description"]/@content');
    if ($metaDesc->length > 0) {
        $content = trim($metaDesc->item(0)->textContent);
    }

    $ogDesc = $xpath->query('//meta[@property="og:description"]/@content');
    if ($ogDesc->length > 0) {
        $ogText = trim($ogDesc->item(0)->textContent);
        if (strlen($ogText) > strlen($content)) {
            $content = $ogText;
        }
    }
}

if (empty($content)) {
    sendJson(false, '', 'Could not extract article content');
}

// Clean up the content
$content = preg_replace('/\s+/', ' ', $content);
$content = str_replace(' .', '.', $content);
$content = preg_replace('/\n{3,}/', "\n\n", $content);
$content = trim($content);

sendJson(true, $content);
?>
