<?php

declare(strict_types=1);

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = (string) parse_url($uri, PHP_URL_PATH);

if ($path === '/health') {
    echo 'OK';
    return;
}

if (preg_match('#^/status/(\d+)$#', $path, $matches) === 1) {
    http_response_code(max(100, min(599, (int) $matches[1])));
    header('Content-Type: application/json');
    echo json_encode(['status' => (int) $matches[1]], JSON_THROW_ON_ERROR);
    return;
}

if (preg_match('#^/delay/(\d+)$#', $path, $matches) === 1) {
    sleep(min(10, (int) $matches[1]));
    header('Content-Type: application/json');
    echo json_encode(['delay' => (int) $matches[1]], JSON_THROW_ON_ERROR);
    return;
}

if (preg_match('#^/stream/(\d+)$#', $path, $matches) === 1) {
    header('Content-Type: application/x-ndjson');
    foreach (range(1, min(20, (int) $matches[1])) as $line) {
        echo json_encode(['line' => $line], JSON_THROW_ON_ERROR) . "\n";
    }
    return;
}

if (!in_array($path, ['/get', '/post', '/put', '/delete'], true)) {
    http_response_code(404);
}

header('Content-Type: application/json');
echo json_encode([
    'method' => $method,
    'url' => sprintf('http://%s%s', (string) ($_SERVER['HTTP_HOST'] ?? '127.0.0.1'), $uri),
    'data' => file_get_contents('php://input'),
], JSON_THROW_ON_ERROR);
