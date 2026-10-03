<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const MAX_REQUEST_BYTES = 16384;

function respond_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function request_method_not_allowed(array $allowed): void
{
    header('Allow: ' . implode(', ', $allowed));
    respond_json(405, ['error' => 'This request method is not allowed.']);
}

function unicode_length(string $value): int
{
    $count = preg_match_all('/./us', $value, $matches);
    return $count === false ? PHP_INT_MAX : $count;
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedPath = parse_url($requestUri, PHP_URL_PATH);
$path = rawurldecode(is_string($parsedPath) ? $parsedPath : '/');
$rootPath = str_replace('\\', '/', realpath(__DIR__) ?: __DIR__);
$documentRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$basePath = '';
if ($documentRoot !== '' && strncmp($rootPath, rtrim($documentRoot, '/') . '/', strlen(rtrim($documentRoot, '/')) + 1) === 0) {
    $basePath = '/' . trim(substr($rootPath, strlen(rtrim($documentRoot, '/'))), '/');
}
if ($basePath !== '' && strncmp($path, $basePath . '/', strlen($basePath) + 1) === 0) {
    $path = substr($path, strlen($basePath));
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/api/health' || $path === '/api/health.php') {
    if ($method !== 'GET') {
        request_method_not_allowed(['GET']);
    }

    try {
        database_connection()->query('SELECT 1');
        respond_json(200, ['status' => 'ok', 'database' => 'connected']);
    } catch (PDOException $exception) {
        respond_json(503, ['status' => 'error', 'error' => 'MySQL is unavailable. Check the database service and configuration.']);
    }
}

if ($path === '/api/contact' || $path === '/api/contact.php') {
    if ($method !== 'POST') {
        request_method_not_allowed(['POST']);
    }

    $rawBody = file_get_contents('php://input', false, null, 0, MAX_REQUEST_BYTES + 1);
    if (!is_string($rawBody) || strlen($rawBody) > MAX_REQUEST_BYTES) {
        respond_json(413, ['error' => 'The message is larger than the allowed limit.']);
    }

    try {
        $body = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        respond_json(400, ['error' => 'The submitted data is invalid.']);
    }

    if (!is_array($body)) {
        respond_json(400, ['error' => 'The submitted data is invalid.']);
    }

    $name = isset($body['name']) && is_string($body['name']) ? trim($body['name']) : '';
    $email = isset($body['email']) && is_string($body['email']) ? trim($body['email']) : '';
    $message = isset($body['message']) && is_string($body['message']) ? trim($body['message']) : '';

    if (unicode_length($name) < 2 || unicode_length($name) > 100 || unicode_length($message) < 5 || unicode_length($message) > 5000) {
        respond_json(400, ['error' => 'Check the name and message you entered.']);
    }

    if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        respond_json(400, ['error' => 'Please enter a valid email address.']);
    }

    try {
        $statement = database_connection()->prepare(
            'INSERT INTO messages (name, email, message) VALUES (:name, :email, :message)'
        );
        $statement->execute([
            ':name' => $name,
            ':email' => $email,
            ':message' => $message,
        ]);
    } catch (PDOException $exception) {
        respond_json(503, ['error' => 'The message could not be saved. Check the MySQL connection.']);
    }

    respond_json(201, ['message' => 'Thank you. Your message has been saved.']);
}

if (strncmp($path, '/api/', 5) === 0 || $path === '/api') {
    respond_json(404, ['error' => 'This API endpoint was not found.']);
}

if ($method !== 'GET' && $method !== 'HEAD') {
    request_method_not_allowed(['GET', 'HEAD']);
}

if ($path === '/') {
    $path = '/index.html';
}

$extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$publicExtensions = ['html', 'css', 'js', 'svg', 'png', 'jpg', 'jpeg'];
if (!in_array($extension, $publicExtensions, true)) {
    http_response_code(404);
    exit;
}

$root = realpath(__DIR__);
$file = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR));
if ($root === false || $file === false || !is_file($file) || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit;
}

return false;