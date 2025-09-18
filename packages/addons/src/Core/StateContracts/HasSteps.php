<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\StateContracts;

interface HasSteps
{
    public function currentStep(): ?object;
    public function stepCount(): int;
    public function stepAt(int $index): ?object;
    public function eachStep(): iterable;
    public function withStepAppended(object $step): static;
}
