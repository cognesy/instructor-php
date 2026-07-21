---
title: 'HTTP Client – Opt-in Stream Capture'
docname: 'http_client_stream_capture'
id: 'e7c4'
tags:
  - 'http'
  - 'streaming'
  - 'debugging'
---
## Overview

Demonstrates opt-in, bounded capture of a streamed response. Capture is disabled by
default so long-lived streams do not retain their response bodies in memory.

The example uses a 10-byte preview limit: the complete stream is still emitted, but
only its prefix is retained for inspection. For very long streams, prefer `preview()`
with a small limit. `chunks()` and `full()` retain one PHP entry per captured chunk;
50,000–100,000 small chunks can therefore consume several times their payload size
in array and string overhead, even with a byte cap. The default avoids that retention.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Stream\ArrayStream;
use Cognesy\Http\Stream\StreamCapturingPolicy;

// Use a Mock driver so the example is deterministic and does not require a network.
$client = (new HttpClientBuilder())
    ->withMock(function (MockHttpDriver $mock): void {
        $mock->addResponse(
            HttpResponse::streaming(
                statusCode: 200,
                headers: ['Content-Type' => 'text/plain'],
                stream: ArrayStream::from([
                    "alpha\n",
                    "beta\n",
                    "gamma\n",
                ]),
            ),
            url: 'https://api.example.local/stream',
            method: 'GET',
        );
    })
    ->create();

$request = new HttpRequest(
    url: 'https://api.example.local/stream',
    method: 'GET',
    headers: ['Accept' => 'text/plain'],
    body: '',
    options: ['stream' => true],
);

// Capture is applied to the concrete response, before its stream is consumed.
$response = $client
    ->send($request)
    ->get()
    ->withStreamCapture(
        StreamCapturingPolicy::preview(maxBytes: 10),
    );

foreach ($response->stream() as $chunk) {
    echo $chunk;
}

$capture = $response->streamCapture();
assert($capture !== null, 'Expected stream capture to be enabled');

$stats = $capture->stats();

echo 'Capture: ' . json_encode([
    'preview' => $capture->preview(),
    'bytes' => $stats->bytes,
    'chunks' => $stats->chunks,
    'capturedBytes' => $stats->capturedBytes,
    'truncated' => $stats->truncated,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;

assert($stats->bytes === 17, 'Expected all stream bytes to be counted');
assert($stats->chunks === 3, 'Expected all stream chunks to be counted');
assert($stats->capturedBytes === 10, 'Expected the capture to be bounded');
assert($capture->preview() === "alpha\nbeta", 'Expected a bounded preview');
assert($stats->truncated, 'Expected the preview to report truncation');
?>
```
