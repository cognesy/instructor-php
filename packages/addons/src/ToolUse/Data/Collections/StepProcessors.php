<?php declare(strict_types=1);

namespace Cognesy\Addons\ToolUse\Data\Collections;

use Cognesy\Addons\ToolUse\Contracts\CanProcessToolState;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;

final readonly class StepProcessors
{
    /** @var CanProcessToolState[] */
    private array $processors;

    public function __construct(CanProcessToolState ...$processors) {
        $this->processors = $processors;
    }

    public function withProcessors(CanProcessToolState ...$processors) : self {
        return new self(...$processors);
    }

    public function isEmpty() : bool {
        return $this->processors === [];
    }

    public function apply(ToolUseState $state) : ToolUseState {
        $chain = $this->buildMiddlewareChain($state);
        return $chain ? $chain($state) : $state;
    }

    private function buildMiddlewareChain(ToolUseState $initialState): ?callable {
        if ($this->isEmpty()) {
            return null;
        }

        $next = fn(ToolUseState $state) => $state;
        
        foreach (array_reverse($this->processors) as $processor) {
            $currentNext = $next;
            $next = static fn(ToolUseState $state) => $processor->process($state, $currentNext);
        }
        
        return $next;
    }
}

