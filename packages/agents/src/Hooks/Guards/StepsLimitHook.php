<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Guards;

use Closure;
use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;

final readonly class StepsLimitHook implements HookInterface
{
    /** @var Closure(mixed): int */
    private Closure $stepCounter;

    /**
     * @param int $maxSteps Maximum allowed steps
     * @param callable(mixed): int $stepCounter Extracts completed step count from state
     */
    public function __construct(
        private int $maxSteps,
        callable $stepCounter,
    ) {
        $this->stepCounter = Closure::fromCallable($stepCounter);
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $currentSteps = ($this->stepCounter)($state);
        if ($currentSteps < $this->maxSteps) {
            return $context;
        }

        return $context->withState($state->withStopSignal($this->createSignal($currentSteps)));
    }

    private function createSignal(int $currentSteps): StopSignal
    {
        $reason = sprintf('Step limit reached: %d/%d', $currentSteps, $this->maxSteps);

        return new StopSignal(
            reason: StopReason::StepsLimitReached,
            message: $reason,
            context: [
                'currentSteps' => $currentSteps,
                'maxSteps' => $this->maxSteps,
            ],
            source: self::class,
        );
    }
}
