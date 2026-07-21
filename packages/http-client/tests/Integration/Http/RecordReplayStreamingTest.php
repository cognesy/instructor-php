<?php

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Extras\Middleware\RecordReplay\RecordingMiddleware;
use Cognesy\Http\Extras\Middleware\RecordReplay\ReplayMiddleware;
use Cognesy\Http\Extras\Support\RecordReplay\StreamedRequestRecord;
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

    $recordFiles = glob($this->storageDir . '/*.json');
    expect($recordFiles)->toBeArray()->toHaveCount(1);
    $recordData = json_decode((string) file_get_contents($recordFiles[0]), true);
    expect($recordData['response'])->not->toHaveKey('body');
    expect($recordData['chunks'])->toBe($chunks);
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
    $recordedResponse = $recording->handle($request, new class($headers, $chunks) implements CanHandleHttpRequest {
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
    expect(iterator_to_array($recordedResponse->stream()))->toBe($chunks);

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

test('recording middleware returns before draining the upstream stream', function() {
    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        ['Accept' => 'text/event-stream'],
        '',
        ['stream' => true],
    );

    $driver = new class implements CanHandleHttpRequest {
        public static int $yielded = 0;

        public function handle(HttpRequest $request): HttpResponse {
            $stream = new IterableStream((function() {
                foreach (['a', 'b', 'c'] as $chunk) {
                    self::$yielded++;
                    yield $chunk;
                }
            })());

            return HttpResponse::streaming(
                statusCode: 200,
                headers: ['Content-Type' => 'text/plain'],
                stream: $stream,
            );
        }
    };

    $driver::$yielded = 0;

    $recording = new RecordingMiddleware($this->storageDir);
    $response = $recording->handle($request, $driver);

    expect($driver::$yielded)->toBe(0)
        ->and($response->isStreamed())->toBeTrue()
        ->and(iterator_to_array($response->stream()))->toBe(['a', 'b', 'c']);
    expect($driver::$yielded)->toBe(3);
});

test('partial consumption does not create a complete recording', function() {
    $request = new HttpRequest(
        'https://api.example.com/partial',
        'GET',
        [],
        '',
        ['stream' => true],
    );
    $driver = new class implements CanHandleHttpRequest {
        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::streaming(
                statusCode: 200,
                headers: [],
                stream: new IterableStream(['first', 'second']),
            );
        }
    };

    $response = (new RecordingMiddleware($this->storageDir))->handle($request, $driver);
    foreach ($response->stream() as $_chunk) {
        break;
    }

    expect(glob($this->storageDir . '/*.json'))->toBeArray()->toHaveCount(0);
});

test('failed upstream streams are not persisted as complete recordings', function() {
    $request = new HttpRequest(
        'https://api.example.com/failure',
        'GET',
        [],
        '',
        ['stream' => true],
    );
    $driver = new class implements CanHandleHttpRequest {
        public function handle(HttpRequest $request): HttpResponse {
            return HttpResponse::streaming(
                statusCode: 200,
                headers: [],
                stream: new IterableStream((function (): Generator {
                    yield 'first';
                    throw new RuntimeException('upstream failed');
                })()),
            );
        }
    };

    $response = (new RecordingMiddleware($this->storageDir))->handle($request, $driver);

    expect(fn() => iterator_to_array($response->stream()))
        ->toThrow(RuntimeException::class, 'upstream failed')
        ->and(glob($this->storageDir . '/*.json'))->toBeArray()->toHaveCount(0);
});
