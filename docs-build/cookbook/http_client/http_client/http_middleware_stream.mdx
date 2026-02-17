---
title: 'HTTP Middleware (Stream)'
docname: 'http_middleware_stream'
id: '67c3'
---
## Overview

Demonstrates streaming HTTP middleware that prefixes each chunk with a label. Uses
composition-based stream transformation.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\Base\BaseResponseDecorator;
use Cognesy\Http\Stream\ArrayStream;

// Scenario: Streamed response where middleware prefixes each chunk

class PrefixChunksMiddleware implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        // ensure we request streaming
        $request = $request->withStreaming(true);
        $response = $next->handle($request);
        // decorate using composition-based stream transform
        return BaseResponseDecorator::decorate(
            $response,
            fn(string $chunk): string => "[CHUNK] " . $chunk,
        );
    }
}

// Mock driver returns SSE-like chunks
$driver = new MockHttpDriver();
$driver->addResponse(
    HttpResponse::streaming(
        statusCode: 200,
        headers: ['Content-Type' => 'text/event-stream'],
        stream: new ArrayStream(["hello\n", "world\n", "from\n", "middleware\n"]),
    ),
    url: 'https://api.example.local/stream',
    method: 'GET',
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
```
