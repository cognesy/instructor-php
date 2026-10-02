<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
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

const PERPLEXITY_FEATURE_URL = 'https://api.perplexity.ai/v1/decisions';

it('executes a Decision through the Perplexity HTTP driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(PERPLEXITY_FEATURE_URL)
        ->header('Authorization', 'Bearer synthetic-test-key')
        ->withJsonSubset(['model' => 'pplx-decider-v1-27b'])
        ->times(1)
        ->replyJson([
            'model' => 'pplx-decider-v1-27b',
            'answers' => ['urgent' => ['type' => 'noul', 'noul' => 0.9897]],
            'usage' => ['input_tokens' => 42, 'output_tokens' => 1],
        ], headers: ['x-request-id' => 'req-7']);

    $response = decisionPerplexityFeatureDriver($mock)->handle(decisionPerplexityFeatureRequest());

    expect($response->answers()->noul('urgent')->probability())->toBe(0.9897)
        ->and($response->usage()->toArray())->toBe(['input' => 42, 'output' => 1])
        ->and($response->providerRequestId()->toString())->toBe('req-7');
});

it('classifies Perplexity HTTP failures without leaking provider bodies', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(PERPLEXITY_FEATURE_URL)
        ->replyJson(['error' => ['message' => 'provider-body-sentinel', 'type' => 'invalid_request']], status: $status);

    try {
        decisionPerplexityFeatureDriver($mock)->handle(decisionPerplexityFeatureRequest());
        test()->fail('Expected the Perplexity driver to reject the HTTP failure.');
    } catch (DecisionProviderException $exception) {
        expect($exception)->toBeInstanceOf($exceptionClass)
            ->and($exception->statusCode)->toBe($status)
            ->and($exception->isRetriable())->toBe($retriable)
            ->and($exception->getMessage())->not->toContain('provider-body-sentinel')
            ->and($exception->getMessage())->not->toContain('synthetic-test-key');
    }
})->with([
    'authentication' => [401, DecisionAuthenticationException::class, false],
    'invalid request' => [400, DecisionInvalidRequestException::class, false],
    'rate limit' => [429, DecisionRateLimitException::class, true],
    'gateway timeout' => [504, DecisionTransientException::class, true],
]);

function decisionPerplexityFeatureDriver(MockHttpDriver $mock): Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest
{
    return DecisionDriverRegistry::default()->makeDriver(
        'perplexity',
        new DecisionConfig(
            driver: 'perplexity',
            apiUrl: 'https://api.perplexity.ai',
            apiKey: 'synthetic-test-key',
            endpoint: '/v1/decisions',
            model: 'pplx-decider-v1-27b',
        ),
        (new HttpClientBuilder)->withDriver($mock)->create(),
        new EventDispatcher,
    );
}

function decisionPerplexityFeatureRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Checkout has been failing for every customer for the last hour.',
        questions: Questions::of(new Noul('urgent', 'Is this support request urgent?')),
    );
}
