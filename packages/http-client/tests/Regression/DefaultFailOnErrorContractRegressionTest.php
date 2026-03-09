<?php declare(strict_types=1);

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
    $this->client = HttpClient::default();
});

it('default HttpClient does not throw on 5xx for single request path', function () {
    $request = new HttpRequest(
        url: $this->baseUrl . '/status/500',
        method: 'GET',
        headers: [],
        body: '',
        options: [],
    );

    $response = $this->client->send($request)->get();

    expect($response->statusCode())->toBe(500);
});

register_shutdown_function(function () {
    IntegrationTestServer::stop();
});
