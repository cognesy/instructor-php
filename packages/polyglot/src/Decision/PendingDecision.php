<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision;

use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Core\DecisionExecutionSession;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Support\Retry\CanDelayRetries;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PendingDecision
{
    private readonly DecisionExecutionSession $session;

    /**
     * @param (callable():int)|null $unixTimeReader
     * @param (callable():int)|null $monotonicNanoReader
     */
    public function __construct(
        DecisionRequest $request,
        CanProcessDecisionRequest $driver,
        CanDelayRetries $retryDelay,
        ?callable $unixTimeReader = null,
        ?EventDispatcherInterface $events = null,
        string $driverName = '',
        ?callable $monotonicNanoReader = null,
    ) {
        $this->session = new DecisionExecutionSession(
            request: $request,
            driver: $driver,
            retryDelay: $retryDelay,
            events: $events,
            driverName: $driverName,
            unixTimeReader: $unixTimeReader,
            monotonicNanoReader: $monotonicNanoReader,
        );
    }

    public function get(): Answers
    {
        return $this->response()->answers();
    }

    public function response(): DecisionResponse
    {
        return $this->session->response();
    }

    public function request(): DecisionRequest
    {
        return $this->session->request();
    }

    public function executionId(): string
    {
        return $this->session->executionId();
    }
}
