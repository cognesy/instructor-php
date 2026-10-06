<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAccessException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;

const FASTINO_FEATURE_URL = 'https://api.fastino.ai/v1/systemone';

it('executes and memoizes a mixed Decision through the Fastino HTTP driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(FASTINO_FEATURE_URL)
        ->header('X-API-Key', 'synthetic-test-key')
        ->withJsonSubset(['model' => 'fastino/GLiDE'])
        ->times(1)
        ->replyJson(decisionFastinoFeatureLiveJson(), headers: ['x-request-id' => 'req-7']);

    $pending = decisionFastinoFeaturePending($mock, new DecisionFastinoFeatureDelay);
    $first = $pending->response();
    $second = $pending->response();

    expect($first->model())->toBe('glide')
        ->and($first->answers()->choice('department')->value())->toBe('returns')
        ->and($first->answers()->score('urgency')->value())->toBe(0.271362383978579)
        ->and($first->usage()->toArray())->toBe(['input' => 618, 'output' => 105])
        ->and($first->providerRequestId()->toString())->toBe('req-7')
        ->and($second)->toBe($first)
        ->and($mock->getReceivedRequests())->toHaveCount(1);
});

it('classifies Fastino HTTP failures without leaking provider bodies', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(FASTINO_FEATURE_URL)
        ->replyJson(['detail' => 'provider-body-sentinel'], status: $status);

    try {
        decisionFastinoFeaturePending($mock, new DecisionFastinoFeatureDelay, maxAttempts: 1)->response();
        test()->fail('Expected the Fastino driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-body-sentinel')
            ->and($exception->getMessage())->not->toContain('synthetic-test-key');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'insufficient credits' => [402, DecisionAccessException::class, false],
    'billing or policy denial' => [403, DecisionAccessException::class, false],
    'unknown model' => [404, DecisionInvalidRequestException::class, false],
    'validation' => [422, DecisionInvalidRequestException::class, false],
    'model warming' => [425, DecisionTransientException::class, true],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'service unavailable' => [503, DecisionTransientException::class, true],
]);

it('does not retry terminal Fastino failures', function (int $status): void {
    $mock = new MockHttpDriver;
    $mock->on()->post(FASTINO_FEATURE_URL)->replyJson(['detail' => 'no'], status: $status);
    $delay = new DecisionFastinoFeatureDelay;

    expect(fn () => decisionFastinoFeaturePending($mock, $delay)->response())
        ->toThrow(DecisionProviderException::class)
        ->and($mock->getReceivedRequests())->toHaveCount(1)
        ->and($delay->delays)->toBe([]);
})->with([401, 402, 403, 404, 422]);

it('waits for the warming model before retrying', function (int $status, array $headers, int $expectedDelay): void {
    $mock = new MockHttpDriver;
    $mock->on()->post(FASTINO_FEATURE_URL)->times(1)->replyJson(['detail' => 'busy'], status: $status, headers: $headers);
    $mock->on()->post(FASTINO_FEATURE_URL)->times(1)->replyJson(decisionFastinoFeatureLiveJson());
    $delay = new DecisionFastinoFeatureDelay;

    $response = decisionFastinoFeaturePending($mock, $delay)->response();

    expect($response->model())->toBe('glide')
        ->and($mock->getReceivedRequests())->toHaveCount(2)
        ->and($delay->delays)->toBe([$expectedDelay]);
})->with([
    'headerless warming uses the documented 60 s' => [425, [], 60000],
    'warming honors a shorter Retry-After' => [425, ['Retry-After' => '10'], 10000],
    'warming Retry-After is clamped to the ceiling' => [425, ['Retry-After' => '120'], 65000],
    'headerless rate limit keeps policy backoff' => [429, [], 250],
    'headerless service failure keeps policy backoff' => [503, [], 250],
]);

function decisionFastinoFeaturePending(
    MockHttpDriver $mock,
    CanDelayRetries $delay,
    int $maxAttempts = 3,
): Cognesy\Polyglot\Decision\PendingDecision {
    $runtime = DecisionRuntime::fromConfig(
        new DecisionConfig(
            driver: 'fastino',
            apiUrl: 'https://api.fastino.ai/v1',
            apiKey: 'synthetic-test-key',
            endpoint: '/systemone',
            model: 'fastino/GLiDE',
        ),
        events: new EventDispatcher,
        httpClient: (new HttpClientBuilder)->withDriver($mock)->create(),
        retryDelay: $delay,
    );

    return Decision::fromRuntime($runtime)
        ->withRequest(decisionFastinoFeatureRequest())
        ->withRetryPolicy(new DecisionRetryPolicy(
            maxAttempts: $maxAttempts,
            baseDelayMs: 250,
            maxDelayMs: 65000,
            jitter: 'none',
        ))
        ->create();
}

function decisionFastinoFeatureRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Refund request: the receipt is attached, the purchase was 10 days ago, and refunds are allowed within 30 days.',
        questions: Questions::of(
            new Noul('refund', 'Does this request qualify for a refund?'),
            new Choice(
                id: 'department',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payment or charge disputes'),
                    new ChoiceOption('returns', 'Refund or return requests'),
                    new ChoiceOption('shipping', 'Delivery or shipping issues'),
                ),
                instructions: 'Which team should handle this request?',
            ),
            new Score('urgency', ScoreLevels::of('low', 'medium', 'high'), 'How urgent is this request?'),
        ),
    );
}

function decisionFastinoFeatureLiveJson(): string
{
    $json = file_get_contents(__DIR__.'/../../Fixtures/Decision/fastino-live-response.json');
    if (! is_string($json)) {
        throw new RuntimeException('Fastino live response fixture cannot be read.');
    }

    return $json;
}

final class DecisionFastinoFeatureDelay implements CanDelayRetries
{
    /** @var list<int> */
    public array $delays = [];

    #[Override]
    public function delay(int $milliseconds): void
    {
        $this->delays[] = $milliseconds;
    }
}
