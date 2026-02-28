<?php

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Middleware\RecordReplay\RecordingMiddleware;
use Cognesy\Http\Middleware\RecordReplay\ReplayMiddleware;
use Cognesy\Http\Middleware\RecordReplay\StreamedRequestRecord;
use Cognesy\Http\Stream\IterableStream;

beforeEach(function() {
    $this->storageDir = sys_get_temp_dir() . '/http_stream_recordings_' . uniqid('', true);
});

afterEach(function() {
    if (!is_dir($this->storageDir)) {
        return;
    }

    $files = glob($this->storageDir . '/*.json');
    if (is_array($files)) {
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    rmdir($this->storageDir);
});

test('recording middleware keeps streamed response consumable and preserves headers/chunks', function() {
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $headers = ['Content-Type' => 'text/event-stream', 'X-Stream-Version' => 'v1'];
    $chunks = ["data: one\n\n", "data: two\n\n", "data: [DONE]\n\n"];

    $recording = new RecordingMiddleware($this->storageDir);
    $next = new class($headers, $chunks) implements CanHandleHttpRequest {
        public function __construct(
            private array $headers,
            private array $chunks,
        ) {}

        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::streaming(
                statusCode: 200,
                headers: $this->headers,
                stream: new IterableStream($this->chunks),
            );
        }
    };

    $recordedResponse = $recording->handle($request, $next);
    $receivedChunks = iterator_to_array($recordedResponse->stream());

    expect($receivedChunks)->toBe($chunks);
    expect($recordedResponse->headers())->toBe($headers);

    $record = $recording->getRecords()->find($request);
    expect($record)->toBeInstanceOf(StreamedRequestRecord::class);
    expect($record?->getResponseHeaders())->toBe($headers);
    expect($record?->getResponseBody())->toBe(implode('', $chunks));
    expect($record?->getChunks())->toBe($chunks);
});

test('replay middleware restores streamed record with status headers and chunk boundaries', function() {
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $headers = ['Content-Type' => 'text/event-stream', 'X-Replay' => 'true'];
    $chunks = ["part-1", "part-2", "part-3"];

    $recording = new RecordingMiddleware($this->storageDir);
    $recording->handle($request, new class($headers, $chunks) implements CanHandleHttpRequest {
        public function __construct(
            private array $headers,
            private array $chunks,
        ) {}

        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::streaming(
                statusCode: 206,
                headers: $this->headers,
                stream: new IterableStream($this->chunks),
            );
        }
    });

    $replay = new ReplayMiddleware($this->storageDir, false);
    $response = $replay->handle($request, new class implements CanHandleHttpRequest {
        public function handle(HttpRequest $request): HttpResponse {
            throw new RuntimeException('Replay fallback should not be called');
        }
    });

    expect($response->isStreamed())->toBeTrue();
    expect($response->statusCode())->toBe(206);
    expect($response->headers())->toBe($headers);
    expect(iterator_to_array($response->stream()))->toBe($chunks);
});
