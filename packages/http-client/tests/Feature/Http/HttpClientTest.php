<?php

use Cognesy\Http\Adapters\MockHttpResponse;
use Cognesy\Http\Contracts\CanHandleHttpRequest;
use Cognesy\Http\Contracts\HttpClientResponse;
use Cognesy\Http\Contracts\HttpMiddleware;
use Cognesy\Http\Data\HttpClientConfig;
use Cognesy\Http\Data\HttpClientRequest;
use Cognesy\Http\Drivers\MockHttpDriver;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

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

    $httpClient = new HttpClient();
    $httpClient = $httpClient->withDriver($mockDriver);

    $request = new HttpClientRequest(
        'https://api.example.com/test',
        'POST',
        ['Content-Type' => 'application/json'],
        '{"key":"value"}',
        []
    );

    // Act
    $response = $httpClient->handle($request);

    // Assert
    expect($response->statusCode())->toBe(200);
    expect($response->body())->toBe('{"success":true}');

    // Verify request was properly passed to the driver
    expect($mockDriver->getReceivedRequests())->toHaveCount(1);
    expect($mockDriver->getLastRequest()->url())->toBe('https://api.example.com/test');
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

    $httpClient = new HttpClient();
    $httpClient = $httpClient->withDriver($mockDriver);

    // Add a simple test middleware that modifies the request
    $httpClient->middleware()->append(new class implements HttpMiddleware {
        public function handle(HttpClientRequest $request, CanHandleHttpRequest $next): HttpClientResponse
        {
            // Add a test header to the request
            $request->headers['X-Test'] = 'Modified by middleware';
            return $next->handle($request);
        }
    });

    $request = new HttpClientRequest(
        'https://api.example.com/resource',
        'GET',
        [],
        '',
        []
    );

    // Act
    $httpClient->handle($request);

    // Assert - Verify the middleware modified the request
    expect($mockDriver->getLastRequest()->headers())->toHaveKey('X-Test');
    expect($mockDriver->getLastRequest()->headers()['X-Test'])->toBe('Modified by middleware');
});

/**
 * Example of integration testing using record/replay approach
 */
test('HTTP client with record/replay middleware', function() {
    // Skip this test if we can't make external HTTP requests
    if (getenv('CI') === 'true' || getenv('SKIP_EXTERNAL_REQUESTS') === 'true') {
        $this->markTestSkipped('Skipping test that requires external HTTP requests');
    }

    // For this test, we'll create a real HTTP client (not a mock)
    // but we'll include the RecordReplayMiddleware for recording/replaying
    $config = new HttpClientConfig(
        httpClientType: 'guzzle',
        connectTimeout: 5,
        requestTimeout: 10
    );

    $httpClient = new HttpClient();
    $httpClient = $httpClient->withConfig($config);

    // Add record/replay middleware
    $recordReplayMiddleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY, // Use RECORD mode when creating the test initially
        $this->testStorageDir
    );

    $httpClient->middleware()->append($recordReplayMiddleware, 'record-replay');

    // Create a request to a real endpoint
    $request = new HttpClientRequest(
        'https://jsonplaceholder.typicode.com/posts/1',
        'GET',
        ['Accept' => 'application/json'],
        '',
        []
    );

    // Act
    $response = $httpClient->handle($request);

    // Assert - We should get a valid response whether it's live or replayed
    expect($response->statusCode())->toBe(200);

    $data = json_decode($response->body(), true);
    expect($data)->toBeArray();
    expect($data)->toHaveKey('id');
    expect($data)->toHaveKey('title');
})->skip('This test is a demonstration and should be skipped');

/**
 * Example of how to mix approaches - mock for unit testing, record/replay for integration
 */
test('mixed testing approach', function() {
    // Create a record/replay middleware in replay mode
    $recordReplayMiddleware = new RecordReplayMiddleware(
        RecordReplayMiddleware::MODE_REPLAY,
        $this->testStorageDir
    );

    // Create a mock driver as fallback for requests without recordings
    $mockDriver = new MockHttpDriver();
    $mockDriver->addResponse(
        MockHttpResponse::success(body: '{"mocked":true}'),
        fn() => true  // Match any URL as fallback
    );

    // Create the client with middleware
    $httpClient = new HttpClient();
    $httpClient = $httpClient->withDriver($mockDriver);
    $httpClient->middleware()->append($recordReplayMiddleware, 'record-replay');

    // Request 1: Should be handled by replay if recording exists, or mock if not
    $request1 = new HttpClientRequest(
        'https://api.example.com/recorded-endpoint',
        'GET',
        [],
        '',
        []
    );

    // Request 2: We'll explicitly run this against the mock
    $request2 = new HttpClientRequest(
        'https://api.example.com/mock-only-endpoint',
        'POST',
        [],
        '{"data":"test"}',
        []
    );

    // Act
    $response1 = $httpClient->handle($request1);

    // For request 2, temporarily disable record/replay middleware
    $httpClient->middleware()->remove('record-replay');
    $response2 = $httpClient->handle($request2);

    // Assert - Both approaches should work in the same test
    expect($response1)->not()->toBeNull();
    expect($response2)->not()->toBeNull();

    // The second response should definitely be from the mock
    expect($response2->body())->toBe('{"mocked":true}');
})->skip('This test is a demonstration and should be skipped');