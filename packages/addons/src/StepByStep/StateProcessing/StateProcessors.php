<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing;

use Closure;

/**
 * Generic middleware chain for processing state objects.
 * 
 * @template TState of object
 */
final readonly class StateProcessors implements CanApplyProcessors
{
    /** @var CanProcessAnyState[] */
    protected array $processors;

    public function __construct(CanProcessAnyState ...$processors) {
        $this->processors = $processors;
    }

    // CONSTRUCTORS /////////////////////////////////////////

    public static function empty(): static {
        return new static();
    }

    public function withProcessors(CanProcessAnyState ...$processors): static {
        return new static(...$processors);
    }

    // API //////////////////////////////////////////////////

    /**
     * Apply all processors to the state using middleware chain pattern.
     *
     * @param TState $state
     * @return TState
     */
    public function apply(object $state, ?callable $terminalFn = null): object {
        $middlewareChain = $this->buildMiddlewareChain($terminalFn);
        return ($middlewareChain)($state);
    }

    // INTERNALS ////////////////////////////////////////////

    private function buildMiddlewareChain(?callable $terminalFn = null): Closure {
        $next = match(true) {
            ($terminalFn !== null) => $terminalFn,
            default => fn(object $state): object => $state,
        };

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