<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Core;

use Cognesy\Events\Support\ListenerGate;
use Cognesy\Http\Exceptions\HttpRequestException;
use Cognesy\Polyglot\Decision\Data\DecisionAttemptId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptFailed;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptStarted;
use Cognesy\Polyglot\Decision\Events\DecisionAttemptSucceeded;
use Cognesy\Polyglot\Decision\Events\DecisionCompleted;
use Cognesy\Polyglot\Decision\Events\DecisionFailed;
use Cognesy\Polyglot\Decision\Events\DecisionStarted;
use Cognesy\Polyglot\Decision\Exceptions\DecisionProviderException;
use Cognesy\Polyglot\Decision\Telemetry\DecisionTelemetry;
use Cognesy\Polyglot\Inference\Core\MonotonicStopwatch;
use Cognesy\Telemetry\Domain\Envelope\OperationCorrelation;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class DecisionLifecycleEmitter
{
    private readonly MonotonicStopwatch $executionStopwatch;

    private readonly MonotonicStopwatch $attemptStopwatch;

    private readonly bool $emitStarted;

    private readonly bool $emitAttemptStarted;

    private readonly bool $emitAttemptSucceeded;

    private readonly bool $emitAttemptFailed;

    private readonly bool $emitCompleted;

    private readonly bool $emitFailed;

    private int $attemptNumber = 0;

    private bool $terminal = false;

    /** @param (callable():int)|null $monotonicNanoReader */
    public function __construct(
        private readonly EventDispatcherInterface $events,
        private readonly DecisionRequest $request,
        private readonly string $executionId,
        private readonly string $driver,
        ?callable $monotonicNanoReader = null,
    ) {
        $this->executionStopwatch = new MonotonicStopwatch($monotonicNanoReader);
        $this->attemptStopwatch = new MonotonicStopwatch($monotonicNanoReader);
        $wants = ListenerGate::wantsAny($events, [
            DecisionStarted::class,
            DecisionAttemptStarted::class,
            DecisionAttemptSucceeded::class,
            DecisionAttemptFailed::class,
            DecisionCompleted::class,
            DecisionFailed::class,
        ]);
        $this->emitStarted = $wants[DecisionStarted::class];
        $this->emitAttemptStarted = $wants[DecisionAttemptStarted::class];
        $this->emitAttemptSucceeded = $wants[DecisionAttemptSucceeded::class];
        $this->emitAttemptFailed = $wants[DecisionAttemptFailed::class];
        $this->emitCompleted = $wants[DecisionCompleted::class];
        $this->emitFailed = $wants[DecisionFailed::class];
    }

    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function executionStarted(): void
    {
        if ($this->executionStopwatch->isRunning()) {
            return;
        }
        $this->executionStopwatch->start();
        if (! $this->emitStarted) {
            return;
        }

        $this->events->dispatch(new DecisionStarted(
            executionId: $this->executionId,
            requestId: $this->request->id()->toString(),
            model: $this->model(),
            driver: $this->driver,
            primitiveCount: $this->request->questions()->count(),
            data: DecisionTelemetry::execution($this->request, $this->executionId),
        ));
    }

    public function beginAttempt(): DecisionAttemptId
    {
        $this->attemptNumber++;
        $attemptId = DecisionAttemptId::generate();
        $this->attemptStopwatch->start();
        if ($this->emitAttemptStarted) {
            $this->events->dispatch(new DecisionAttemptStarted(
                executionId: $this->executionId,
                requestId: $this->request->id()->toString(),
                attemptId: $attemptId->toString(),
                attemptNumber: $this->attemptNumber,
                model: $this->model(),
                driver: $this->driver,
                data: DecisionTelemetry::attempt($this->request, $this->executionId, $attemptId->toString()),
            ));
        }

        return $attemptId;
    }

    public function correlationForAttempt(DecisionAttemptId $attemptId): OperationCorrelation
    {
        $seed = $this->request->telemetryCorrelation();

        return OperationCorrelation::child(
            rootOperationId: $seed?->rootOperationId() ?? $this->executionId,
            parentOperationId: $attemptId->toString(),
            sessionId: $seed?->sessionId() ?? $this->request->id()->toString(),
            userId: $seed?->userId(),
            conversationId: $seed?->conversationId(),
            requestId: $this->request->id()->toString(),
        );
    }

    public function attemptSucceeded(DecisionAttemptId $attemptId, DecisionResponse $response): void
    {
        if (! $this->emitAttemptSucceeded) {
            return;
        }
        $this->events->dispatch(new DecisionAttemptSucceeded(
            executionId: $this->executionId,
            requestId: $this->request->id()->toString(),
            attemptId: $attemptId->toString(),
            attemptNumber: $this->attemptNumber,
            durationMs: $this->attemptStopwatch->elapsedMs(),
            usage: $response->usage(),
            data: DecisionTelemetry::attempt($this->request, $this->executionId, $attemptId->toString()),
        ));
    }

    public function attemptFailed(DecisionAttemptId $attemptId, Throwable $error, bool $willRetry): void
    {
        if (! $this->emitAttemptFailed) {
            return;
        }
        $this->events->dispatch(new DecisionAttemptFailed(
            executionId: $this->executionId,
            requestId: $this->request->id()->toString(),
            attemptId: $attemptId->toString(),
            attemptNumber: $this->attemptNumber,
            errorType: get_class($error),
            httpStatusCode: self::statusCodeOf($error),
            willRetry: $willRetry,
            durationMs: $this->attemptStopwatch->elapsedMs(),
            data: DecisionTelemetry::attempt($this->request, $this->executionId, $attemptId->toString()),
        ));
    }

    public function executionCompleted(DecisionResponse $response): void
    {
        if ($this->terminal) {
            return;
        }
        $this->terminal = true;
        if (! $this->emitCompleted) {
            return;
        }
        $this->events->dispatch(new DecisionCompleted(
            executionId: $this->executionId,
            requestId: $this->request->id()->toString(),
            model: $response->model(),
            driver: $this->driver,
            primitiveCount: $this->request->questions()->count(),
            attemptCount: $this->attemptNumber,
            durationMs: $this->executionStopwatch->elapsedMs(),
            usage: $response->usage(),
            data: DecisionTelemetry::execution($this->request, $this->executionId),
        ));
    }

    public function executionFailed(Throwable $error): void
    {
        if ($this->terminal) {
            return;
        }
        $this->terminal = true;
        if (! $this->emitFailed) {
            return;
        }
        $this->events->dispatch(new DecisionFailed(
            executionId: $this->executionId,
            requestId: $this->request->id()->toString(),
            model: $this->model(),
            driver: $this->driver,
            primitiveCount: $this->request->questions()->count(),
            attemptCount: $this->attemptNumber,
            errorType: get_class($error),
            httpStatusCode: self::statusCodeOf($error),
            durationMs: $this->executionStopwatch->elapsedMs(),
            data: DecisionTelemetry::execution($this->request, $this->executionId),
        ));
    }

    private function model(): string
    {
        return $this->request->model() ?? '';
    }

    private static function statusCodeOf(Throwable $error): ?int
    {
        return match (true) {
            $error instanceof DecisionProviderException => $error->statusCode,
            $error instanceof HttpRequestException => $error->getStatusCode(),
            default => null,
        };
    }
}
