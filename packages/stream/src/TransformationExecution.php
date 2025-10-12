<?php declare(strict_types=1);

namespace Cognesy\Stream;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Support\Reduced;
use Iterator;
use LogicException;

class TransformationExecution
{
    private Reducer $reducerStack;
    private Iterator $iterator;
    private mixed $accumulator;

    private bool $exhausted;  // No more source elements
    private bool $completed = false;  // Completion phase executed

    public function __construct(
        Reducer $reducerStack,
        Iterator $iterator,
        mixed $accumulator,
    ) {
        $this->reducerStack = $reducerStack;
        $this->iterator = $iterator;
        $this->accumulator = $accumulator;
        $this->exhausted = !$this->iterator->valid();
    }

    /**
     * Check if there are more reduction steps (not including completion)
     */
    public function hasNextStep(): bool {
        return !$this->exhausted;
    }

    /**
     * Execute one reduction step and return intermediate accumulator.
     * Does NOT execute completion phase.
     */
    public function step(): mixed {
        if ($this->exhausted) {
            throw new LogicException('No more reduction steps available.');
        }
        $this->accumulator = $this->tryProcessNext();
        return $this->accumulator;
    }

    /**
     * Execute all remaining steps and return completed result.
     */
    public function completed(): mixed {
        while (!$this->exhausted) { // Process remaining elements
            $this->accumulator = $this->tryProcessNext();
        }
        return $this->tryComplete(); // Execute completion phase
    }

    // INTERNAL /////////////////////////////////////////////////////

    /**
     * Execute completion phase and return final result.
     * Can only be called once.
     */
    private function tryComplete(): mixed {
        if ($this->completed) {
            throw new LogicException('Transduction already completed.');
        }
        $this->completed = true;
        return $this->reducerStack->complete($this->accumulator);
    }

    /**
     * Process the next source element and advance iterator.
     * Returns intermediate accumulator state.
     */
    private function tryProcessNext(): mixed {
        $newAccumulator = $this->reducerStack->step(
            $this->accumulator,
            $this->iterator->current(),
        );

        // Check for early termination before advancing
        return match(true) {
            $newAccumulator instanceof Reduced => $this->onReduced($newAccumulator),
            default => $this->onNext($newAccumulator),
        };
    }

    private function onReduced(mixed $accumulator) : mixed {
        $this->iterator->next();
        $this->exhausted = true;
        return $accumulator->value();
    }

    private function onNext(mixed $accumulator) : mixed {
        $this->iterator->next();
        $this->exhausted = !$this->iterator->valid();
        return $accumulator;
    }
}
