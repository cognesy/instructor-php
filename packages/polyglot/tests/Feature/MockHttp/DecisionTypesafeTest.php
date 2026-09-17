<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Decision\Answers\ChoiceAnswer;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Answers\ScoreAnswer;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;

it('executes a mixed typed Decision through the TypeSafe HTTP driver', function () {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.example.test/v1/systemone')
        ->header('Authorization', 'Bearer synthetic-test-key')
        ->header('Content-Type', 'application/json')
        ->withJsonSubset(['model' => 'jev-test'])
        ->times(1)
        ->replyJson(
            decisionTypesafeFeatureResponseJson(),
            headers: ['x-typesafe-request-id' => 'mock-request-7'],
        );
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'typesafe',
        decisionTypesafeFeatureConfig(),
        $http,
        new EventDispatcher,
    );

    $response = $driver->handle(decisionTypesafeFeatureRequest());

    expect($response->answers()->noul('urgent'))->toBeInstanceOf(NoulAnswer::class)
        ->and($response->answers()->choice('route'))->toBeInstanceOf(ChoiceAnswer::class)
        ->and($response->answers()->score('impact'))->toBeInstanceOf(ScoreAnswer::class)
        ->and($response->answers()->choice('route')->value())->toBe('technical')
        ->and($response->answers()->score('impact')->value())->toBe(1.2)
        ->and($response->usage()->toArray())->toBe(['input' => 321, 'output' => 47])
        ->and($response->providerRequestId()->toString())->toBe('mock-request-7')
        ->and($mock->getReceivedRequests())->toHaveCount(1);
});

it('classifies TypeSafe HTTP failures without leaking provider or request bodies', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
) {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.example.test/v1/systemone')
        ->replyJson(['error' => 'provider-body-sentinel'], status: $status);
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'typesafe',
        decisionTypesafeFeatureConfig(),
        $http,
        new EventDispatcher,
    );

    try {
        $driver->handle(decisionTypesafeFeatureRequest());
        test()->fail('Expected the TypeSafe driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-body-sentinel')
            ->and($exception->getMessage())->not->toContain('synthetic-test-key')
            ->and($exception->getMessage())->not->toContain('checkout is unavailable');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'invalid request' => [422, DecisionInvalidRequestException::class, false],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'server failure' => [529, DecisionTransientException::class, true],
]);

it('retries a TypeSafe 529 once through the lazy Decision runtime and respects bounded Retry-After', function () {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.example.test/v1/systemone')
        ->times(1)
        ->replyJson(
            ['error' => 'overloaded'],
            status: 529,
            headers: ['Retry-After' => '3'],
        );
    $mock->on()
        ->post('https://api.example.test/v1/systemone')
        ->times(1)
        ->replyJson(decisionTypesafeFeatureResponseJson());
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $delay = new DecisionTypesafeFeatureDelay;
    $runtime = DecisionRuntime::fromConfig(
        decisionTypesafeFeatureConfig(),
        events: new EventDispatcher,
        httpClient: $http,
        retryDelay: $delay,
    );
    $pending = Decision::fromRuntime($runtime)
        ->withRequest(decisionTypesafeFeatureRequest())
        ->withRetryPolicy(new DecisionRetryPolicy(
            maxAttempts: 2,
            baseDelayMs: 0,
            maxDelayMs: 100,
            jitter: 'none',
        ))
        ->create();

    expect($mock->getReceivedRequests())->toHaveCount(0)
        ->and($pending->get()->choice('route')->value())->toBe('technical')
        ->and($mock->getReceivedRequests())->toHaveCount(2)
        ->and($delay->delays)->toBe([100]);
});

function decisionTypesafeFeatureConfig(): DecisionConfig
{
    return new DecisionConfig(
        driver: 'typesafe',
        apiUrl: 'https://api.example.test/v1',
        apiKey: 'synthetic-test-key',
        endpoint: '/systemone',
        model: 'jev-test',
    );
}

function decisionTypesafeFeatureRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'The checkout is unavailable and orders are blocked.',
        questions: Questions::of(
            new Noul('urgent'),
            new Choice('route', ChoiceOptions::of(
                new ChoiceOption('billing', 'Billing'),
                new ChoiceOption('technical', 'Technical support'),
            )),
            new Score('impact', ScoreLevels::of(
                'No impact',
                JsonContent::object(['label' => 'Degraded', 'workaround' => true]),
                JsonContent::list(['Blocked', (object) ['workaround' => false]]),
            )),
        ),
    );
}

function decisionTypesafeFeatureResponseJson(): string
{
    $path = __DIR__.'/../../Fixtures/Decision/typesafe-golden-responses.json';
    $json = file_get_contents($path);
    if (! is_string($json)) {
        throw new RuntimeException('TypeSafe golden response fixture cannot be read.');
    }
    $fixture = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
    if (! $fixture instanceof stdClass) {
        throw new RuntimeException('TypeSafe golden response fixture must be an object.');
    }

    return json_encode($fixture->mixed, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
}

final class DecisionTypesafeFeatureDelay implements CanDelayRetries
{
    /** @var list<int> */
    public array $delays = [];

    #[Override]
    public function delay(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}
