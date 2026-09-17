<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpRequestTelemetry;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Drivers\TypeSafe\TypesafeRequestAdapter;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptFailed;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptStarted;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptSucceeded;
use Cognesy\Polyglot\Decision\Events\DecisionCompleted;
use Cognesy\Polyglot\Decision\Events\DecisionEvent;
use Cognesy\Polyglot\Decision\Events\DecisionFailed;
use Cognesy\Polyglot\Decision\Events\DecisionStarted;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionTransientException;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Domain\Observation\ObservationStatus;

it('emits correlated metadata-only lifecycle events once across a retry', function () {
    $secretState = 'secret-state-sentinel';
    $secretInstruction = 'secret-instruction-sentinel';
    $secretCriteria = 'secret-criteria-sentinel';
    $secretError = 'secret-error-sentinel';
    $events = new EventDispatcher('decision.lifecycle.events.test');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        if ($event instanceof DecisionEvent) {
            $captured[] = $event;
        }
    });
    $driver = new DecisionEventFakeDriver(
        new DecisionTransientException($secretError, 529),
        decisionEventResponse(),
    );
    $clock = 0;
    $runtime = new DecisionRuntime(
        driver: $driver,
        events: $events,
        defaultModel: 'jev-test',
        retryDelay: new DecisionEventNoDelay,
        driverName: 'typesafe',
        monotonicNanoReader: static function () use (&$clock): int {
            $clock += 1_000_000;

            return $clock;
        },
    );
    $request = new DecisionRequest(
        input: $secretState,
        questions: Questions::of(new Noul(
            id: 'safe',
            instructions: $secretInstruction,
            criteria: new NoulCriteria(true: $secretCriteria),
        )),
        retryPolicy: new DecisionRetryPolicy(maxAttempts: 2, baseDelayMs: 0, jitter: 'none'),
    );
    $pending = $runtime->create($request);

    $response = $pending->response();
    expect($pending->response())->toBe($response);

    expect(array_map(static fn (object $event): string => $event::class, $captured))->toBe([
        DecisionStarted::class,
        DecisionAttemptStarted::class,
        DecisionAttemptFailed::class,
        DecisionAttemptStarted::class,
        DecisionAttemptSucceeded::class,
        DecisionCompleted::class,
    ]);

    /** @var DecisionStarted $started */
    $started = $captured[0];
    /** @var DecisionAttemptStarted $firstAttempt */
    $firstAttempt = $captured[1];
    /** @var DecisionAttemptFailed $failedAttempt */
    $failedAttempt = $captured[2];
    /** @var DecisionAttemptStarted $secondAttempt */
    $secondAttempt = $captured[3];
    /** @var DecisionCompleted $completed */
    $completed = $captured[5];
    expect($started->executionId)->toBe($pending->executionId())
        ->and($started->requestId)->toBe($request->id()->toString())
        ->and($started->model)->toBe('jev-test')
        ->and($started->driver)->toBe('typesafe')
        ->and($started->primitiveCount)->toBe(1)
        ->and($firstAttempt->attemptNumber)->toBe(1)
        ->and($firstAttempt->isRetry())->toBeFalse()
        ->and($secondAttempt->attemptNumber)->toBe(2)
        ->and($secondAttempt->isRetry())->toBeTrue()
        ->and($secondAttempt->attemptId)->not->toBe($firstAttempt->attemptId)
        ->and($failedAttempt->willRetry)->toBeTrue()
        ->and($failedAttempt->httpStatusCode)->toBe(529)
        ->and($completed->attemptCount)->toBe(2);

    expect($driver->requests)->toHaveCount(2);
    foreach ($driver->requests as $index => $attemptRequest) {
        $attemptEvent = $captured[$index === 0 ? 1 : 3];
        expect($attemptRequest->telemetryCorrelation()?->parentOperationId())
            ->toBe($attemptEvent->attemptId)
            ->and($attemptRequest->telemetryCorrelation()?->rootOperationId())
            ->toBe($pending->executionId());
    }

    $adapter = new TypesafeRequestAdapter(new DecisionConfig(
        driver: 'typesafe',
        apiUrl: 'https://api.typesafe.test',
        apiKey: 'secret-api-key-sentinel',
        endpoint: '/v1/systemone',
        model: 'jev-test',
    ));
    $httpEnvelope = HttpRequestTelemetry::requestEnvelope(
        $adapter->toHttpClientRequest($driver->requests[1]),
    );
    expect($httpEnvelope->correlation()->parentOperationId())->toBe($secondAttempt->attemptId)
        ->and($httpEnvelope->correlation()->rootOperationId())->toBe($pending->executionId());

    $serialized = json_encode(array_map(
        static fn (DecisionEvent $event): mixed => $event->data,
        $captured,
    ), JSON_THROW_ON_ERROR);
    expect($serialized)
        ->not->toContain($secretState)
        ->not->toContain($secretInstruction)
        ->not->toContain($secretCriteria)
        ->not->toContain($secretError)
        ->not->toContain('secret-api-key-sentinel');
});

