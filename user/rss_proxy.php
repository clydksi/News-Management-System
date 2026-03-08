<?php
/**
 * RSS Proxy - Server-side RSS fetcher
 * This bypasses CORS issues by fetching RSS on the server
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/xml; charset=utf-8');

// Get the RSS query from the URL parameter
$query = isset($_GET['q']) ? $_GET['q'] : 'Philippines news';

// Build Google News RSS URL
$rssUrl = 'https://news.google.com/rss/search?q=' . urlencode($query) . '&hl=en-US&gl=US&ceid=US:en';

// Initialize cURL
$ch = curl_init();

// Set cURL options for better reliability
curl_setopt_array($ch, [
    CURLOPT_URL => $rssUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    CURLOPT_HTTPHEADER => [
        'Accept: application/xml, text/xml, application/rss+xml',
        'Accept-Language: en-US,en;q=0.9',
        'Cache-Control: no-cache',
        'Connection: keep-alive'
    ]
]);

// Execute the request
$xmlContent = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

// Log for debugging (optional)
error_log("RSS Proxy - Query: $query");
error_log("RSS Proxy - HTTP Code: $httpCode");

// Check for errors
if ($xmlContent === false) {
    http_response_code(503);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Failed to fetch RSS feed',
        'details' => $curlError,
        'query' => $query
    ]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'RSS feed returned error',
        'http_code' => $httpCode,
        'query' => $query
    ]);
    exit;
}

// Validate XML
$xml = @simplexml_load_string($xmlContent);
if ($xml === false) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Invalid XML received',
        'query' => $query
    ]);
    exit;
}

// Return the XML content
echo $xmlContent;