<?php
declare(strict_types=1);

function database_connection(): PDO
{
    $localConfigPath = __DIR__ . '/config.local.php';
    $localConfig = is_file($localConfigPath) ? require $localConfigPath : [];
    $localConfig = is_array($localConfig) ? $localConfig : [];

    $host = getenv('DB_HOST') ?: ($localConfig['host'] ?? '127.0.0.1');
    $database = getenv('DB_NAME') ?: ($localConfig['database'] ?? 'adnan_portfolio');
    $username = getenv('DB_USER') ?: ($localConfig['username'] ?? 'root');
    $password = getenv('DB_PASSWORD') ?: ($localConfig['password'] ?? '');

    $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}