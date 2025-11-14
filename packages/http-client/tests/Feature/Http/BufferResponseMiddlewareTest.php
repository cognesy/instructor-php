<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Middleware\BufferResponse\BufferResponseMiddleware;

beforeEach(function () {
    $this->middleware = new BufferResponseMiddleware();
    $this->driver = new MockHttpDriver();
    $this->request = new HttpRequest(
        url: 'http://example.com',
        method: 'GET',
        headers: [],
        body: [],
        options: [],
    );
});

test('response body can be read multiple times', function () {
    // Arrange
    $this->driver->addResponse(
        MockHttpResponseFactory::success(body: 'test response')
    );

    // Act
    $response = $this->middleware->handle($this->request, $this->driver);
    $firstRead = $response->body();
    $secondRead = $response->body();

    // Assert
    expect($firstRead)->toBe('test response');
    expect($secondRead)->toBe('test response');
});

test('response stream can be read multiple times', function () {
    // Arrange
    $chunks = ['chunk1', 'chunk2', 'chunk3'];
    $this->driver->addResponse(
        MockHttpResponseFactory::streaming(chunks: $chunks)
    );

    // Act
    $response = $this->middleware->handle($this->request, $this->driver);

    // First stream read
    $firstRead = iterator_to_array($response->stream());

    // Second stream read
    $secondRead = iterator_to_array($response->stream());

    // Assert
    expect($firstRead)->toBe($chunks);
    expect($secondRead)->toBe($chunks);
});
