<?php declare(strict_types=1);

use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Drivers\Guzzle\GuzzleDriver;
use Cognesy\Http\Drivers\Laravel\LaravelDriver;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Symfony\Component\EventDispatcher\EventDispatcher;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
});

function createErrorDriver(string $type, bool $failOnError = true): object {
    $config = new HttpClientConfig(failOnError: $failOnError);
    $events = new EventDispatcher();

    return match ($type) {
        'curl' => new CurlDriver($config, $events),
        'guzzle' => new GuzzleDriver($config, $events),
        'laravel' => new LaravelDriver($config, $events),
        'symfony' => new SymfonyDriver($config, $events),
        default => throw new InvalidArgumentException("Unknown driver type: {$type}"),
    };
}

it('throws consistent client error exceptions for all drivers', function (string $driverType) {
    $driver = createErrorDriver($driverType);
    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    try {
        $driver->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (HttpClientErrorException $e) {
        expect($e->getStatusCode())->toBe(404);
        expect($e->isRetriable())->toBeFalse();
        expect($e->getRequest())->toBe($request);
    }
})->with(['curl', 'guzzle', 'laravel', 'symfony']);

it('throws consistent server error exceptions for all drivers', function (string $driverType) {
    $driver = createErrorDriver($driverType);
    $request = new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []);

    try {
        $driver->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (ServerErrorException $e) {
        expect($e->getStatusCode())->toBe(500);
        expect($e->isRetriable())->toBeTrue();
        expect($e->getRequest())->toBe($request);
    }
})->with(['curl', 'guzzle', 'laravel', 'symfony']);

it('treats 429 rate limit error as retriable', function (string $driverType) {
    $driver = createErrorDriver($driverType);
    $request = new HttpRequest($this->baseUrl . '/status/429', 'GET', [], '', []);

    try {
        $driver->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (HttpClientErrorException $e) {
        expect($e->getStatusCode())->toBe(429);
        expect($e->isRetriable())->toBeTrue();
    }
})->with(['curl', 'guzzle', 'laravel', 'symfony']);

it('does not throw when fail on error is disabled', function (string $driverType) {
    $driver = createErrorDriver($driverType, failOnError: false);
    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    $response = $driver->handle($request);
    expect($response->statusCode())->toBe(404);
})->with(['curl', 'guzzle', 'laravel', 'symfony']);

it('handles network errors with invalid host', function () {
    $driver = createErrorDriver('guzzle');
    $request = new HttpRequest('https://invalid-host-that-does-not-exist.com', 'GET', [], '', []);

    try {
        $driver->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (NetworkException $e) {
        expect($e->isRetriable())->toBeTrue();
        expect($e->getRequest())->toBe($request);
        expect($e)->toBeInstanceOf(NetworkException::class);
    }
});

it('catches all exceptions via backward compatible base class', function () {
    $driver = createErrorDriver('guzzle');
    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    try {
        $driver->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (HttpRequestException $e) {
        expect($e->getRequest())->toBe($request);
    }
});

it('integrates http client with new exceptions', function () {
    $driver = createErrorDriver('guzzle');
    $client = new HttpClient($driver);

    try {
        $client->withRequest(new HttpRequest($this->baseUrl . '/status/422', 'GET', [], '', []))->get();
        $this->fail('Expected exception to be thrown');
    } catch (HttpClientErrorException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->isRetriable())->toBeFalse();
    }
});

it('formats exception messages correctly', function () {
    $driver = createErrorDriver('guzzle');
    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    try {
        $driver->handle($request);
    } catch (HttpClientErrorException $e) {
        $message = $e->getMessage();
        expect($message)->toContain('HTTP 404 Client Error');
        expect($message)->toContain('GET ' . $this->baseUrl . '/status/404');
        expect($message)->toContain('Status: 404');
    }
});

it('provides full exception context', function () {
    $driver = createErrorDriver('guzzle');
    $request = new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []);

    try {
        $driver->handle($request);
    } catch (ServerErrorException $e) {
        expect($e->getRequest())->toBe($request);
        expect($e->getResponse())->not->toBeNull();
        expect($e->getStatusCode())->toBe(500);
        expect($e->isRetriable())->toBeTrue();
    }
});
