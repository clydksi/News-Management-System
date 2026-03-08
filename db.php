<?php
$_env = parse_ini_file(__DIR__ . '/.env');
$host   = $_env['DB_HOST'] ?? 'localhost';
$dbname = $_env['DB_NAME'] ?? 'crud_news';
$user   = $_env['DB_USER'] ?? 'root';
$pass   = $_env['DB_PASS'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // ← fixes LIMIT/OFFSET integer binding
        ]
    );
} catch (PDOException $e) {
    die("DB Connection failed.");
}
