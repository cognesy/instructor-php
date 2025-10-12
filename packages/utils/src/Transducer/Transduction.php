<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Iterator;
use LogicException;

class Transduction
{
    private Reducer $reducerStack;
    private Iterator $iterator;
    private mixed $accumulator;

    private bool $readyForCompletion = false;
    private bool $completed = false;

    public function __construct(
        Reducer $reducerStack,
        Iterator $iterator,
        mixed $accumulator,
    ) {
        $this->reducerStack = $reducerStack;
        $this->iterator = $iterator;
        $this->accumulator = $accumulator;
    }

    public function hasNextStep(): bool {
        return !$this->completed && !$this->readyForCompletion;
    }

    public function step(): mixed {
        return match(true) {
            $this->completed => throw new LogicException('Transduction already completed.'),
            $this->readyForCompletion => $this->executeCompletion($this->reducerStack, $this->accumulator),
            default => $this->tryIterate($this->reducerStack, $this->iterator, $this->accumulator),
        };
    }

    public function final() : mixed {
        while ($this->hasNextStep()) {
            $accumulator = $this->step();
        }
        return $accumulator;
    }

    // INTERNAL /////////////////////////////////////////////////////

    private function tryIterate(Reducer $reducerStack, Iterator $iterator, mixed $accumulator) : mixed {
        $newAccumulator = $this->executeStep($reducerStack, $iterator, $accumulator);
        $this->readyForCompletion = $this->isReadyForCompletion($iterator, $newAccumulator);
        $this->accumulator = match(true) {
            $newAccumulator instanceof Reduced => $newAccumulator->value(),
            default => $newAccumulator,
        };
        $iterator->next();
        return $this->accumulator;
    }

    private function isReadyForCompletion(Iterator $iterator, mixed $accumulator): bool {
        return match(true) {
            !$iterator->valid() => true,
            $accumulator instanceof Reduced => true,
            default => false,
        };
    }

    private function executeStep(Reducer $reducerStack, Iterator $iterator, mixed $accumulator): mixed {
        return $reducerStack->step($accumulator, $iterator->current());
    }

    private function executeCompletion(Reducer $reducerStack, mixed $accumulator): mixed {
        $this->completed = true;
        return $reducerStack->complete($accumulator);
    }
}