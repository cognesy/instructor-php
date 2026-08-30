<?php

declare(strict_types=1);

// HTTP Test Server - Entry point for integration testing
// This script is executed by PHP's built-in server

$autoloaders = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];
$autoloader = null;
foreach ($autoloaders as $candidate) {
    if (is_file($candidate)) {
        $autoloader = $candidate;
        break;
    }
}

if ($autoloader === null) {
    throw new RuntimeException('Unable to locate the Composer autoloader');
}

require_once $autoloader;

use Cognesy\Http\Tests\Support\HttpTestRouter;

// Enable CORS for all requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: *');

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '';

// Get headers
$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $headerName = str_replace(' ', '-', ucwords(str_replace('_', ' ', substr($key, 5))));
        $headers[$headerName] = $value;
    }
}

// Get request body
$body = file_get_contents('php://input');

// Parse query parameters
parse_str($query, $args);

try {
    // Create and use router
    $router = new HttpTestRouter();
    $router->handleRequest($method, $path, $args, $headers, $body);
} catch (Throwable $e) {
    // Fallback error handling
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'path' => $path
    ]);
}
