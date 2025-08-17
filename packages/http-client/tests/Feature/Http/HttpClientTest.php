<?php

use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponse;
use Cognesy\Http\HttpClientBuilder;

beforeEach(function() {
    $this->testStorageDir = sys_get_temp_dir() . '/http_test_recordings';
});

afterEach(function() {
    // Clean up test recordings if they exist
    if (is_dir($this->testStorageDir)) {
        $files = glob($this->testStorageDir . '/*.json');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->testStorageDir)) {
            rmdir($this->testStorageDir);
        }
    }
});

/**
 * Example of unit testing using mocking approach
 */
test('HTTP client with mock driver', function() {
    // Arrange
    $mockDriver = new MockHttpDriver();
    $expectedResponse = MockHttpResponse::success(body: '{"success":true}');

    $mockDriver->addResponse(
        $expectedResponse,
        'https://api.example.com/test',
        'POST',
        '{"key":"value"}'
    );

    $httpClient = (new HttpClientBuilder)->withDriver($mockDriver)->create();

    $request = new HttpRequest(
        'https://api.example.com/test',
        'POST',
        ['Content-Type' => 'application/json'],
        '{"key":"value"}',
        []
    );

    // Act
    $response = $httpClient->withRequest($request)->get();

    // Assert
    expect($response->statusCode())->toBe(200);
    expect($response->body())->toBe('{"success":true}');

    // Verify request was properly passed to the driver
    expect($mockDriver->getReceivedRequests())->toHaveCount(1);
    expect($mockDriver->getLastRequest()?->url())->toBe('https://api.example.com/test');
});

/**
 * Example of testing middleware usage
 */
test('HTTP client with middleware', function() {
    // Arrange
    $mockDriver = new MockHttpDriver();
    $expectedResponse = MockHttpResponse::success(body: '{"id":123}');

    $mockDriver->addResponse(
        $expectedResponse,
        'https://api.example.com/resource',
        'GET'
    );

    $httpClient = (new HttpClientBuilder)->withDriver($mockDriver)->create();

    // Add a simple test middleware that modifies the request
    $httpClient->withMiddleware(new class implements HttpMiddleware {
        public function handle(HttpRequest $request, CanHandleHttpRequest $next): HttpResponse
        {
            // Add a test header to the request
            $request->headers['X-Test'] = 'Modified by middleware';
            return $next->handle($request);
        }
    });

    $request = new HttpRequest(
        'https://api.example.com/resource',
        'GET',
        [],
        '',
        []
    );

    // Act
    $httpClient->withRequest($request)->get();

    // Assert - Verify the middleware modified the request
    expect($mockDriver->getLastRequest()->headers())->toHaveKey('X-Test');
    expect($mockDriver->getLastRequest()->headers()['X-Test'])->toBe('Modified by middleware');
});
