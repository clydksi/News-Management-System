<?php
// Test file to check if curl can reach Google News
header('Content-Type: application/json');

echo json_encode([
    'php_version' => phpversion(),
    'curl_enabled' => function_exists('curl_init'),
    'allow_url_fopen' => ini_get('allow_url_fopen'),
    'timezone' => date_default_timezone_get(),
    'server_time' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);

echo "\n\n--- Testing Google News RSS ---\n";

$url = 'https://news.google.com/rss/search?q=sports&hl=en-US&gl=US&ceid=US:en';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'url_tested' => $url,
    'http_code' => $httpCode,
    'curl_error' => $error ?: 'none',
    'response_length' => strlen($response),
    'response_preview' => substr($response, 0, 500)
], JSON_PRETTY_PRINT);
