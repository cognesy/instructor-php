<?php
require 'examples/boot.php';

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Stream\ArrayStream;

// Basics: request a streamed response and iterate chunks.
// Use Mock driver to simulate SSE-like stream.

$client = (new HttpClientBuilder())
    ->withMock(function ($mock) {
        $mock->addResponse(
            HttpResponse::streaming(
                statusCode: 200,
                headers: ['Content-Type' => 'text/event-stream'],
                stream: ArrayStream::from(["hello\n", "from\n", "stream\n"]),
            ),
            url: 'https://api.example.local/stream',
            method: 'GET'
        );
    })
    ->create();

$request = new HttpRequest(
    url: 'https://api.example.local/stream',
    method: 'GET',
    headers: ['Accept' => 'text/event-stream'],
    body: '',
    options: ['stream' => true],
);

foreach ($client->withRequest($request)->stream() as $chunk) {
    echo $chunk; // handle streamed data incrementally
}
?>
