<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\EventSource\EventSourceMiddleware;
use Cognesy\Http\Middleware\ServerSideEvents\StreamSSEsMiddleware;
use Cognesy\Http\Stream\ArrayStream;

function sseStreamChunks(): array {
    return [
        "id: 1\n",
        "event: message\n",
        "data: {\"a\":1}\n\n",
        "data: [DONE]\n\n",
    ];
}

function streamedSseResponse(): HttpResponse {
    return HttpResponse::streaming(
        statusCode: 200,
        headers: ['Content-Type' => 'text/event-stream'],
        stream: ArrayStream::from(sseStreamChunks()),
    );
}

test('StreamSSEsMiddleware parses SSE payload lines', function() {
    $driver = new MockHttpDriver();
    $driver->addResponse(
        streamedSseResponse(),
        'https://api.example.com/sse',
        'GET'
    );

    $client = (new HttpClientBuilder())
        ->withDriver($driver)
        ->create()
        ->withMiddleware(new StreamSSEsMiddleware());

    $request = new HttpRequest(
        'https://api.example.com/sse',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $chunks = iterator_to_array($client->withRequest($request)->stream());

    expect($chunks)->toBe(['{"a":1}', '[DONE]']);
});

test('EventSourceMiddleware with parser matches StreamSSEs behavior', function() {
    $driver = new MockHttpDriver();
    $driver->addResponse(
        streamedSseResponse(),
        'https://api.example.com/sse',
        'GET'
    );

    $eventSource = (new EventSourceMiddleware())
        ->withParser(static fn(string $payload) => $payload);

    $client = (new HttpClientBuilder())
        ->withDriver($driver)
        ->create()
        ->withMiddleware($eventSource);

    $request = new HttpRequest(
        'https://api.example.com/sse',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $chunks = iterator_to_array($client->withRequest($request)->stream());

    expect($chunks)->toBe(['{"a":1}', '[DONE]']);
});

test('EventSourceMiddleware without parser preserves raw stream chunks', function() {
    $driver = new MockHttpDriver();
    $sourceChunks = sseStreamChunks();
    $driver->addResponse(
        HttpResponse::streaming(
            statusCode: 200,
            headers: ['Content-Type' => 'text/event-stream'],
            stream: ArrayStream::from($sourceChunks),
        ),
        'https://api.example.com/sse',
        'GET'
    );

    $client = (new HttpClientBuilder())
        ->withDriver($driver)
        ->create()
        ->withMiddleware(new EventSourceMiddleware());

    $request = new HttpRequest(
        'https://api.example.com/sse',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $chunks = iterator_to_array($client->withRequest($request)->stream());

    expect($chunks)->toBe($sourceChunks);
});

test('HttpClient withSSEStream composes EventSourceMiddleware', function() {
    $client = HttpClient::default()->withSSEStream();

    $stackProperty = new ReflectionProperty($client, 'middlewareStack');
    $middlewareStack = $stackProperty->getValue($client);

    $middlewares = $middlewareStack->all();
    expect($middlewares)->toHaveCount(1);
    expect(array_values($middlewares)[0])->toBeInstanceOf(EventSourceMiddleware::class);
});
