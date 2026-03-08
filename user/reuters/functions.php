<?php
// functions.php

/**
 * Send a GraphQL query to Reuters Connect API
 */
function reutersQuery($query, $accessToken, $endpoint) {
    $payload = json_encode(['query' => $query]);

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer $accessToken"
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
