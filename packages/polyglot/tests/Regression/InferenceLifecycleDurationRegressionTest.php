<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Core\InferenceExecutionSession;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

/**
 * Regression: lifecycle durations used DateInterval::s (the seconds component,
 * 0-59) and wrapped every 60 seconds, so a 61s call was reported as ~1000ms.
 * The monotonic stopwatch reports total elapsed ms and does not wrap.
 *
 * The injected clock steps 61 seconds on every read, so every measured interval
 * is at least one 61s step — proving no 60-second wrap — without sleeping.
 */
function steppingNanoClock(int $stepNs = 61_000_000_000): callable
{
    $calls = 0;
    return function () use (&$calls, $stepNs): int {
        return (++$calls) * $stepNs;
    };
}

/**
 * @param list<object> $events
 */
function findEvent(array $events, string $class): object
{
    foreach ($events as $event) {
        if ($event instanceof $class) {
            return $event;
        }
    }
    throw new RuntimeException("Missing event: {$class}");
}

it('reports non-wrapping monotonic durations for a successful attempt and completion', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $session = new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-duration',
            retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        )),
        driver: new FakeInferenceDriver(
            responses: [
                new InferenceResponse(
                    content: 'OK',
                    finishReason: 'stop',
                    usage: new InferenceUsage(inputTokens: 5, outputTokens: 2),
                ),
            ],
        ),
        events: $events,
        monotonicNanoReader: steppingNanoClock(),
    );

    $session->response();

    $attemptDuration = findEvent($captured, InferenceAttemptSucceeded::class)->data['durationMs'];
    $completedDuration = findEvent($captured, InferenceCompleted::class)->data['durationMs'];

    expect($attemptDuration)->toBeFloat()->toBeGreaterThanOrEqual(60_000.0)
        ->and($completedDuration)->toBeFloat()->toBeGreaterThanOrEqual(60_000.0);
});

it('reports non-wrapping monotonic durations for a failed attempt and completion', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    $session = new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-duration',
            retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        )),
        driver: new FakeInferenceDriver(
            onResponse: fn() => throw new ProviderInvalidRequestException('bad request', 400),
        ),
        events: $events,
        monotonicNanoReader: steppingNanoClock(),
    );

    expect(fn() => $session->response())->toThrow(ProviderInvalidRequestException::class);

    $failedDuration = findEvent($captured, InferenceAttemptFailed::class)->data['durationMs'];
    $completedDuration = findEvent($captured, InferenceCompleted::class)->data['durationMs'];

    expect($failedDuration)->toBeFloat()->toBeGreaterThanOrEqual(60_000.0)
        ->and($completedDuration)->toBeFloat()->toBeGreaterThanOrEqual(60_000.0);
});

it('reports a non-negative sub-wrap duration for a fast real-clock call', function () {
    $events = new EventDispatcher();
    $captured = [];
    $events->wiretap(function (object $event) use (&$captured): void {
        $captured[] = $event;
    });

    // No injected clock: uses real hrtime; a fast fake call must stay non-negative.
    $session = new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest(new InferenceRequest(
            messages: Messages::fromString('hello'),
            model: 'gpt-duration',
            retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
        )),
        driver: new FakeInferenceDriver(
            responses: [new InferenceResponse(content: 'OK', finishReason: 'stop')],
        ),
        events: $events,
    );

    $session->response();

    $completedDuration = findEvent($captured, InferenceCompleted::class)->data['durationMs'];
    expect($completedDuration)->toBeFloat()->toBeGreaterThanOrEqual(0.0);
});
