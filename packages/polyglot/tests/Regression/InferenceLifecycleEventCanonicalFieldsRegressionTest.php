<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

it('keeps canonical started and attempt-started fields authoritative', function () {
    $supplementary = [
        'executionId' => 'spoofed-execution',
        'requestId' => 'spoofed-request',
        'isStreamed' => true,
        'model' => 'spoofed-model',
        'messageCount' => 999,
        'attemptId' => 'spoofed-attempt',
        'attemptNumber' => 999,
        'isRetry' => false,
        'custom' => 'kept',
        TelemetryEnvelope::KEY => ['marker' => 'kept'],
    ];

    $started = InferenceStarted::fromLifecycle(
        'exec-1',
        'request-1',
        false,
        'model-1',
        2,
        $supplementary,
    );
    $attemptStarted = new InferenceAttemptStarted(
        'exec-1',
        'attempt-1',
        2,
        'model-1',
        $supplementary,
    );

    expect($started->data)->toMatchArray([
        'executionId' => 'exec-1',
        'requestId' => 'request-1',
        'isStreamed' => false,
        'model' => 'model-1',
        'messageCount' => 2,
        'custom' => 'kept',
        TelemetryEnvelope::KEY => ['marker' => 'kept'],
    ])->and($started->executionId())->toBe('exec-1')
        ->and($started->requestId())->toBe('request-1')
        ->and($attemptStarted->data)->toMatchArray([
            'executionId' => 'exec-1',
            'attemptId' => 'attempt-1',
            'attemptNumber' => 2,
            'model' => 'model-1',
            'isRetry' => true,
            'custom' => 'kept',
            TelemetryEnvelope::KEY => ['marker' => 'kept'],
        ]);
});

it('keeps canonical success usage and completion fields authoritative', function () {
    $usage = new InferenceUsage(
        inputTokens: 10,
        outputTokens: 4,
        cacheWriteTokens: 3,
        cacheReadTokens: 2,
        reasoningTokens: 1,
    );
    $supplementary = [
        'executionId' => 'spoofed-execution',
        'attemptId' => 'spoofed-attempt',
        'attemptNumber' => 999,
        'finishReason' => 'spoofed-finish',
        'durationMs' => 999.0,
        'model' => 'spoofed-model',
        'isFinal' => false,
        'isSuccess' => false,
        'attemptCount' => 999,
        'inputTokens' => 999,
        'outputTokens' => 999,
        'cacheWriteTokens' => 999,
        'cacheReadTokens' => 999,
        'reasoningTokens' => 999,
        'totalTokens' => 999,
        'custom' => 'kept',
    ];

    $succeeded = InferenceAttemptSucceeded::fromLifecycle(
        'exec-1', 'attempt-1', 1, 'stop', 12.5, $usage, $supplementary,
    );
    $reported = InferenceUsageReported::fromLifecycle(
        'exec-1', 'model-1', true, $usage, $supplementary,
    );
    $completed = InferenceCompleted::fromLifecycle(
        'exec-1', true, 'stop', 23.0, 1, $usage, $supplementary,
    );

    expect($succeeded->data)->toMatchArray([
        'executionId' => 'exec-1',
        'attemptId' => 'attempt-1',
        'attemptNumber' => 1,
        'finishReason' => 'stop',
        'durationMs' => 12.5,
        'inputTokens' => 10,
        'outputTokens' => 4,
        'cacheWriteTokens' => 3,
        'cacheReadTokens' => 2,
        'reasoningTokens' => 1,
        'totalTokens' => 20,
        'custom' => 'kept',
    ])->and($succeeded->totalTokens())->toBe(20)
        ->and($reported->data)->toMatchArray([
            'executionId' => 'exec-1',
            'model' => 'model-1',
            'isFinal' => true,
            'inputTokens' => 10,
            'outputTokens' => 4,
            'totalTokens' => 20,
            'custom' => 'kept',
        ])->and($reported->totalTokens())->toBe(20)
        ->and($completed->data)->toMatchArray([
            'executionId' => 'exec-1',
            'isSuccess' => true,
            'finishReason' => 'stop',
            'durationMs' => 23.0,
            'attemptCount' => 1,
            'inputTokens' => 10,
            'outputTokens' => 4,
            'totalTokens' => 20,
            'custom' => 'kept',
        ])->and($completed->totalTokens())->toBe(20);
});

it('keeps failed-attempt fields authoritative through telemetry projection', function () {
    $otel = new OtelExporter();
    $telemetry = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('polyglot.lifecycle.canonical-fields.test');
    (new RuntimeEventBridge(new PolyglotTelemetryProjector($telemetry)))->attachTo($events);

    $events->dispatch(InferenceStarted::fromLifecycle('exec-1', 'request-1', false, 'model-1', 1));
    $events->dispatch(new InferenceAttemptStarted('exec-1', 'attempt-1', 1, 'model-1'));
    $failed = InferenceAttemptFailed::fromLifecycle(
        executionId: 'exec-1',
        attemptId: 'attempt-1',
        attemptNumber: 1,
        errorMessage: 'canonical-error',
        errorType: RuntimeException::class,
        httpStatusCode: 503,
        willRetry: true,
        durationMs: 8.5,
        data: [
            'executionId' => 'spoofed-execution',
            'attemptId' => 'spoofed-attempt',
            'attemptNumber' => 999,
            'errorMessage' => 'spoofed-error',
            'errorType' => LogicException::class,
            'httpStatusCode' => 400,
            'willRetry' => false,
            'durationMs' => 999.0,
            'partialInputTokens' => 3,
            'custom' => 'kept',
        ],
    );
    $events->dispatch($failed);

    expect($failed->errorMessage())->toBe('canonical-error')
        ->and($failed->data)->toMatchArray([
            'executionId' => 'exec-1',
            'attemptId' => 'attempt-1',
            'attemptNumber' => 1,
            'errorMessage' => 'canonical-error',
            'errorType' => RuntimeException::class,
            'httpStatusCode' => 503,
            'willRetry' => true,
            'durationMs' => 8.5,
            'partialInputTokens' => 3,
            'custom' => 'kept',
        ]);

    $attempt = current(array_filter(
        $otel->observations(),
        static fn($observation): bool => $observation->name() === 'llm.inference.attempt',
    ));
    expect($attempt)->not->toBeFalse();
    $attributes = $attempt->attributes()->toArray();
    expect($attributes['error.message'] ?? null)->toBe('canonical-error')
        ->and($attributes['http.response.status_code'] ?? null)->toBe(503)
        ->and($attributes['inference.retry'] ?? null)->toBeTrue();
});
