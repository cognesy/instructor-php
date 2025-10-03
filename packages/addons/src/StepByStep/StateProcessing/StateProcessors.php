<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing;

use Closure;

/**
 * Generic middleware chain for processing state objects.
 *
 * @template TState of object
 * @implements CanApplyProcessors<TState>
 */
final readonly class StateProcessors implements CanApplyProcessors
{
    /** @var CanProcessAnyState<TState>[] */
    protected array $processors;

    /**
     * @param CanProcessAnyState<TState> ...$processors
     */
    public function __construct(CanProcessAnyState ...$processors) {
        $this->processors = $processors;
    }

    // CONSTRUCTORS /////////////////////////////////////////

    public static function empty(): static {
        return new static();
    }

    /**
     * @param CanProcessAnyState<TState> ...$processors
     */
    public function withProcessors(CanProcessAnyState ...$processors): static {
        return new static(...$processors);
    }

    // API //////////////////////////////////////////////////

    /**
     * Apply all processors to the state using middleware chain pattern.
     *
     * @param TState $state
     * @param (callable(TState): TState)|null $terminalFn
     * @return TState
     */
    #[\Override]
    public function apply(object $state, ?callable $terminalFn = null): object {
        $middlewareChain = $this->buildMiddlewareChain($terminalFn);
        return ($middlewareChain)($state);
    }

    // INTERNALS ////////////////////////////////////////////

    /**
     * @param (callable(TState): TState)|null $terminalFn
     * @return Closure(TState): TState
     */
    private function buildMiddlewareChain(?callable $terminalFn = null): Closure {
        /**
         * @param TState $state
         * @return TState
         */
        $identity = function(object $state): object {
            return $state;
        };

        /** @var callable(TState): TState $next */
        $next = $terminalFn ?? $identity;

        foreach ($this->reversed() as $processor) {
            $currentNext = $next;
            $next = function(object $state) use ($processor, $currentNext): object {
                if (!$processor->canProcess($state)) {
                    return $currentNext($state);
                }
                /**
                 * @var TState $state
                 * @psalm-suppress InvalidArgument - Processors work via canProcess() runtime check
                 */
                return $processor->process($state, $currentNext);
            };
        }
        /** @var Closure(TState): TState $next */
        return $next;
    }

    /** @return iterable<CanProcessAnyState> */
    private function reversed(): iterable {
        foreach (array_reverse($this->processors) as $processor) {
            yield $processor;
        }
    }
}