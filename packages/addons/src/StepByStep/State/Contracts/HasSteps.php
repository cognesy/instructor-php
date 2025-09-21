<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\State\Contracts;

/**
 * @template TStep of object
 */
interface HasSteps
{
    /** @return ?TStep */
    public function currentStep(): ?object;

    public function stepCount(): int;

    /** @return ?TStep */
    public function stepAt(int $index): ?object;

    /** @return iterable<TStep> */
    public function eachStep(): iterable;

    /**
     * @param TStep $step
     */
    public function withAddedStep(object $step): static;

    /** @param TStep ...$step */
    public function withAddedSteps(object ...$step): static;
}
