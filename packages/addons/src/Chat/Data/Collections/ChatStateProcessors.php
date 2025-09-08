<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;

final class ChatStateProcessors
{
    /** @var CanProcessChatState[] */
    private array $processors = [];

    public function __construct(CanProcessChatState ...$processors) {
        $this->processors = $processors;
    }

    public function add(CanProcessChatState ...$processors): self {
        foreach ($processors as $processor) {
            $this->processors[] = $processor;
        }
        return $this;
    }

    public function isEmpty(): bool {
        return $this->processors === [];
    }

    public function apply(ChatStep $step, ChatState $state): ChatState {
        $initialState = $state->withAddedStep($step)->withCurrentStep($step);
        $chain = $this->buildMiddlewareChain($initialState);
        return $chain ? $chain($initialState) : $initialState;
    }

    private function buildMiddlewareChain(ChatState $initialState): ?callable {
        if ($this->isEmpty()) {
            return null;
        }

        $next = fn(ChatState $state) => $state;
        
        foreach (array_reverse($this->processors) as $processor) {
            $currentNext = $next;
            $next = fn(ChatState $state) => $processor->process($state, $currentNext);
        }
        
        return $next;
    }

    /** @return CanProcessChatState[] */
    public function all(): array {
        return $this->processors;
    }
}
