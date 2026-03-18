---
title: 'HTTP Middleware (Stream)'
docname: 'http_middleware_stream'
id: '67c3'
tags:
  - 'http'
  - 'middleware'
  - 'streaming'
---
## Overview

Simple streaming middleware example that tags every emitted chunk.

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
use Cognesy\Http\Extras\Support\BaseResponseDecorator;
use Cognesy\Http\Stream\ArrayStream;

class TagStreamChunks implements HttpMiddleware
{
    public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse {
        $request = $request->withStreaming(true);
        $response = $next->handle($request);

        return BaseResponseDecorator::decorate(
            $response,
            fn(string $chunk): string => "[STREAM] " . $chunk,
        );
    }
}

$driver = new MockHttpDriver();
$driver->addResponse(
    HttpResponse::streaming(
        statusCode: 200,
        headers: ['Content-Type' => 'text/event-stream'],
        stream: new ArrayStream(["one\n", "two\n", "three\n"]),
    ),
    url: 'https://api.example.local/stream',
    method: 'GET',
);

$client = HttpClient::fromDriver($driver)
    ->withMiddleware(new TagStreamChunks());

$request = new HttpRequest(
    url: 'https://api.example.local/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: ['stream' => true],
);

$chunkCount = 0;
foreach ($client->send($request)->stream() as $chunk) {
    echo $chunk;
    $chunkCount++;
}

assert($chunkCount > 0, 'Expected at least one streamed chunk');
assert(str_contains($chunk, '[STREAM]'), 'Expected chunks to be tagged by middleware');
?>
```
