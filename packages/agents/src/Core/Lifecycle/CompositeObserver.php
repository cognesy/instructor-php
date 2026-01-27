<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Continuation\Enums\StopReason;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Chains multiple lifecycle observers together.
 *
 * Observers are called in order. For blocking decisions (toolUsing, stopping),
 * the first blocking decision wins. For state transformations, each observer
 * receives the output of the previous one.
 */
final class CompositeObserver implements CanObserveAgentLifecycle
{
    /** @var CanObserveAgentLifecycle[] */
    private array $observers;

    public function __construct(CanObserveAgentLifecycle ...$observers)
    {
        $this->observers = $observers;
    }

    public function with(CanObserveAgentLifecycle $observer): self
    {
        return new self(...[...$this->observers, $observer]);
    }

    #[\Override]
    public function executionStarting(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->executionStarting($state);
        }
        return $state;
    }

    #[\Override]
    public function executionEnding(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->executionEnding($state);
        }
        return $state;
    }

    #[\Override]
    public function executionFailed(AgentState $state, AgentException $exception): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->executionFailed($state, $exception);
        }
        return $state;
    }

    #[\Override]
    public function stepStarting(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->stepStarting($state);
        }
        return $state;
    }

    #[\Override]
    public function stepEnding(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->stepEnding($state);
        }
        return $state;
    }

    #[\Override]
    public function toolUsing(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        foreach ($this->observers as $observer) {
            $decision = $observer->toolUsing($toolCall, $state);
            if ($decision->isBlocked()) {
                return $decision;
            }
            $toolCall = $decision->toolCall();
        }
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function toolUsed(ToolExecution $execution, AgentState $state): ToolExecution
    {
        foreach ($this->observers as $observer) {
            $execution = $observer->toolUsed($execution, $state);
        }
        return $execution;
    }

    #[\Override]
    public function stopping(AgentState $state, StopReason $reason): StopDecision
    {
        foreach ($this->observers as $observer) {
            $decision = $observer->stopping($state, $reason);
            if ($decision->isPrevented()) {
                return $decision;
            }
        }
        return StopDecision::allow();
    }
}
