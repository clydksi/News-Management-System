<?php
$_env = parse_ini_file(__DIR__ . '/.env');
$host   = $_env['DB_HOST'] ?? 'localhost';
$dbname = $_env['DB_NAME'] ?? 'crud_news';
$user   = $_env['DB_USER'] ?? 'root';
$pass   = $_env['DB_PASS'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed.");
}
