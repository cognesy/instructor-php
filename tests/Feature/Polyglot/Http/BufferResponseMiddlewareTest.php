<?php

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;

beforeEach(function () {
    $this->middleware = new BufferResponseMiddleware();
    $this->handler = new MockHttpHandler();
    $this->request = new HttpClientRequest(
        url: 'http://example.com',
        method: 'GET',
        headers: [],
        body: [],
        options: []
    );
});

test('response body can be read multiple times', function () {
    // Arrange
    $this->handler->setResponse(new MockResponse('test response', $this->handler));

    // Act
    $response = $this->middleware->handle($this->request, $this->handler);
    $firstRead = $response->body();
    $secondRead = $response->body();

    // Assert
    expect($firstRead)->toBe('test response');
    expect($secondRead)->toBe('test response');
    expect($this->handler->getResponseBodyReadCount())->toBe(1);
});

test('response stream can be read multiple times', function () {
    // Arrange
    $chunks = ['chunk1', 'chunk2', 'chunk3'];
    $this->handler->setResponse(new MockStreamResponse($chunks, $this->handler));

    // Act
    $response = $this->middleware->handle($this->request, $this->handler);
    
    // First stream read
    $firstRead = iterator_to_array($response->stream());
    
    // Second stream read
    $secondRead = iterator_to_array($response->stream());

    // Assert
    expect($firstRead)->toBe($chunks);
    expect($secondRead)->toBe($chunks);
    expect($this->handler->getStreamReadCount())->toBe(1);
});

class MockHttpHandler implements CanHandleHttpRequest
{
    private HttpClientResponse $response;
    private int $responseBodyReadCount = 0;
    private int $streamReadCount = 0;

    public function setResponse(HttpClientResponse $response): void
    {
        $this->response = $response;
    }

    public function handle(HttpClientRequest $request): HttpClientResponse
    {
        return $this->response;
    }

    public function getResponseBodyReadCount(): int
    {
        return $this->responseBodyReadCount;
    }

    public function getStreamReadCount(): int
    {
        return $this->streamReadCount;
    }

    public function incrementResponseBodyReadCount(): void
    {
        $this->responseBodyReadCount++;
    }

    public function incrementStreamReadCount(): void
    {
        $this->streamReadCount++;
    }
}

class MockResponse implements HttpClientResponse
{
    private string $body;
    private MockHttpHandler $handler;

    public function __construct(string $body, ?MockHttpHandler $handler = null)
    {
        $this->body = $body;
        $this->handler = $handler ?? new MockHttpHandler();
    }

    public function statusCode(): int
    {
        return 200;
    }

    public function headers(): array
    {
        return [];
    }

    public function body(): string
    {
        $this->handler->incrementResponseBodyReadCount();
        return $this->body;
    }

    public function stream(int $chunkSize = 1): Generator
    {
        $this->handler->incrementStreamReadCount();
        yield $this->body;
    }
}

class MockStreamResponse implements HttpClientResponse
{
    private array $chunks;
    private MockHttpHandler $handler;

    public function __construct(array $chunks, ?MockHttpHandler $handler = null)
    {
        $this->chunks = $chunks;
        $this->handler = $handler ?? new MockHttpHandler();
    }

    public function statusCode(): int
    {
        return 200;
    }

    public function headers(): array
    {
        return [];
    }

    public function body(): string
    {
        $this->handler->incrementResponseBodyReadCount();
        return implode('', $this->chunks);
    }

    public function stream(int $chunkSize = 1): Generator
    {
        $this->handler->incrementStreamReadCount();
        foreach ($this->chunks as $chunk) {
            yield $chunk;
        }
    }
}
