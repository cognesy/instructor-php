<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Contracts;

use Cognesy\Agents\Agent\Continuation\ContinuationOutcome;
use Cognesy\Agents\Agent\Data\ToolExecution;
use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\ToolCall;
use DateTimeImmutable;

/**
 * Emits agent lifecycle events.
 */
interface CanEmitAgentEvents
{
    public function executionStarted(AgentState $state, int $availableTools): void;

    public function stepStarted(AgentState $state): void;

    public function stepCompleted(AgentState $state): void;

    public function stateUpdated(AgentState $state): void;

    public function continuationEvaluated(AgentState $state, ContinuationOutcome $outcome): void;

    public function executionFinished(AgentState $state): void;

    public function executionFailed(AgentState $state, AgentException $exception): void;

    public function toolCallStarted(ToolCall $toolCall, DateTimeImmutable $startedAt): void;

    public function toolCallCompleted(ToolExecution $execution): void;
}
