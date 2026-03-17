<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Errors\ProviderErrorClassifier;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('sanitizes provider-classified http exception messages', function () {
    $request = new HttpRequest(
        url: 'https://user:pass@example.com/v1/chat?api_key=super-secret&token=url-token&q=ok',
        method: 'POST',
        headers: [],
        body: [],
        options: [],
    );
    $httpError = new HttpRequestException('Network down', $request);

    $providerError = ProviderErrorClassifier::fromHttpException($httpError);
    $message = $providerError->getMessage();

    expect($message)->toContain('[REDACTED]')
        ->and($message)->toContain('q=ok')
        ->and($message)->not->toContain('super-secret')
        ->and($message)->not->toContain('url-token')
        ->and($message)->not->toContain('user:pass@');
});

it('emits sanitized attempt failure telemetry with provider status code', function () {
    $events = new EventDispatcher();
    $captured = null;
    $events->addListener(InferenceAttemptFailed::class, function (InferenceAttemptFailed $event) use (&$captured): void {
        $captured = $event;
    });

    $driver = new FakeInferenceDriver(
        onResponse: fn() => throw new ProviderInvalidRequestException(
            'Provider error. Request: POST https://user:pass@example.com/v1/chat?api_key=super-secret&token=url-token&q=ok',
            401,
        ),
    );

    $request = (new InferenceRequestBuilder())
        ->withMessages(\Cognesy\Messages\Messages::fromString('test'))
        ->withRetryPolicy(new InferenceRetryPolicy(maxAttempts: 1))
        ->create();

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: $events,
    );

    expect(fn() => $pending->response())->toThrow(ProviderInvalidRequestException::class);

    expect($captured)->not->toBeNull()
        ->and($captured->data['httpStatusCode'])->toBe(401)
        ->and($captured->data['errorMessage'])->toContain('[REDACTED]')
        ->and($captured->data['errorMessage'])->toContain('q=ok')
        ->and($captured->data['errorMessage'])->not->toContain('super-secret')
        ->and($captured->data['errorMessage'])->not->toContain('url-token')
        ->and($captured->data['errorMessage'])->not->toContain('user:pass@');
});
