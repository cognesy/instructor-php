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
    public function beforeExecution(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->beforeExecution($state);
        }
        return $state;
    }

    #[\Override]
    public function afterExecution(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->afterExecution($state);
        }
        return $state;
    }

    #[\Override]
    public function onError(AgentState $state, AgentException $exception): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onError($state, $exception);
        }
        return $state;
    }

    #[\Override]
    public function beforeStep(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->beforeStep($state);
        }
        return $state;
    }

    #[\Override]
    public function afterStep(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->afterStep($state);
        }
        return $state;
    }

    #[\Override]
    public function beforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        foreach ($this->observers as $observer) {
            $decision = $observer->beforeToolUse($toolCall, $state);
            if ($decision->isBlocked()) {
                return $decision;
            }
            $toolCall = $decision->toolCall();
        }
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function afterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        foreach ($this->observers as $observer) {
            $execution = $observer->afterToolUse($execution, $state);
        }
        return $execution;
    }

    #[\Override]
    public function beforeStopDecision(AgentState $state, StopReason $reason): StopDecision
    {
        foreach ($this->observers as $observer) {
            $decision = $observer->beforeStopDecision($state, $reason);
            if ($decision->isPrevented()) {
                return $decision;
            }
        }
        return StopDecision::allow();
    }
}
