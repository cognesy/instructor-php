<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Aspects;

use Cognesy\Addons\Core\StateContracts\HasSteps;

/**
 * @template TStep of object
 */
class Steps implements HasSteps
{
    /** @var TStep[] $steps */
    private array $steps;

    public function __construct(
        array $steps = []
    ) {
        $this->steps = $steps;
    }

    /**
     * Get the current step, or null if there are no steps.
     *
     * @return ?TStep
     */
    public function currentStep(): ?object {
        if ($this->stepCount() === 0) {
            return null;
        }
        $lastIndex = $this->stepCount() - 1;
        return $this->steps[$lastIndex] ?? null;
    }

    /**
     * Get the step at the given index, or null if out of bounds.
     *
     * @param int $index
     * @return ?TStep
     */
    public function stepAt(int $index): ?object {
        return $this->steps[$index] ?? null;
    }

    public function stepCount(): int {
        return count($this->steps);
    }

    /**
     * Iterate over each step.
     *
     * @return iterable<TStep>
     */
    public function eachStep(): iterable {
        foreach ($this->steps as $step) {
            yield $step;
        }
    }

    /**
     * Return a new instance with the added step.
     *
     * @param TStep $step
     * @return static
     */
    public function withStepAppended(object $step): static {
        return new static([...$this->steps, $step]);
    }
}