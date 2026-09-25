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

it('executes a keyless Laya decision through its registered driver', function (): void {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('http://127.0.0.1:8091/v1/systemone')
        ->withJsonSubset(['model' => 'laya-typed-decisions'])
        ->replyJson([
            'model' => 'laya-typed-decisions@checkpoint-abc',
            'answers' => [
                'safe' => [
                    'type' => 'noul',
                    'noul' => 0.8,
                    'confidence' => 0.8,
                    'action' => ['act_probability' => 0.7],
                ],
            ],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 0],
        ], headers: [
            'x-request-id' => 'laya-request-7',
            'x-laya-backend' => 'mlx',
        ]);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'laya',
        decisionLayaFeatureConfig(),
        $http,
        new EventDispatcher(),
    );

    $response = $driver->handle(decisionLayaFeatureRequest());

    expect($response->answers()->noul('safe')->probability())->toBe(0.8)
        ->and($response->answers()->noul('safe')->signals()->modelActionProbability())->toBe(0.7)
        ->and($response->model())->toBe('laya-typed-decisions@checkpoint-abc')
        ->and($response->providerRequestId()->toString())->toBe('laya-request-7')
        ->and($mock->getLastRequest()?->headers())->not->toHaveKey('Authorization');
});

it('classifies Laya HTTP failures safely', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('http://127.0.0.1:8091/v1/systemone')
        ->replyJson(
            ['error' => ['code' => 'private-code', 'message' => 'private-state']],
            status: $status,
            headers: ['Retry-After' => '1'],
        );
    $http = (new HttpClientBuilder())->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'laya',
        decisionLayaFeatureConfig(),
        $http,
        new EventDispatcher(),
    );

    try {
        $driver->handle(decisionLayaFeatureRequest());
        test()->fail('Expected the Laya driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->retryAfter)->toBe('1')
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('private-state')
            ->and($exception->getMessage())->not->toContain('private-code');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'invalid request' => [400, DecisionInvalidRequestException::class, false],
    'body too large' => [413, DecisionInvalidRequestException::class, false],
    'unprocessable context' => [422, DecisionInvalidRequestException::class, false],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'backend failure' => [502, DecisionTransientException::class, true],
    'loading or queue full' => [503, DecisionTransientException::class, true],
    'timeout' => [504, DecisionTransientException::class, true],
]);

it('classifies a missing external Laya service as transient', function (): void {
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'laya',
        decisionLayaFeatureConfig(),
        new DecisionLayaFailingHttpClient(),
        new EventDispatcher(),
    );

    expect(fn () => $driver->handle(decisionLayaFeatureRequest()))
        ->toThrow(DecisionTransientException::class, 'network request failed');
});

function decisionLayaFeatureConfig(): DecisionConfig {
    return new DecisionConfig(
        driver: 'laya',
        apiUrl: 'http://127.0.0.1:8091',
        endpoint: '/v1/systemone',
        model: 'laya-typed-decisions',
    );
}

function decisionLayaFeatureRequest(): DecisionRequest {
    return new DecisionRequest('state', Questions::of(new Noul('safe', 'Safe?')));
}

final class DecisionLayaFailingHttpClient implements CanSendHttpRequests
{
    #[Override]
    public function send(HttpRequest $request): PendingHttpResponse {
        throw new HttpRequestException('network failed', request: $request);
    }
}
