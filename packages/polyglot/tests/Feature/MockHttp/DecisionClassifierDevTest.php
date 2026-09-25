<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Http\PendingHttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAuthenticationException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionRateLimitException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('executes a keyless classifier.dev decision through its registered driver', function (): void {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://classifier.dev/v1/classify')
        ->withJsonSubset(['tier' => 'fast', 'items' => ['billing issue']])
        ->replyJson([
            'tier' => 'fast',
            'model' => 'jev-test',
            'results' => [[
                'dimensions' => [
                    'q0' => [
                        'label' => 'yes',
                        'confidence' => 0.9,
                        'scores' => ['yes' => 0.9, 'no' => 0.1],
                        'model' => 'jev-test',
                        'ms' => 4,
                    ],
                    'q1' => [
                        'label' => 'billing',
                        'confidence' => 0.8,
                        'scores' => ['billing' => 0.8, 'technical' => 0.2],
                        'model' => 'jev-test',
                        'ms' => 4,
                    ],
                    'q2' => [
                        'label' => 'level 1: high',
                        'confidence' => 0.7,
                        'scores' => ['level 0: low' => 0.3, 'level 1: high' => 0.7],
                        'model' => 'jev-test',
                        'ms' => 4,
                    ],
                ],
            ]],
            'usage' => ['items' => 1, 'dimensions' => 3, 'classifications' => 3, 'ms' => 4],
        ], headers: ['Idempotency-Key' => 'echoed-request']);
    $http = (new HttpClientBuilder())->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'classifier-dev',
        decisionClassifierDevFeatureConfig(),
        $http,
        new EventDispatcher(),
    );

    $response = $driver->handle(decisionClassifierDevFeatureRequest());

    expect($response->answers()->count())->toBe(3)
        ->and($response->answers()->noul('safe')->probability())->toBe(0.9)
        ->and($response->answers()->choice('route')->value())->toBe('billing')
        ->and($response->answers()->score('impact')->value())->toBe(0.7)
        ->and($response->providerRequestId()->toString())->toBe('echoed-request')
        ->and($mock->getReceivedRequests())->toHaveCount(1)
        ->and($mock->getLastRequest()?->headers())->not->toHaveKey('Authorization')
        ->and($mock->getLastRequest()?->headers())->toHaveKey('Idempotency-Key');
});

it('classifies classifier.dev HTTP failures safely', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver();
    $mock->on()
        ->post('https://classifier.dev/v1/classify')
        ->replyJson(
            ['error' => 'provider-body-sentinel'],
            status: $status,
            headers: ['Retry-After' => '17'],
        );
    $http = (new HttpClientBuilder())->withDriver($mock)->create();
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'classifier-dev',
        decisionClassifierDevFeatureConfig(),
        $http,
        new EventDispatcher(),
    );

    try {
        $driver->handle(decisionClassifierDevFeatureRequest());
        test()->fail('Expected the classifier.dev driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->retryAfter)->toBe('17')
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-body-sentinel');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'forbidden' => [403, DecisionAuthenticationException::class, false],
    'invalid request' => [400, DecisionInvalidRequestException::class, false],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'upstream failure' => [502, DecisionTransientException::class, true],
    'server failure' => [503, DecisionTransientException::class, true],
]);

it('classifies classifier.dev network failures as transient', function (): void {
    $driver = DecisionDriverRegistry::default()->makeDriver(
        'classifier-dev',
        decisionClassifierDevFeatureConfig(),
        new DecisionClassifierDevFailingHttpClient(),
        new EventDispatcher(),
    );

    expect(fn () => $driver->handle(decisionClassifierDevFeatureRequest()))
        ->toThrow(DecisionTransientException::class, 'network request failed');
});

function decisionClassifierDevFeatureConfig(): DecisionConfig {
    return new DecisionConfig(
        driver: 'classifier-dev',
        apiUrl: 'https://classifier.dev',
        endpoint: '/v1/classify',
        model: 'fast',
    );
}

function decisionClassifierDevFeatureRequest(): DecisionRequest {
    return new DecisionRequest('billing issue', Questions::of(
        new Noul('safe'),
        new Choice('route', ChoiceOptions::of(
            new ChoiceOption('billing'),
            new ChoiceOption('technical'),
        )),
        new Score('impact', ScoreLevels::of('low', 'high')),
    ));
}

final class DecisionClassifierDevFailingHttpClient implements CanSendHttpRequests
{
    #[Override]
    public function send(HttpRequest $request): PendingHttpResponse {
        throw new HttpRequestException('network failed', request: $request);
    }
}
