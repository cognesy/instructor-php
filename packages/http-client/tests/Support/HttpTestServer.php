<?php

// HTTP Test Server - Entry point for integration testing
// This script is executed by PHP's built-in server

// Include the autoloader
require_once __DIR__ . '/../../../../vendor/autoload.php';

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