it('emits one balanced terminal failure sequence despite repeated pending consumption', function () {
    $events = new EventDispatcher('decision.lifecycle.failure.test');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        if ($event instanceof DecisionEvent) {
            $captured[] = $event;
        }
    });
    $error = new DecisionInvalidRequestException('provider-body-sentinel', 422);
    $driver = new DecisionEventFakeDriver($error);
    $pending = (new DecisionRuntime(
        driver: $driver,
        events: $events,
        defaultModel: 'jev-test',
        retryDelay: new DecisionEventNoDelay,
    ))->create(decisionEventRequest());

    expect(fn () => $pending->response())->toThrow($error::class)
        ->and(fn () => $pending->get())->toThrow($error::class)
        ->and(array_map(static fn (object $event): string => $event::class, $captured))->toBe([
            DecisionStarted::class,
            DecisionAttemptStarted::class,
            DecisionAttemptFailed::class,
            DecisionFailed::class,
        ]);

    /** @var DecisionAttemptFailed $attemptFailed */
    $attemptFailed = $captured[2];
    /** @var DecisionFailed $failed */
    $failed = $captured[3];
    expect($attemptFailed->willRetry)->toBeFalse()
        ->and($attemptFailed->httpStatusCode)->toBe(422)
        ->and($failed->attemptCount)->toBe(1)
        ->and((string) $attemptFailed)->not->toContain('provider-body-sentinel')
        ->and((string) $failed)->not->toContain('provider-body-sentinel');
});

it('projects retry and terminal failure lifecycles as balanced parent-child spans', function () {
    $otel = new OtelExporter;
    $telemetry = new Telemetry(new TraceRegistry, new CompositeTelemetryExporter([$otel]));
    $events = new EventDispatcher('decision.telemetry.retry.test');
    (new RuntimeEventBridge(new PolyglotTelemetryProjector($telemetry)))->attachTo($events);
    $retryDriver = new DecisionEventFakeDriver(
        new DecisionTransientException('do-not-export-this-message', 529),
        decisionEventResponse(),
    );
    $pending = (new DecisionRuntime(
        driver: $retryDriver,
        events: $events,
        defaultModel: 'jev-test',
        retryDelay: new DecisionEventNoDelay,
        driverName: 'typesafe',
    ))->create(decisionEventRequest(new DecisionRetryPolicy(maxAttempts: 2, baseDelayMs: 0, jitter: 'none')));
    $pending->response();

    $observations = $otel->observations();
    expect($observations)->toHaveCount(3)
        ->and(array_map(static fn ($observation): string => $observation->name(), $observations))->toBe([
            'sdm.decision.attempt',
            'sdm.decision.attempt',
            'sdm.decision',
        ]);
    $execution = $observations[2];
    expect($observations[0]->status())->toBe(ObservationStatus::Error)
        ->and($observations[1]->status())->toBe(ObservationStatus::Ok)
        ->and($execution->status())->toBe(ObservationStatus::Ok)
        ->and($observations[0]->spanReference()->parentSpanId()?->value())
        ->toBe($execution->spanReference()->spanId()->value())
        ->and($observations[1]->spanReference()->parentSpanId()?->value())
        ->toBe($execution->spanReference()->spanId()->value())
        ->and(json_encode($otel->tracesPayload(), JSON_THROW_ON_ERROR))
        ->not->toContain('do-not-export-this-message');

    $failureOtel = new OtelExporter;
    $failureTelemetry = new Telemetry(
        new TraceRegistry,
        new CompositeTelemetryExporter([$failureOtel]),
    );
    $failureEvents = new EventDispatcher('decision.telemetry.failure.test');
    (new RuntimeEventBridge(new PolyglotTelemetryProjector($failureTelemetry)))->attachTo($failureEvents);
    $failure = (new DecisionRuntime(
        driver: new DecisionEventFakeDriver(new DecisionInvalidRequestException('hidden', 422)),
        events: $failureEvents,
        defaultModel: 'jev-test',
        retryDelay: new DecisionEventNoDelay,
    ))->create(decisionEventRequest());
    expect(fn () => $failure->response())->toThrow(DecisionInvalidRequestException::class);

    expect($failureOtel->observations())->toHaveCount(2)
        ->and($failureOtel->observations()[0]->status())->toBe(ObservationStatus::Error)
        ->and($failureOtel->observations()[1]->status())->toBe(ObservationStatus::Error);
});

final class DecisionEventFakeDriver implements CanProcessDecisionRequest
{
    /** @var list<DecisionRequest> */
    public array $requests = [];

    /** @var non-empty-list<DecisionResponse|Throwable> */
    private array $outcomes;

    private DecisionResponse|Throwable $last;

    public function __construct(DecisionResponse|Throwable ...$outcomes)
    {
        if ($outcomes === []) {
            throw new InvalidArgumentException('Fake Decision driver requires an outcome.');
        }
        $this->outcomes = array_values($outcomes);
        $this->last = $this->outcomes[array_key_last($this->outcomes)];
    }

    #[Override]
    public function handle(DecisionRequest $request): DecisionResponse
    {
        $this->requests[] = $request;
        $outcome = array_shift($this->outcomes) ?? $this->last;
        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return $outcome;
    }
}

final class DecisionEventNoDelay implements CanDelayRetries
{
    #[Override]
    public function delay(int $milliseconds): void {}
}

function decisionEventRequest(?DecisionRetryPolicy $retryPolicy = null): DecisionRequest
{
    return new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Noul('safe')),
        retryPolicy: $retryPolicy,
    );
}

function decisionEventResponse(): DecisionResponse
{
    return new DecisionResponse(
        answers: Answers::of(new NoulAnswer('safe', 0.8)),
        model: 'jev-result',
        usage: new DecisionUsage(inputTokens: 4, outputTokens: 2),
    );
}
