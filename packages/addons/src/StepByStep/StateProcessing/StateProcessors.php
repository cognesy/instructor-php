<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing;

use Closure;

/**
 * Generic middleware chain for processing state objects.
 * 
 * @template TState of object
 */
readonly class StateProcessors implements CanApplyProcessors
{
    /** @var CanProcessAnyState[] */
    protected array $processors;
    private Closure $middlewareChain;

    public function __construct(
        CanProcessAnyState ...$processors,
    ) {
        $this->processors = $processors;
        $this->middlewareChain = $this->buildMiddlewareChain();
    }

    // CONSTRUCTORS /////////////////////////////////////////

    public static function empty(): static {
        return new static();
    }

    // API //////////////////////////////////////////////////

    /**
     * Apply all processors to the state using middleware chain pattern.
     *
     * @param TState $state
     * @return TState
     */
    public function apply(object $state): object {
        return ($this->middlewareChain)($state);
    }

    // INTERNALS ////////////////////////////////////////////

    private function buildMiddlewareChain(): Closure {
        $next = fn(object $state) => $state; // terminal no-op processor
        foreach ($this->reversed() as $processor) {
            $currentNext = $next;
            $next = function(object $state) use ($processor, $currentNext): object {
                if (!$processor->canProcess($state)) {
                    return $currentNext($state);
                }
                return $processor->process($state, $currentNext);
            };
        }
        return $next;
    }

    /** @return iterable<CanProcessAnyState> */
    private function reversed(): iterable {
        foreach (array_reverse($this->processors) as $processor) {
            yield $processor;
        }
    }
}