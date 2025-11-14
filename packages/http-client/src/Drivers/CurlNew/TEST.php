<?php declare(strict_types=1);

/**
 * Quick Test Script for CurlNew Driver
 *
 * Run: php packages/http-client/src/Drivers/CurlNew/TEST.php
 */

require_once __DIR__ . '/../../../../../vendor/autoload.php';

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpRequestBody;
use Cognesy\Http\Drivers\CurlNew\CurlNewDriver;
use Symfony\Component\EventDispatcher\EventDispatcher;

echo "═══════════════════════════════════════\n";
echo " CurlNew Driver Test\n";
echo "═══════════════════════════════════════\n\n";

$config = new HttpClientConfig(
    connectTimeout: 3,
    requestTimeout: 30,
    streamChunkSize: 256,
    failOnError: false,
);

$events = new EventDispatcher();
$driver = new CurlNewDriver($config, $events);

// Test 1: Simple GET request
echo "Test 1: Simple GET Request\n";
echo "───────────────────────────────────────\n";

$request = new HttpRequest(
    url: 'https://httpbin.org/get',
    method: 'GET',
    headers: ['User-Agent' => 'CurlNew-Test/1.0'],
    body: '',
    options: ['stream' => false],
);

try {
    $response = $driver->handle($request);
    echo "✓ Status: " . $response->statusCode() . "\n";
    echo "✓ Body length: " . strlen($response->body()) . " bytes\n";
    echo "✓ Headers: " . count($response->headers()) . " headers\n";
    echo "✓ Is streamed: " . ($response->isStreamed() ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: POST with JSON
echo "Test 2: POST with JSON\n";
echo "───────────────────────────────────────\n";

$request = new HttpRequest(
    url: 'https://httpbin.org/post',
    method: 'POST',
    headers: [
        'Content-Type' => 'application/json',
        'User-Agent' => 'CurlNew-Test/1.0',
    ],
    body: ['test' => 'data', 'number' => 42],
    options: ['stream' => false],
);

try {
    $response = $driver->handle($request);
    echo "✓ Status: " . $response->statusCode() . "\n";
    echo "✓ Body length: " . strlen($response->body()) . " bytes\n";

    $data = json_decode($response->body(), true);
    if (isset($data['json']['test']) && $data['json']['test'] === 'data') {
        echo "✓ JSON data preserved correctly\n";
    }
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Streaming request
echo "Test 3: Streaming Request\n";
echo "───────────────────────────────────────\n";

$request = new HttpRequest(
    url: 'https://httpbin.org/stream/10',
    method: 'GET',
    headers: ['User-Agent' => 'CurlNew-Test/1.0'],
    body: '',
    options: ['stream' => true],
);

try {
    $response = $driver->handle($request);
    echo "✓ Status: " . $response->statusCode() . "\n";
    echo "✓ Is streamed: " . ($response->isStreamed() ? 'yes' : 'no') . "\n";

    $chunkCount = 0;
    $totalBytes = 0;

    foreach ($response->stream() as $chunk) {
        $chunkCount++;
        $totalBytes += strlen($chunk);
    }

    echo "✓ Chunks received: {$chunkCount}\n";
    echo "✓ Total bytes: {$totalBytes}\n";
    echo "✓ Body length: " . strlen($response->body()) . " bytes\n";
} catch (Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 4: Error handling
echo "Test 4: Error Handling (404)\n";
echo "───────────────────────────────────────\n";

$config = new HttpClientConfig(
    connectTimeout: 3,
    requestTimeout: 30,
    streamChunkSize: 256,
    failOnError: true,
);

$driver = new CurlNewDriver($config, $events);

$request = new HttpRequest(
    url: 'https://httpbin.org/status/404',
    method: 'GET',
    headers: [],
    body: '',
    options: ['stream' => false],
);

try {
    $response = $driver->handle($request);
    echo "✗ Should have thrown exception\n";
} catch (Throwable $e) {
    echo "✓ Exception thrown: " . get_class($e) . "\n";
    echo "✓ Message: " . $e->getMessage() . "\n";
}

echo "\n═══════════════════════════════════════\n";
echo " ✓ All tests completed\n";
echo "═══════════════════════════════════════\n";
