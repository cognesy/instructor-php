<?php

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanHandleRequestPool;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Creation\HttpClientDriverFactory;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Utils\Result\Result;

test('client accepts explicit pool handler injection for custom drivers', function() {
    $poolHandler = new class implements CanHandleRequestPool {
        public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
            $results = [];
            foreach ($requests as $request) {
                $results[] = Result::success(HttpResponse::sync(
                    statusCode: 200,
                    headers: ['Content-Type' => 'application/json'],
                    body: json_encode(['url' => $request->url()]),
                ));
            }
            return HttpResponseList::fromArray($results);
        }
    };

    $client = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->withPoolHandler($poolHandler)
        ->create();

    $requests = HttpRequestList::of(
        new HttpRequest('https://api.example.com/a', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/b', 'GET', [], '', []),
    );

    $results = $client->pool($requests);

    expect($results->successCount())->toBe(2);
    expect($results->failureCount())->toBe(0);
    $firstPayload = json_decode($results->successful()[0]->body(), true);
    $secondPayload = json_decode($results->successful()[1]->body(), true);
    expect($firstPayload['url'])->toBe('https://api.example.com/a');
    expect($secondPayload['url'])->toBe('https://api.example.com/b');
});

test('registered custom pool handler can be resolved from config driver name', function() {
    $driverName = 'custom-pool-' . uniqid();
    $state = (object)['handlerUsed' => false];

    HttpClientDriverFactory::registerPoolHandler($driverName, function(HttpClientConfig $config, $events) use ($state) {
        return new class($config, $state) implements CanHandleRequestPool {
            public function __construct(
                private HttpClientConfig $config,
                private object $state,
            ) {}

            public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList {
                $this->state->handlerUsed = true;
                return HttpResponseList::empty();
            }
        };
    });

    $client = (new HttpClientBuilder())
        ->withDriver(new MockHttpDriver())
        ->withConfig(new HttpClientConfig(driver: $driverName))
        ->create();

    $results = $client->pool(HttpRequestList::empty());

    expect($results)->toBeInstanceOf(HttpResponseList::class);
    expect($results->count())->toBe(0);
    expect($state->handlerUsed)->toBeTrue();
});
