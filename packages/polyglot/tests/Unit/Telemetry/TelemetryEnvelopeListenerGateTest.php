<?php declare(strict_types=1);

use Cognesy\Events\Contracts\CanCheckListeners;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Config\InferenceRetryPolicy;
use Cognesy\Polyglot\Inference\Core\InferenceExecutionSession;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptFailed;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Inference\Exceptions\ProviderInvalidRequestException;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;
use Cognesy\Telemetry\Domain\Envelope\TelemetryEnvelope;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * InferenceExecutionSession must not build InferenceTelemetry envelopes when nothing
 * consumes the lifecycle event that would carry them. Four of the six build sites
 * serialise the entire conversation via Messages::toArray().
 *
 * WHY THE PROBE IS ON messages() AND NOT ON toArray(). Both Messages and Message are
 * `final readonly`, so toArray() cannot be intercepted by a test double. messages() is
 * the only route to it. On this test's path -- FakeInferenceDriver, one attempt, no
 * length recovery -- the telemetry envelope is the *sole* caller of messages(); the
 * remaining in-session use, `count($request->messages())`, sits inside the
 * InferenceStarted payload the guard is protecting. So a surviving probe proves the
 * stronger statement: the telemetry path touched the conversation zero times.
 *
 * Mutation-checked: removing any one of the six guards makes the first test fail with
 * the sentinel exception rather than a soft assertion.
 */

/** Reports no listeners for anything. */
final class NoListenerTelemetryDispatcher implements EventDispatcherInterface, CanCheckListeners
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $event): object {
        $this->dispatched[] = $event;
        return $event;
    }

    #[\Override]
    public function hasListenersFor(string $eventClass): bool {
        return false;
    }
}

/** Plain PSR-14 -- cannot report listeners, so must be assumed to listen. */
final class OpaqueTelemetryDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $dispatched = [];

    public function dispatch(object $event): object {
        $this->dispatched[] = $event;
        return $event;
    }
}

function telemetryGateSession(
    EventDispatcherInterface $events,
    InferenceRequest $request,
): InferenceExecutionSession {
    return new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest($request),
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
    );
}

function telemetryGateRequest(): InferenceRequest {
    return new InferenceRequest(
        messages: Messages::fromString('hello'),
        model: 'gpt-gate',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
    );
}

/** A request whose conversation cannot be read without raising the sentinel. */
function telemetryProbeRequest(): InferenceRequest {
    return new class(
        messages: Messages::fromString('hello'),
        model: 'gpt-gate',
        retryPolicy: new InferenceRetryPolicy(maxAttempts: 1),
    ) extends InferenceRequest {
        #[\Override]
        public function messages(): Messages {
            throw new RuntimeException('Telemetry must not read the conversation with no listeners.');
        }
    };
}

it('builds no telemetry envelope when no lifecycle event has listeners', function () {
    $events = new NoListenerTelemetryDispatcher();

    $response = telemetryGateSession($events, telemetryProbeRequest())->response();

    expect($response->content())->toBe('OK')
        ->and($events->dispatched)->toBeEmpty();
});

it('still emits every lifecycle event to a dispatcher that cannot report listeners', function () {
    $events = new OpaqueTelemetryDispatcher();

    telemetryGateSession($events, telemetryGateRequest())->response();

    $classes = array_map(static fn(object $e): string => $e::class, $events->dispatched);

    expect($classes)->toContain(InferenceStarted::class)
        ->and($classes)->toContain(InferenceAttemptStarted::class)
        ->and($classes)->toContain(InferenceAttemptSucceeded::class)
        ->and($classes)->toContain(InferenceUsageReported::class)
        ->and($classes)->toContain(InferenceCompleted::class);
});

it('carries the telemetry envelope when a listener is registered', function () {
    $events = new EventDispatcher();
    $seen = [];
    $events->addListener(InferenceStarted::class, function (InferenceStarted $e) use (&$seen): void {
        $seen[] = $e->data[TelemetryEnvelope::KEY] ?? null;
    });

    telemetryGateSession($events, telemetryGateRequest())->response();

    expect($seen)->toHaveCount(1)
        ->and($seen[0])->toBeArray()
        ->and($seen[0]['operation']['type'] ?? null)->toBe('llm.inference')
        // The conversation IS serialised here -- that is the point of the fail-open half
        // of the gate. (Per-message id/createdAt are not asserted; they vary per run.)
        ->and($seen[0]['io']['input'][0]['role'] ?? null)->toBe('user')
        ->and($seen[0]['io']['input'][0]['content'] ?? null)->toBe('hello');
});

it('gates the failure path, which also runs error redaction', function () {
    $events = new NoListenerTelemetryDispatcher();

    $session = new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest(telemetryGateRequest()),
        driver: new FakeInferenceDriver(
            onResponse: fn() => throw new ProviderInvalidRequestException('bad request', 400),
        ),
        events: $events,
    );

    expect(fn() => $session->response())->toThrow(ProviderInvalidRequestException::class);
    expect($events->dispatched)->toBeEmpty();
});

it('still emits the failure event when a listener wants it', function () {
    $events = new EventDispatcher();
    $failed = [];
    $events->addListener(InferenceAttemptFailed::class, function (InferenceAttemptFailed $e) use (&$failed): void {
        $failed[] = $e;
    });

    $session = new InferenceExecutionSession(
        execution: InferenceExecution::fromRequest(telemetryGateRequest()),
        driver: new FakeInferenceDriver(
            onResponse: fn() => throw new ProviderInvalidRequestException('bad request', 400),
        ),
        events: $events,
    );

    expect(fn() => $session->response())->toThrow(ProviderInvalidRequestException::class);

    expect($failed)->toHaveCount(1)
        ->and($failed[0]->data['errorMessage'] ?? null)->toBe('bad request')
        ->and($failed[0]->data[TelemetryEnvelope::KEY]['operation']['type'] ?? null)->toBe('llm.inference.attempt');
});

it('gates each lifecycle event independently', function () {
    // A dispatcher that admits to exactly one listener: InferenceCompleted.
    $events = new class implements EventDispatcherInterface, CanCheckListeners {
        /** @var list<object> */
        public array $dispatched = [];

        public function dispatch(object $event): object {
            $this->dispatched[] = $event;
            return $event;
        }

        #[\Override]
        public function hasListenersFor(string $eventClass): bool {
            return $eventClass === InferenceCompleted::class;
        }
    };

    telemetryGateSession($events, telemetryGateRequest())->response();

    $classes = array_map(static fn(object $e): string => $e::class, $events->dispatched);

    expect($classes)->toBe([InferenceCompleted::class]);
});
