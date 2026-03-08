<?php
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_verify(): void {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (
        empty($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        http_response_code(403);
        $isJson = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
               || strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false;
        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid CSRF token.']);
        } else {
            echo 'Invalid CSRF token.';
        }
        exit;
    }
}
