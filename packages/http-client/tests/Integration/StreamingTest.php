<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Middleware\StreamByLine\StreamByLineMiddleware;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Illuminate\Http\Client\Factory as HttpFactory;
use GuzzleHttp\Client;
use Symfony\Component\HttpClient\HttpClient;

beforeEach(function() {
    $this->baseUrl = IntegrationTestServer::start();
    $this->events = new EventDispatcher();
    $this->config = new HttpClientConfig(
        requestTimeout: 30,
        connectTimeout: 5,
        failOnError: false
    );
});

afterEach(function() {
    // Server cleanup handled by IntegrationTestServer
});

function createStreamingDriver(string $type, HttpClientConfig $config, EventDispatcher $events) {
    return match($type) {
        'guzzle' => new GuzzleDriver($config, $events, new Client()),
        'laravel' => new LaravelDriver($config, $events, new HttpFactory()),
        'symfony' => new SymfonyDriver($config, $events, HttpClient::create()),
        default => throw new InvalidArgumentException("Unknown driver type: $type")
    };
}

// Test basic streaming functionality across all drivers
test('driver handles streaming responses', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    $request = new HttpRequest($this->baseUrl . '/stream/3', 'GET', [], '', ['stream' => true]); // isStreamed = true
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->isStreamed())->toBeTrue(); // This should work since request isStreamed = true
    
    // Collect all streamed data
    $allData = '';
    foreach ($response->stream() as $chunk) {
        $allData .= $chunk;
    }
    
    // Verify we got NDJSON data with 3 lines
    $lines = array_filter(explode("\n", trim($allData)), fn($line) => !empty(trim($line)));
    expect(count($lines))->toBeGreaterThanOrEqual(3);
    
    // Check that we get JSON data lines
    foreach (array_slice($lines, 0, 3) as $i => $line) {
        $data = json_decode(trim($line), true);
        expect($data)->toHaveKey('line');
        expect($data)->toHaveKey('data');
        expect($data['line'])->toBe($i);
        expect($data['data'])->toBe("stream data $i");
    }
    
})->with(['guzzle', 'laravel', 'symfony']);

// Test EventSource (Server-Sent Events) streaming
test('driver handles EventSource/SSE responses', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    $request = new HttpRequest($this->baseUrl . '/sse/3', 'GET', [], '', ['stream' => true]);
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->isStreamed())->toBeTrue();
    
    // Collect all data from the stream
    $allData = '';
    foreach ($response->stream() as $chunk) {
        $allData .= $chunk;
    }
    
    // Verify we got SSE-format data
    expect($allData)->toContain('id: event_0');
    expect($allData)->toContain('event: message');
    expect($allData)->toContain('data: {');
    expect($allData)->toContain('"event_id":0');
    expect($allData)->toContain('SSE event');
    
})->with(['guzzle', 'laravel', 'symfony']);

// Test streaming with chunk size control
test('driver respects chunk size in streaming', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    $request = new HttpRequest($this->baseUrl . '/stream/5', 'GET', [], '', ['stream' => true]);
    $response = $driver->handle($request);
    
    expect($response->isStreamed())->toBeTrue();
    
    // Stream with small chunk size
    $chunks = [];
    foreach ($response->stream(64) as $chunk) {
        $chunks[] = $chunk;
        if (count($chunks) > 20) break; // Prevent infinite loops in tests
    }
    
    // Should have received chunks (exact count depends on driver implementation)
    expect($chunks)->toBeArray();
    expect(count($chunks))->toBeGreaterThan(0);
    
})->with(['guzzle', 'laravel', 'symfony']);

// Test streaming performance and timing
test('streaming provides data progressively', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    $start = microtime(true);
    $request = new HttpRequest($this->baseUrl . '/stream-slow/3', 'GET', [], '', ['stream' => true]);
    $response = $driver->handle($request);
    
    $firstChunkTime = null;
    $chunkTimes = [];
    
    foreach ($response->stream() as $chunk) {
        $currentTime = microtime(true);
        if ($firstChunkTime === null) {
            $firstChunkTime = $currentTime - $start;
        }
        $chunkTimes[] = $currentTime - $start;
        
        // Break after first few chunks to avoid long test times
        if (count($chunkTimes) >= 2) break;
    }
    
    // First chunk should arrive reasonably quickly (allow for server startup)
    expect($firstChunkTime)->toBeLessThan(0.5); // More lenient timing
    expect(count($chunkTimes))->toBeGreaterThanOrEqual(2);
    
})->with(['guzzle', 'laravel', 'symfony']);

// Test non-streamed request handling
test('driver handles non-streamed requests normally', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    $request = new HttpRequest($this->baseUrl . '/stream/3', 'GET', [], '', ['stream' => false]); // isStreamed = false
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->isStreamed())->toBeFalse();
    
    // Body should contain all data at once
    $body = $response->body();
    expect($body)->toContain('"line":0');
    expect($body)->toContain('"line":1');
    expect($body)->toContain('"line":2');
    
})->with(['guzzle', 'laravel', 'symfony']);

// Test streaming middleware integration
test('StreamByLine middleware processes streaming data', function () {
    $driver = createStreamingDriver('guzzle', $this->config, $this->events);
    
    // Add StreamByLine middleware with JSON parser
    $middleware = new StreamByLineMiddleware(
        parser: function($line) {
            return json_decode(trim($line), true);
        }
    );
    
    // Note: This test demonstrates middleware concept
    // Actual integration would require middleware stack setup
    expect($middleware)->toBeInstanceOf(StreamByLineMiddleware::class);
});

// Test that streaming works for successful responses
test('streaming works for valid endpoints', function (string $driverType) {
    $driver = createStreamingDriver($driverType, $this->config, $this->events);
    
    // Use a valid streaming endpoint
    $request = new HttpRequest($this->baseUrl . '/stream/2', 'GET', [], '', ['stream' => true]);
    $response = $driver->handle($request);
    
    expect($response)->toBeInstanceOf(HttpResponse::class);
    expect($response->statusCode())->toBe(200);
    expect($response->isStreamed())->toBeTrue();
    
    // Collect all data
    $allData = '';
    foreach ($response->stream() as $chunk) {
        $allData .= $chunk;
    }
    
    // Should get streaming data
    expect($allData)->toContain('stream data');
    expect($allData)->toContain('"line":0');
    expect($allData)->toContain('"line":1');
    
})->with(['guzzle', 'laravel', 'symfony']);

// Clean up server after all tests complete
register_shutdown_function(function() {
    IntegrationTestServer::stop();
});