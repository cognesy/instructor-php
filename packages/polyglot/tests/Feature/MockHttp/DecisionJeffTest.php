<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('executes a keyless Jeff decision through its registered driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('http://127.0.0.1:8000/v1/systemone')
        ->withJsonSubset(['model' => 'gliformer-large-v1'])
        ->replyJson([
            'model' => 'gliformer-large-v1',
            'answers' => ['safe' => ['type' => 'noul', 'noul' => 0.8]],
            'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
        ], headers: [
            'x-typesafe-request-id' => 'request-1',
            'x-jeff-server-ms' => '8.2',
            'x-jeff-batcher-ms' => '1.1',
        ]);
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'jeff',
        decisionJeffFeatureConfig(),
        $http,
        new EventDispatcher,
    );

    $response = $driver->handle(decisionJeffFeatureRequest());

    expect($response->answers()->noul('safe')->probability())->toBe(0.8)
        ->and($response->providerRequestId()->toString())->toBe('request-1')
        ->and($mock->getLastRequest()?->headers())->not->toHaveKey('Authorization');
});

it('classifies Jeff HTTP failures safely', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('http://127.0.0.1:8000/v1/systemone')
        ->replyJson(
            ['error' => 'provider-body-sentinel'],
            status: $status,
            headers: ['Retry-After' => '2', 'Retry-After-Ms' => '2000'],
        );
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'jeff',
        decisionJeffFeatureConfig(),
        $http,
        new EventDispatcher,
    );

    try {
        $driver->handle(decisionJeffFeatureRequest());
        test()->fail('Expected the Jeff driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->retryAfter)->toBe('2')
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-body-sentinel');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'forbidden' => [403, DecisionAuthenticationException::class, false],
    'invalid request' => [422, DecisionInvalidRequestException::class, false],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'queue full' => [529, DecisionTransientException::class, true],
    'server failure' => [503, DecisionTransientException::class, true],
]);

it('classifies Jeff network failures as transient', function (): void {
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'jeff',
        decisionJeffFeatureConfig(),
        new DecisionJeffFailingHttpClient,
        new EventDispatcher,
    );

    expect(fn () => $driver->handle(decisionJeffFeatureRequest()))
        ->toThrow(DecisionTransientException::class, 'network request failed');
});

function decisionJeffFeatureConfig(): DecisionConfig
{
    return new DecisionConfig(
        driver: 'jeff',
        apiUrl: 'http://127.0.0.1:8000',
        endpoint: '/v1/systemone',
        model: 'gliformer-large-v1',
    );
}

function decisionJeffFeatureRequest(): DecisionRequest
{
    return new DecisionRequest('state', Questions::of(new Noul('safe')));
}

final class DecisionJeffFailingHttpClient implements CanSendHttpRequests
{
    #[Override]
    public function send(HttpRequest $request): PendingHttpResponse
    {
        throw new HttpRequestException('network failed', request: $request);
    }
}
