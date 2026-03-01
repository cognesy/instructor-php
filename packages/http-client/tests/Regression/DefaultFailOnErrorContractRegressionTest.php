<?php declare(strict_types=1);

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Tests\Support\IntegrationTestServer;
use Cognesy\Utils\Result\Failure;

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

    $response = $this->client->withRequest($request)->get();

    expect($response->statusCode())->toBe(500);
});

it('default HttpClient pool path returns Failure result on 5xx instead of throwing', function () {
    $requests = HttpRequestList::of(
        new HttpRequest($this->baseUrl . '/status/500', 'GET', [], '', []),
    );

    $results = $this->client->withPool($requests)->all();
    $resultArray = $results->all();

    expect($results)->toHaveCount(1)
        ->and($resultArray[0])->toBeInstanceOf(Failure::class);
});

register_shutdown_function(function () {
    IntegrationTestServer::stop();
});
