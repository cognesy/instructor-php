<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Drivers\Mock\MockHttpResponseFactory;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Illuminate\Http\Client\Factory as HttpFactory;

beforeEach(function() {
    $this->config = new HttpClientConfig(
        requestTimeout: 1,      // Very fast timeout
        connectTimeout: 1,      // One second to connect
        failOnError: false
    );
    $this->events = new EventDispatcher();
});

function createDriver(string $type, HttpClientConfig $config, EventDispatcher $events) {
    return match($type) {
        'guzzle' => new GuzzleDriver($config, $events),
        'laravel' => new LaravelDriver($config, $events, new HttpFactory()),
        'symfony' => new SymfonyDriver($config, $events),
        'mock' => new MockHttpDriver(),
        default => throw new InvalidArgumentException("Unknown driver type: $type")
    };
}

// Test that all drivers implement the unified interface correctly
test('all drivers implement consistent interface', function () {
    $drivers = [
        'mock' => createDriver('mock', $this->config, $this->events),
        'guzzle' => createDriver('guzzle', $this->config, $this->events),
        'laravel' => createDriver('laravel', $this->config, $this->events),
        'symfony' => createDriver('symfony', $this->config, $this->events),
    ];
    
    foreach ($drivers as $driverName => $driver) {
        expect($driver)->toBeInstanceOf(\Cognesy\Http\Contracts\CanHandleHttpRequest::class);
        
        // Each driver should have the handle method
        expect(method_exists($driver, 'handle'))->toBeTrue();
    }
});

// Test basic functionality with mock driver (always reliable)
test('driver handles basic HTTP operations', function (string $method) {
    $driver = createDriver('mock', $this->config, $this->events);
    
    // Set up mock response
    $expectedResponse = MockHttpResponseFactory::success(
        body: json_encode(['method' => $method, 'test' => 'success'])
    );
    
    $testUrl = 'https://test.example.com/' . strtolower($method);
    $driver->addResponse($expectedResponse, $testUrl, $method);
    
    $request = new HttpRequest(
        $testUrl,
        $method,
        ['Content-Type' => 'application/json'],
        $method === 'GET' ? '' : '{"test": "data"}',
        []
    );
    
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain($method);
    expect($response->body())->toContain('success');
    
})->with(['GET', 'POST', 'PUT', 'DELETE']);

// Test error handling with mock driver
test('driver handles different status codes', function (int $statusCode) {
    $driver = createDriver('mock', $this->config, $this->events);
    
    $mockResponse = match($statusCode) {
        200 => MockHttpResponseFactory::success(body: '{"status": "ok"}'),
        404 => MockHttpResponseFactory::error(statusCode: 404, body: '{"error": "not found"}'),
        500 => MockHttpResponseFactory::error(statusCode: 500, body: '{"error": "server error"}')
    };
    
    $testUrl = "https://test.example.com/status/{$statusCode}";
    $driver->addResponse($mockResponse, $testUrl, 'GET');
    
    $request = new HttpRequest($testUrl, 'GET', [], '', []);
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe($statusCode);
    
})->with([200, 404, 500]);

// Test that driver can handle custom headers
test('driver handles custom headers', function () {
    $driver = createDriver('mock', $this->config, $this->events);
    
    $customHeaders = [
        'Authorization' => 'Bearer test-token',
        'X-Custom-Header' => 'custom-value',
        'Content-Type' => 'application/json'
    ];
    
    $expectedResponse = MockHttpResponseFactory::success(
        body: json_encode(['headers' => $customHeaders])
    );
    
    $testUrl = 'https://test.example.com/headers';
    $driver->addResponse($expectedResponse, $testUrl, 'GET');
    
    $request = new HttpRequest($testUrl, 'GET', $customHeaders, '', []);
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->body())->toContain('Bearer test-token');
    expect($response->body())->toContain('custom-value');
});

// Test that driver consistency works (same input = same output type)
test('driver provides consistent response interface', function () {
    $driver = createDriver('mock', $this->config, $this->events);
    
    $expectedResponse = MockHttpResponseFactory::success(body: '{"consistent": true}');
    $testUrl = 'https://test.example.com/consistent';
    
    $driver->addResponse($expectedResponse, $testUrl, 'GET');
    $request = new HttpRequest($testUrl, 'GET', [], '', []);
    $response1 = $driver->handle($request);
    
    // Re-add for second call
    $driver->addResponse($expectedResponse, $testUrl, 'GET');
    $response2 = $driver->handle($request);
    
    // Both responses should have same interface
    expect($response1)->toBeInstanceOf(HttpResponse::class);
    expect($response2)->toBeInstanceOf(HttpResponse::class);
    expect($response1->statusCode())->toBe($response2->statusCode());
    expect($response1->body())->toBe($response2->body());
});

// Validate that all real drivers can be instantiated without errors
test('all real drivers can be instantiated', function (string $driverType) {
    $driver = createDriver($driverType, $this->config, $this->events);
    
    expect($driver)->toBeInstanceOf(\Cognesy\Http\Contracts\CanHandleHttpRequest::class);
    
    // Each driver should be a different concrete implementation
    $expectedClass = match($driverType) {
        'guzzle' => GuzzleDriver::class,
        'laravel' => LaravelDriver::class,
        'symfony' => SymfonyDriver::class,
        'mock' => MockHttpDriver::class
    };
    
    expect($driver)->toBeInstanceOf($expectedClass);
    
})->with(['mock', 'guzzle', 'laravel', 'symfony']);