<?php declare(strict_types=1);

require_once __DIR__ . '/../Support/HttpTestRouter.php';
require_once __DIR__ . '/../Support/IntegrationTestServer.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Instructor\Laravel\HttpClient\LaravelDriver;
use Cognesy\Instructor\Laravel\Tests\Support\IntegrationTestServer;
use Illuminate\Http\Client\Factory as HttpFactory;

beforeEach(function() {
    $this->baseUrl = IntegrationTestServer::start();
    $this->events = new EventDispatcher();
    $this->config = new HttpClientConfig(
        requestTimeout: 30,
        connectTimeout: 5,
        failOnError: false,
    );
});

function createLaravelStreamingDriver(HttpClientConfig $config, EventDispatcher $events): LaravelDriver {
    return new LaravelDriver($config, $events, new HttpFactory());
}

test('laravel driver handles streaming responses', function () {
    $driver = createLaravelStreamingDriver($this->config, $this->events);
    $response = $driver->handle(new HttpRequest($this->baseUrl . '/stream/3', 'GET', [], '', ['stream' => true]));

    expect($response)->toBeInstanceOf(HttpResponse::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->isStreamed())->toBeTrue();
});

test('laravel driver handles SSE responses', function () {
    $driver = createLaravelStreamingDriver($this->config, $this->events);
    $response = $driver->handle(new HttpRequest($this->baseUrl . '/sse/3', 'GET', [], '', ['stream' => true]));

    $allData = '';
    foreach ($response->stream() as $chunk) {
        $allData .= $chunk;
    }

    expect($allData)->toContain('id: event_0')
        ->and($allData)->toContain('event: message');
});

test('laravel driver preserves plain-text request body', function () {
    $driver = createLaravelStreamingDriver(
        new HttpClientConfig(connectTimeout: 3, requestTimeout: 30, failOnError: false),
        $this->events,
    );

    $response = $driver->handle(new HttpRequest(
        url: $this->baseUrl . '/post',
        method: 'POST',
        headers: ['Content-Type' => 'text/plain'],
        body: 'plain-text-body',
        options: [],
    ));

    expect($response->statusCode())->toBe(200)
        ->and($response->body())->toContain('"data": "plain-text-body"');
});

register_shutdown_function(function() {
    IntegrationTestServer::stop();
});
