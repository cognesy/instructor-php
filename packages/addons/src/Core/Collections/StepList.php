<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Collections;

use Cognesy\Addons\Core\StateContracts\HasSteps;
use Cognesy\Utils\Collection\ArrayList;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @template TStep of object
 * @implements HasSteps<TStep>
 */
class StepList implements HasSteps, Countable, IteratorAggregate
{
    /** @var ArrayList<TStep> */
    private ArrayList $list;

    public function __construct(
        object ...$steps
    ) {
        $this->list = ArrayList::of(...$steps);
    }

    /** @param ArrayList<TStep> $list */
    private static function fromList(ArrayList $list): static {
        $instance = new static();
        $instance->list = $list;
        return $instance;
    }

    // ACCESSORS ///////////////////////////////////////////////////

    /** @return TStep[] */
    public function all(): array {
        return $this->list->all();
    }

    public function currentStep(): ?object {
        return $this->list->last();
    }

    public function isEmpty(): bool {
        return $this->list->isEmpty();
    }

    /** @return ?TStep */
    public function lastStep(): ?object {
        return $this->list->last();
    }

    /** @return ?TStep */
    public function stepAt(int $index): ?object {
        return $this->list->getOrNull($index);
    }

    /**
     * @deprecated Use count() instead
     */
    public function stepCount(): int {
        return $this->list->count();
    }

    public function count(): int {
        return $this->list->count();
    }

    // ITERATORS ///////////////////////////////////////////////////

    /** @return iterable<TStep> */
    public function eachStep(): iterable {
        return $this;
    }

    /** @return Traversable<int, TStep> */
    public function getIterator(): Traversable {
        return $this->list->getIterator();
    }

    // MUTATORS ////////////////////////////////////////////////////

    /**
     * @param TStep $step
     */
    public function withAddedStep(object $step): static {
        return static::fromList($this->list->withAdded($step));
    }

    /** @param TStep ...$step */
    public function withAddedSteps(object ...$step): static {
        return static::fromList($this->list->withAdded(...$step));
    }

    // TRANSFORMERS AND CONVERSIONS ////////////////////////////////

    /** @return array<TStep> */
    public function reversed(): array {
        return $this->list->reverse()->all();
    }
}