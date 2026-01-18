<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\State\Contracts\CanMarkExecutionStarted;
use Cognesy\Addons\StepByStep\StepByStep;
use Throwable;

final class HookState implements CanMarkExecutionStarted
{
    public function __construct(public bool $started) {}

    public static function fresh(): self
    {
        return new self(false);
    }

    #[\Override]
    public function markExecutionStarted(): static
    {
        return new self(true);
    }
}

final class NoopStepByStep extends StepByStep
{
    #[\Override]
    protected function canContinue(object $state): bool
    {
        return false;
    }

    #[\Override]
    protected function makeNextStep(object $state): object
    {
        return new \stdClass();
    }

    #[\Override]
    protected function applyStep(object $state, object $nextStep): object
    {
        return $state;
    }

    #[\Override]
    protected function onNoNextStep(object $state): object
    {
        return $state;
    }

    #[\Override]
    protected function onStepCompleted(object $state): object
    {
        return $state;
    }

    #[\Override]
    protected function onFailure(Throwable $error, object $state): object
    {
        return $state;
    }
}

it('marks execution start when supported by state', function () {
    $executor = new NoopStepByStep();
    $state = HookState::fresh();

    $finalState = $executor->finalStep($state);

    expect($finalState)->toBeInstanceOf(HookState::class);
    expect($finalState->started)->toBeTrue();
});
