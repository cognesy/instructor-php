<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Collections;

use ArrayIterator;
use Cognesy\Addons\StepByStep\State\Contracts\HasSteps;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template TStep of object
 * @implements HasSteps<TStep>
 */
readonly class Steps implements HasSteps, Countable, IteratorAggregate
{
    /** @var TStep[] $steps */
    protected array $steps;

    public function __construct(
        object ...$steps
    ) {
        $this->steps = $steps;
    }

    // ACCESSORS ///////////////////////////////////////////////////

    /** @return TStep[] */
    public function all(): array {
        return $this->steps;
    }

    public function currentStep(): ?object {
        return $this->lastStep();
    }

    public function isEmpty(): bool {
        return $this->steps === [];
    }

    /** @return ?TStep */
    public function lastStep(): ?object {
        if ($this->count() === 0) {
            return null;
        }
        $lastIndex = $this->count() - 1;
        return $this->steps[$lastIndex] ?? null;
    }

    /** @return ?TStep */
    public function stepAt(int $index): ?object {
        return $this->steps[$index] ?? null;
    }

    /**
     * @deprecated Use count() instead
     */
    public function stepCount(): int {
        return $this->count();
    }

    // ITERATORS ///////////////////////////////////////////////////

    /** @return iterable<TStep> */
    public function eachStep(): iterable {
        foreach ($this->steps as $step) {
            yield $step;
        }
    }

    /** @return Traversable<int, TStep> */
    public function getIterator(): Traversable {
        return new ArrayIterator($this->steps);
    }

    public function count(): int {
        return count($this->steps);
    }

    // MUTATORS ////////////////////////////////////////////////////

    /**
     * @param TStep $step
     */
    public function withAddedStep(object $step): static {
        return $this->withAddedSteps($step);
    }

    /** @param TStep ...$step */
    public function withAddedSteps(object ...$step): static {
        return new static(...[...$this->steps, ...$step]);
    }

    // TRANSFORMERS AND CONVERSIONS ////////////////////////////////

    /** @return iterable<TStep> */
    public function reversed(): iterable {
        foreach (array_reverse($this->steps) as $step) {
            yield $step;
        }
    }
}
