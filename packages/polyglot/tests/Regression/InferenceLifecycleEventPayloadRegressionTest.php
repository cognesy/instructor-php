<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Http\Exceptions\TimeoutException;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;

it('emits minimal array payloads for successful lifecycle events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-lifecycle',
            retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        )),
        driver: new FakeInferenceDriver(
            responses: [
                new InferenceResponse(
                    content: 'OK',
                    finishReason: 'stop',
                    usage: new InferenceUsage(inputTokens: 10, outputTokens: 3),
                ),
            ],
        ),
        eventDispatcher: $events,
    );

    $pending->response();

    expect(findLifecycleEvent($captured, InferenceStarted::class)->data)->toMatchArray([
        'isStreamed' => false,
        'model' => 'gpt-lifecycle',
        'messageCount' => 1,
    ]);

    expect(findLifecycleEvent($captured, InferenceAttemptSucceeded::class)->data)->toMatchArray([
        'attemptNumber' => 1,
        'finishReason' => 'stop',
        'inputTokens' => 10,
        'outputTokens' => 3,
        'totalTokens' => 13,
    ]);

    expect(findLifecycleEvent($captured, InferenceUsageReported::class)->data)->toMatchArray([
        'model' => 'gpt-lifecycle',
        'isFinal' => true,
        'inputTokens' => 10,
        'outputTokens' => 3,
        'totalTokens' => 13,
    ]);

    expect(findLifecycleEvent($captured, InferenceCompleted::class)->data)->toMatchArray([
        'isSuccess' => true,
        'finishReason' => 'stop',
        'attemptCount' => 1,
        'inputTokens' => 10,
        'outputTokens' => 3,
        'totalTokens' => 13,
    ]);
});

it('preserves execution telemetry parentage across started and completed events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $request = new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-parentage',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        telemetryCorrelation: OperationCorrelation::child(
            rootOperationId: 'root-operation',
            parentOperationId: 'upstream-step',
            sessionId: 'session-1',
            userId: 'user-1',
            conversationId: 'conversation-1',
        ),
    );

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest($request),
        driver: new FakeInferenceDriver(
            responses: [
                new InferenceResponse(
                    content: 'OK',
                    finishReason: 'stop',
                    usage: new InferenceUsage(inputTokens: 10, outputTokens: 3),
                ),
            ],
        ),
        eventDispatcher: $events,
    );

    $pending->response();

    $started = findLifecycleEvent($captured, InferenceStarted::class)->data[TelemetryEnvelope::KEY];
    $attemptStarted = findLifecycleEvent($captured, InferenceAttemptStarted::class)->data[TelemetryEnvelope::KEY];
    $completed = findLifecycleEvent($captured, InferenceCompleted::class)->data[TelemetryEnvelope::KEY];
    $executionId = findLifecycleEvent($captured, InferenceStarted::class)->data['executionId'];

    expect($started['correlation']['root_operation_id'])->toBe('root-operation');
    expect($started['correlation']['parent_operation_id'])->toBe('upstream-step');
    expect($completed['correlation']['root_operation_id'])->toBe('root-operation');
    expect($completed['correlation']['parent_operation_id'])->toBe('upstream-step');
    expect($attemptStarted['correlation']['root_operation_id'])->toBe('root-operation');
    expect($attemptStarted['correlation']['parent_operation_id'])->toBe($executionId);
});

it('emits minimal array payloads for failed lifecycle events', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $pending = new PendingInference(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-failure',
            options: ['stream' => true],
            retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        )),
        driver: new FakeInferenceDriver(
            onStream: function (): iterable {
                yield new PartialInferenceDelta(
                    contentDelta: 'part',
                    usage: new InferenceUsage(inputTokens: 4, outputTokens: 2),
                    usageIsCumulative: true,
                );
                throw new TimeoutException('stream lost');
            },
        ),
        eventDispatcher: $events,
    );

    try {
        $pending->response();
    } catch (TimeoutException) {
    }

    expect(findLifecycleEvent($captured, InferenceAttemptFailed::class)->data)->toMatchArray([
        'willRetry' => false,
        'partialInputTokens' => 4,
        'partialOutputTokens' => 2,
        'partialTotalTokens' => 6,
    ]);

    expect(findLifecycleEvent($captured, InferenceCompleted::class)->data)->toMatchArray([
        'isSuccess' => false,
        'finishReason' => 'error',
        'attemptCount' => 1,
        'inputTokens' => 4,
        'outputTokens' => 2,
        'totalTokens' => 6,
    ]);
});

function findLifecycleEvent(array $events, string $class): object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }

    throw new RuntimeException("Missing event: {$class}");
}
