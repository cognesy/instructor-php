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

const CLEF_FEATURE_URL = 'https://api.cloudflare.com/client/v4/accounts/acct/ai/run/@cf/cloudflare/clef';

it('executes a Decision through the Clef HTTP driver', function (): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(CLEF_FEATURE_URL)
        ->header('Authorization', 'Bearer synthetic-test-key')
        ->withJsonSubset(['model' => 'clef'])
        ->times(1)
        ->replyJson([
            'result' => [
                'model' => 'clef',
                'answers' => ['urgent' => ['type' => 'noul', 'noul' => 0.9897]],
                'usage' => ['input_tokens' => 42, 'output_tokens' => 0],
            ],
            'success' => true,
            'errors' => [],
            'messages' => [],
        ], headers: ['cf-ray' => 'ray-7']);

    $response = decisionClefFeatureDriver($mock)->handle(decisionClefFeatureRequest());

    expect($response->answers()->noul('urgent')->probability())->toBe(0.9897)
        ->and($response->usage()->toArray())->toBe(['input' => 42, 'output' => 0])
        ->and($response->providerRequestId()->toString())->toBe('ray-7');
});

it('classifies Clef HTTP failures without leaking provider bodies', function (
    int $status,
    string $exceptionClass,
    bool $retriable,
): void {
    $mock = new MockHttpDriver;
    $mock->on()
        ->post(CLEF_FEATURE_URL)
        ->replyJson(['errors' => [['code' => 5006, 'message' => 'provider-body-sentinel']], 'success' => false], status: $status);

    try {
        decisionClefFeatureDriver($mock)->handle(decisionClefFeatureRequest());
        test()->fail('Expected the Clef driver to reject the HTTP failure.');
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
    'server failure' => [503, DecisionTransientException::class, true],
]);

function decisionClefFeatureDriver(MockHttpDriver $mock): Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest
{
    return DecisionDriverRegistry::default()->makeDriver(
        'clef',
        new DecisionConfig(
            driver: 'clef',
            apiUrl: 'https://api.cloudflare.com/client/v4/accounts/acct/ai',
            apiKey: 'synthetic-test-key',
            endpoint: '/run/@cf/cloudflare/{model}',
            model: 'clef',
        ),
        (new HttpClientBuilder)->withDriver($mock)->create(),
        new EventDispatcher,
    );
}

function decisionClefFeatureRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Checkout has been failing for every customer for the last hour.',
        questions: Questions::of(new Noul('urgent', 'Is this support request urgent?')),
    );
}
