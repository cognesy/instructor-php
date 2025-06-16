<?php

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;

beforeEach(function() {
    $this->driver = new MockHttpDriver();
});

test('can return predefined response', function() {
    // Arrange
    $expectedResponse = MockHttpResponse::success(body: '{"result": true}');

    $this->driver->addResponse(
        $expectedResponse,
        'https://api.example.com/test',
        'POST'
    );

    $request = new HttpRequest(
        'https://api.example.com/test',
        'POST',
        ['Content-Type' => 'application/json'],
        '{"data": "test"}',
        []
    );

    // Act
    $response = $this->driver->handle($request);

    // Assert
    expect($response)->toBe($expectedResponse);
    expect($response->body())->toBe('{"result": true}');
    expect($response->statusCode())->toBe(200);
});

test('can match requests by callback', function() {
    // Arrange
    $this->driver->addResponse(
        MockHttpResponse::success(body: '{"users": []}'),
        fn($url) => str_contains($url, 'users'),
        'GET'
    );

    $request = new HttpRequest(
        'https://api.example.com/users?page=1',
        'GET',
        [],
        '',
        []
    );

    // Act
    $response = $this->driver->handle($request);

    // Assert
    expect($response->body())->toBe('{"users": []}');
});

test('can match requests by json body', function() {
    // Arrange
    $this->driver->addResponse(
        MockHttpResponse::success(body: '{"success": true}'),
        null,
        'POST',
        fn($body) => str_contains($body, '"name":"John"')
    );

    $request = new HttpRequest(
        'https://api.example.com/users',
        'POST',
        ['Content-Type' => 'application/json'],
        '{"name":"John","age":30}',
        []
    );

    // Act
    $response = $this->driver->handle($request);

    // Assert
    expect($response->body())->toBe('{"success": true}');
});

test('tracks received requests', function() {
    // Arrange
    $response = MockHttpResponse::success();
    $this->driver->addResponse($response, null, null, null); // Match any request

    $request1 = new HttpRequest('https://api.example.com/users', 'GET', [], '', []);
    $request2 = new HttpRequest('https://api.example.com/posts', 'GET', [], '', []);

    // Act
    $this->driver->handle($request1);
    $this->driver->handle($request2);

    // Assert
    $receivedRequests = $this->driver->getReceivedRequests();
    expect($receivedRequests)->toHaveCount(2);
    expect($receivedRequests[0])->toBe($request1);
    expect($receivedRequests[1])->toBe($request2);
    expect($this->driver->getLastRequest())->toBe($request2);
});

test('generates dynamic responses', function() {
    // Arrange
    $this->driver->addResponse(
        function(HttpRequest $request) {
            $url = $request->url();
            $id = substr($url, strrpos($url, '/') + 1);
            return MockHttpResponse::success(
                body: json_encode(['id' => $id, 'name' => 'User ' . $id])
            );
        },
        fn($url) => preg_match('/\/users\/\d+$/', $url)
    );

    $request = new HttpRequest(
        'https://api.example.com/users/123',
        'GET',
        [],
        '',
        []
    );

    // Act
    $response = $this->driver->handle($request);

    // Assert
    expect($response->body())->toBe('{"id":"123","name":"User 123"}');
});

test('handles streaming responses', function() {
    // Arrange
    $chunks = ['{"id":"123",', '"name":"User', ' 123"}'];

    $this->driver->addResponse(
        MockHttpResponse::streaming(chunks: $chunks),
        'https://api.example.com/stream'
    );

    $request = new HttpRequest(
        'https://api.example.com/stream',
        'GET',
        [],
        '',
        ['stream' => true]
    );

    // Act
    $response = $this->driver->handle($request);

    // Assert
    $receivedChunks = [];
    foreach ($response->stream() as $chunk) {
        $receivedChunks[] = $chunk;
    }

    expect($receivedChunks)->toBe($chunks);
    expect($response->body())->toBe(implode('', $chunks));
});

test('throws exception when no matching response', function() {
    // Arrange
    $this->driver->addResponse(
        MockHttpResponse::success(),
        'https://api.example.com/endpoint',
        'GET'
    );

    $request = new HttpRequest(
        'https://api.example.com/different-endpoint',
        'GET',
        [],
        '',
        []
    );

    // Act & Assert
    expect(fn() => $this->driver->handle($request))
        ->toThrow(\InvalidArgumentException::class);
});