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
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Drivers\Respan\RespanDriver;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAccessException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('executes a RESPAN decision through its driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.respan.ai/api/v1/scores')
        ->withJsonSubset(['model' => 'span-01-free'])
        ->replyJson([
            'model' => 'span-01-free',
            'results' => [[
                'id' => 'apology',
                'p_present' => 0.9,
                'p_absent' => 0.08,
                'p_not_observable' => 0.02,
            ]],
            'usage' => ['input_tokens' => 21],
        ], headers: ['x-respan-log-id' => 'log-1']);
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'respan',
        respanFeatureConfig(),
        $http,
        new EventDispatcher,
    );

    $response = $driver->handle(respanFeatureRequest());

    expect($response->answers()->noul('apology')->probability())->toBe(0.9)
        ->and($response->answers()->noul('apology')->probabilities()->unknown())->toBe(0.02)
        ->and($response->providerRequestId()->toString())->toBe('log-1')
        ->and($mock->getLastRequest()?->headers()['Authorization'])->toBe('Bearer test-key');
});

it('classifies RESPAN HTTP failures safely', function (
    int $status,
    array $body,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post('https://api.respan.ai/api/v1/scores')
        ->replyJson($body, status: $status, headers: ['Retry-After' => '1']);
    $http = (new HttpClientBuilder)->withDriver($mock)->create();
    $driver = new RespanDriver(respanFeatureConfig(), $http, new EventDispatcher);

    try {
        $driver->handle(respanFeatureRequest());
        test()->fail('Expected the RESPAN driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->retryAfter)->toBe('1')
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-secret');
    }
})->with([
    'authentication' => [401, ['detail' => 'provider-secret'], DecisionAuthenticationException::class, false],
    'entitlement' => [403, ['detail' => 'Span-01 scoring is not enabled.'], DecisionAccessException::class, false],
    'credits' => [402, ['detail' => 'Insufficient credits.'], DecisionAccessException::class, false],
    'invalid request' => [422, ['detail' => 'Invalid behavior.'], DecisionInvalidRequestException::class, false],
    'dependency' => [424, ['detail' => 'Unavailable.'], DecisionTransientException::class, true],
    'rate limit' => [429, ['detail' => 'Limited.'], DecisionRateLimitException::class, true],
    'server failure' => [503, ['detail' => 'Unavailable.'], DecisionTransientException::class, true],
]);

it('classifies RESPAN network failures as transient', function (): void {
    $driver = new RespanDriver(
        respanFeatureConfig(),
        new DecisionRespanFailingHttpClient,
        new EventDispatcher,
    );

    expect(fn () => $driver->handle(respanFeatureRequest()))
        ->toThrow(DecisionTransientException::class, 'network request failed');
});

function respanFeatureConfig(): DecisionConfig
{
    return new DecisionConfig(
        driver: 'respan',
        apiUrl: 'https://api.respan.ai',
        apiKey: 'test-key',
        endpoint: '/api/v1/scores',
        model: 'span-01-free',
    );
}

function respanFeatureRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: JsonContent::object([
            'input' => [['role' => 'user', 'content' => 'My order is late.']],
            'output' => ['role' => 'assistant', 'content' => 'I am sorry.'],
        ]),
        questions: Questions::of(new Noul('apology', 'The assistant apologizes.')),
        model: 'span-01-free',
    );
}

final class DecisionRespanFailingHttpClient implements CanSendHttpRequests
{
    #[Override]
    public function send(HttpRequest $request): PendingHttpResponse
    {
        throw new HttpRequestException('network failed', request: $request);
    }
}
