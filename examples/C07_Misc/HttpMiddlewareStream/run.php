---
title: 'HTTP Middleware (Stream)'
docname: 'http_middleware_stream'
---

<?php
require 'examples/boot.php';

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;

// Scenario: Streamed response where middleware prefixes each chunk

class PrefixChunksMiddleware implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
    {
        // ensure we request streaming
        $request = $request->withStreaming(true);

        $response = $next->handle($request);

        // decorate using the new BaseResponseDecorator stream transform
        return new class($request, $response) extends BaseResponseDecorator {
            protected function toChunk(string $data): string {
                return "[CHUNK] " . $data;
            }
        };
    }
}

// Mock driver returns SSE-like chunks
$driver = new MockHttpDriver();
$driver->addResponse(
    new HttpResponse(
        statusCode: 200,
        body: '',
        headers: ['Content-Type' => 'text/event-stream'],
        isStreamed: true,
        stream: ["hello\n", "world\n", "from\n", "middleware\n"],
    ),
    url: 'https://api.example.local/stream',
    method: 'GET'
);

$client = new HttpClient(driver: $driver);
$client = $client->withMiddleware(new PrefixChunksMiddleware());

$request = new HttpRequest(
    url: 'https://api.example.local/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: ['stream' => true],
);

foreach ($client->withRequest($request)->stream() as $chunk) {
    echo $chunk; // chunks will be prefixed by middleware
}
?>

