<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/HttpTestRouter.php';

use Cognesy\Instructor\Laravel\Tests\Support\HttpTestRouter;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (!str_starts_with($key, 'HTTP_')) {
        continue;
    }

    $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', substr($key, 5))));
    $headers[$headerName] = $value;
}

$body = file_get_contents('php://input');
parse_str($query, $args);

try {
    (new HttpTestRouter())->handleRequest($method, $path, $args, $headers, $body);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');

    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'path' => $path,
    ]);
}
