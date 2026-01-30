<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Lifecycle;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Core\Data\ToolExecution;
use Cognesy\Agents\Core\Exceptions\AgentException;
use Cognesy\Polyglot\Inference\Data\ToolCall;

/**
 * Chains multiple lifecycle observers together.
 *
 * Observers are called in order. For blocking decisions (toolUsing),
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
    public function onBeforeExecution(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onBeforeExecution($state);
        }
        return $state;
    }

    #[\Override]
    public function onAfterExecution(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onAfterExecution($state);
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
    public function onBeforeStep(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onBeforeStep($state);
        }
        return $state;
    }

    #[\Override]
    public function onAfterStep(AgentState $state): AgentState
    {
        foreach ($this->observers as $observer) {
            $state = $observer->onAfterStep($state);
        }
        return $state;
    }

    #[\Override]
    public function onBeforeToolUse(ToolCall $toolCall, AgentState $state): ToolUseDecision
    {
        foreach ($this->observers as $observer) {
            $decision = $observer->onBeforeToolUse($toolCall, $state);
            if ($decision->isBlocked()) {
                return $decision;
            }
            $toolCall = $decision->toolCall();
        }
        return ToolUseDecision::proceed($toolCall);
    }

    #[\Override]
    public function onAfterToolUse(ToolExecution $execution, AgentState $state): ToolExecution
    {
        foreach ($this->observers as $observer) {
            $execution = $observer->onAfterToolUse($execution, $state);
        }
        return $execution;
    }

}
