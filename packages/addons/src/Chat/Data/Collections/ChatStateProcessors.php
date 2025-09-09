<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data\Collections;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;

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

    public function apply(ChatState $state): ChatState {
        $chain = $this->buildMiddlewareChain($state);
        return $chain ? $chain($state) : $state;
    }

    private function buildMiddlewareChain(ChatState $initialState): ?callable {
        if ($this->isEmpty()) {
            return null;
        }
        $next = fn(ChatState $state) => $state;
        foreach ($this->reversed() as $processor) {
            $currentNext = $next;
            $next = static fn(ChatState $state) => $processor->process($state, $currentNext);
        }
        return $next;
    }

    /** @return CanProcessChatState[] */
    public function all(): array {
        return $this->processors;
    }

    public function each(): iterable {
        foreach ($this->processors as $processor) {
            yield $processor;
        }
    }

    public function reversed(): iterable {
        foreach (array_reverse($this->processors) as $processor) {
            yield $processor;
        }
    }
}
