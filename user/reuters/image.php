<?php
require 'config.php';

$url = $_GET['url'] ?? '';
if (!$url) {
    http_response_code(400);
    exit("Missing image URL");
}

// Fetch the image from Reuters with Authorization header
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$data = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);

if ($info['http_code'] === 200) {
    // Determine mime type
    $mime = $info['content_type'] ?? 'image/jpeg';
    header("Content-Type: $mime");
    echo $data;
} else {
    http_response_code(404);
    echo "Image could not be loaded";
}
