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
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\PendingInference;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Http\Exceptions\TimeoutException;

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
