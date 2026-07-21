<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Messages\Messages;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;

/**
 * Regression: the projector previously read the non-existent `exception` key
 * for InferenceAttemptFailed while the runtime emits the sanitized failure
 * text under `errorMessage`, dropping error.message from failed-attempt spans.
 */

function runFailingInferenceWithProjector(
    int $maxAttempts,
    \Throwable $error,
    string $expectedThrowable,
): OtelExporter {
    $otel = new OtelExporter();
    $hub = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));

    $events = new EventDispatcher('polyglot.attempt-failed.projector.test');
    (new RuntimeEventBridge(new PolyglotTelemetryProjector($hub)))->attachTo($events);

    $driver = new FakeInferenceDriver(
        onResponse: fn() => throw $error,
    );

    $request = new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-failure',
        // baseDelayMs=0 + jitter=none keeps retry loops instant and deterministic.
        retryPolicy: new InferenceRetryPolicy(
            maxAttempts: $maxAttempts,
            baseDelayMs: 0,
            jitter: 'none',
        ),
    );

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest($request),
        driver: $driver,
        eventDispatcher: $events,
    );

    expect(fn() => $pending->response())->toThrow($expectedThrowable);

    return $otel;
}

function attemptFailureObservations(OtelExporter $otel): array
{
    return array_values(array_filter(
        $otel->observations(),
        fn($observation) => $observation->name() === 'llm.inference.attempt',
    ));
}

it('projects a populated, sanitized error.message for terminal attempt failures', function () {
    $otel = runFailingInferenceWithProjector(
        maxAttempts: 1,
        error: new ProviderInvalidRequestException(
            'Provider rejected request calling https://api.example.com/v1/chat?api_key=super-secret&q=ok',
            401,
        ),
        expectedThrowable: ProviderInvalidRequestException::class,
    );

    $attempts = attemptFailureObservations($otel);
    expect($attempts)->not->toBeEmpty();

    $attributes = $attempts[0]->attributes()->toArray();
    expect($attributes['error.message'] ?? null)
        ->not->toBeNull()
        ->toContain('Provider rejected request')
        // sensitive query params are masked (URL-encoded) in the sanitized text
        ->toContain('api_key=%5BREDACTED%5D')
        ->not->toContain('super-secret');
    expect($attributes['http.response.status_code'] ?? null)->toBe(401);
});

it('projects a populated error.message for each retried attempt failure', function () {
    $otel = runFailingInferenceWithProjector(
        maxAttempts: 3,
        // TimeoutException is retryable, so every attempt (including retries) fails.
        error: new TimeoutException('Timed out calling https://api.example.com/v1/chat?api_key=super-secret&q=ok'),
        expectedThrowable: TimeoutException::class,
    );

    $attempts = attemptFailureObservations($otel);
    expect(count($attempts))->toBeGreaterThan(1);

    foreach ($attempts as $observation) {
        $attributes = $observation->attributes()->toArray();
        expect($attributes['error.message'] ?? null)
            ->not->toBeNull()
            ->toContain('Timed out calling')
            ->not->toContain('super-secret');
    }
});
