<?php declare(strict_types=1);

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Tests\Support\IntegrationTestServer;

beforeEach(function () {
    $this->baseUrl = IntegrationTestServer::start();
});

afterEach(function () {
    IntegrationTestServer::stop();
});

it('default HttpClient does not throw on 5xx for single request path', function () {
    $client = (new HttpClientBuilder())->create();

    $response = $client
        ->send(new HttpRequest(
            url: $this->baseUrl . '/status/500',
            method: 'GET',
            headers: [],
            body: '',
            options: [],
        ))
        ->get();

    expect($response->statusCode())->toBe(500);
});
