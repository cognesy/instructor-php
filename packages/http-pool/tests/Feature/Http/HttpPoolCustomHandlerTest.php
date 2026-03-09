<?php declare(strict_types=1);

use Cognesy\Http\Collections\HttpRequestList;
use Cognesy\Http\Collections\HttpResponseList;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\HttpPool\Config\HttpPoolConfig;
use Cognesy\HttpPool\Contracts\CanHandleRequestPool;
use Cognesy\HttpPool\Creation\HttpPoolBuilder;
use Cognesy\HttpPool\Creation\HttpPoolRegistry;
use Cognesy\Utils\Result\Result;

test('pool accepts explicit custom handler injection', function () {
    $poolHandler = new class implements CanHandleRequestPool {
        public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList
        {
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

    $pool = (new HttpPoolBuilder())
        ->withPoolHandler($poolHandler)
        ->create();

    $requests = HttpRequestList::of(
        new HttpRequest('https://api.example.com/a', 'GET', [], '', []),
        new HttpRequest('https://api.example.com/b', 'GET', [], '', []),
    );

    $results = $pool->pool($requests);

    expect($results->successCount())->toBe(2)
        ->and($results->failureCount())->toBe(0);

    $firstPayload = json_decode($results->successful()[0]->body(), true);
    $secondPayload = json_decode($results->successful()[1]->body(), true);

    expect($firstPayload['url'])->toBe('https://api.example.com/a')
        ->and($secondPayload['url'])->toBe('https://api.example.com/b');
});

test('registered custom pool handler can be resolved from config driver name', function () {
    $driverName = 'custom-pool-' . uniqid();
    $state = (object) ['handlerUsed' => false];
    $registry = HttpPoolRegistry::make()->withPool(
        $driverName,
        function (HttpPoolConfig $config, $events) use ($state) {
            return new class($config, $state) implements CanHandleRequestPool {
                public function __construct(
                    private HttpPoolConfig $config,
                    private object $state,
                ) {}

                public function pool(HttpRequestList $requests, ?int $maxConcurrent = null): HttpResponseList
                {
                    $this->state->handlerUsed = true;

                    return HttpResponseList::empty();
                }
            };
        },
    );

    $pool = (new HttpPoolBuilder())
        ->withConfig(new HttpPoolConfig(driver: $driverName))
        ->withPools($registry)
        ->create();

    $results = $pool->pool(HttpRequestList::empty());

    expect($results)->toBeInstanceOf(HttpResponseList::class)
        ->and($results->count())->toBe(0)
        ->and($state->handlerUsed)->toBeTrue();
});
