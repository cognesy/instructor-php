<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Symfony\SymfonyDriver;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

it('passes shared transport options to symfony requests', function () {
    $captured = [];
    $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$captured): MockResponse {
        $captured = $options;
        return new MockResponse('{}', ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
    });

    $driver = new SymfonyDriver(
        config: new HttpClientConfig(
            driver: 'symfony',
            verifyTls: false,
            followRedirects: false,
            maxRedirects: 2,
            httpVersion: '1.1',
        ),
        events: new EventDispatcher(),
        clientInstance: $client,
    );

    $driver->handle(new HttpRequest(
        url: 'https://example.test',
        method: 'GET',
        headers: [],
        body: '',
        options: [],
    ));

    expect($captured['verify_peer'])->toBeFalse()
        ->and($captured['verify_host'])->toBeFalse()
        ->and($captured['max_redirects'])->toBe(0)
        ->and($captured['http_version'])->toBe('1.1');
});
