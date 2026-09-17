<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Core;

use Closure;
use Cognesy\Logging\EventLog;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionAttemptId;
use Cognesy\Polyglot\Decision\Data\DecisionExecutionId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

final class DecisionExecutionSession
{
    private readonly DecisionExecutionId $executionId;

    private readonly DecisionRetryPolicy $retryPolicy;

    /** @var Closure():int */
    private readonly Closure $unixTimeReader;

    private readonly DecisionLifecycleEmitter $lifecycle;

    private ?DecisionAttemptId $attemptId = null;

    private int $attemptNumber = 0;

    private ?DecisionResponse $response = null;

    private ?Throwable $terminalError = null;

    /**
     * @param (callable():int)|null $unixTimeReader
     * @param (callable():int)|null $monotonicNanoReader
     */
    public function __construct(
        private readonly DecisionRequest $request,
        private readonly CanProcessDecisionRequest $driver,
        private readonly CanDelayRetries $retryDelay,
        ?callable $unixTimeReader = null,
        ?DecisionExecutionId $executionId = null,
        ?EventDispatcherInterface $events = null,
        string $driverName = '',
        ?callable $monotonicNanoReader = null,
    ) {
        $this->executionId = $executionId ?? DecisionExecutionId::generate();
        $this->retryPolicy = $request->retryPolicy() ?? new DecisionRetryPolicy;
        $this->unixTimeReader = $unixTimeReader === null
            ? static fn (): int => time()
            : Closure::fromCallable($unixTimeReader);
        $this->lifecycle = new DecisionLifecycleEmitter(
            events: $events ?? EventLog::root('polyglot.decision.execution'),
            request: $request,
            executionId: $this->executionId->toString(),
            driver: trim($driverName) === '' ? get_class($driver) : $driverName,
            monotonicNanoReader: $monotonicNanoReader,
        );
    }

    public function request(): DecisionRequest
    {
        return $this->request;
    }

    public function executionId(): string
    {
        return $this->executionId->toString();
    }

    public function attemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function attemptId(): ?string
    {
        return $this->attemptId?->toString();
    }

    public function response(): DecisionResponse
    {
        if ($this->terminalError !== null) {
            throw $this->terminalError;
        }
        if ($this->response !== null) {
            return $this->response;
        }

        return $this->execute();
    }

    private function execute(): DecisionResponse
    {
        $this->lifecycle->executionStarted();
        while (true) {
            $this->attemptId = $this->lifecycle->beginAttempt();
            $this->attemptNumber = $this->lifecycle->attemptNumber();
            $attemptRequest = $this->request->withTelemetryCorrelation(
                $this->lifecycle->correlationForAttempt($this->attemptId),
            );

            try {
                $this->response = $this->driver->handle($attemptRequest);
                $this->lifecycle->attemptSucceeded($this->attemptId, $this->response);
                $this->lifecycle->executionCompleted($this->response);

                return $this->response;
            } catch (Throwable $error) {
                $willRetry = $this->retryPolicy->shouldRetry($error, $this->attemptNumber);
                $this->lifecycle->attemptFailed($this->attemptId, $error, $willRetry);
                if (! $willRetry) {
                    $this->terminalError = $error;
                    $this->lifecycle->executionFailed($error);
                    throw $error;
                }
                $delay = $this->retryPolicy->delayMsForAttempt(
                    $error,
                    $this->attemptNumber,
                    ($this->unixTimeReader)(),
                );
                if ($delay > 0) {
                    $this->retryDelay->delay($delay);
                }
            }
        }
    }
}
