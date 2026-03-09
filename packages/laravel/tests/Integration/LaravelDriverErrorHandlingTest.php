<?php declare(strict_types=1);

require_once __DIR__ . '/../Support/HttpTestRouter.php';
require_once __DIR__ . '/../Support/IntegrationTestServer.php';

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpClientErrorException;
use Cognesy\Http\Exceptions\NetworkException;
use Cognesy\Http\Exceptions\ServerErrorException;
use Cognesy\Instructor\Laravel\HttpClient\LaravelDriver;
use Cognesy\Instructor\Laravel\Tests\Support\IntegrationTestServer;
use Illuminate\Http\Client\Factory as HttpFactory;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
});

function createLaravelDriver(bool $failOnError = true): LaravelDriver {
    return new LaravelDriver(
        new HttpClientConfig(failOnError: $failOnError),
        new EventDispatcher(),
        new HttpFactory(),
    );
}

it('throws consistent client error exceptions', function () {
    $request = new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', []);

    try {
        createLaravelDriver()->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (HttpClientErrorException $e) {
        expect($e->getStatusCode())->toBe(404)
            ->and($e->isRetriable())->toBeFalse()
            ->and($e->getRequest())->toBe($request);
    }
});

it('throws consistent server error exceptions', function () {
    $request = new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []);

    try {
        createLaravelDriver()->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (ServerErrorException $e) {
        expect($e->getStatusCode())->toBe(500)
            ->and($e->isRetriable())->toBeTrue()
            ->and($e->getRequest())->toBe($request);
    }
});

it('does not throw when fail on error is disabled', function () {
    $response = createLaravelDriver(false)->handle(
        new HttpRequest($this->baseUrl . '/status/404', 'GET', [], '', [])
    );

    expect($response->statusCode())->toBe(404);
});

it('handles network errors with invalid host', function () {
    $request = new HttpRequest('https://invalid-host-that-does-not-exist.com', 'GET', [], '', []);

    try {
        createLaravelDriver()->handle($request);
        $this->fail('Expected exception to be thrown');
    } catch (NetworkException $e) {
        expect($e->isRetriable())->toBeTrue()
            ->and($e->getRequest())->toBe($request);
    }
});

register_shutdown_function(function () {
    IntegrationTestServer::stop();
});
