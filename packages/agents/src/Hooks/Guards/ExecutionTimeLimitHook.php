<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Guards;

use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Hooks\Contracts\HookInterface;
use Cognesy\Agents\Hooks\Data\HookContext;
use Cognesy\Agents\Hooks\Enums\HookTrigger;
use DateTimeImmutable;

final class ExecutionTimeLimitHook implements HookInterface
{
    private ?DateTimeImmutable $executionStartedAt = null;

    public function __construct(
        private readonly float $maxSeconds,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        return match ($context->triggerType()) {
            HookTrigger::BeforeExecution => $this->handleStart($context),
            HookTrigger::BeforeStep => $this->handleBeforeStep($context),
            default => $context,
        };
    }

    private function handleStart(HookContext $context): HookContext
    {
        $this->executionStartedAt = $context->createdAt();
        return $context;
    }

    private function handleBeforeStep(HookContext $context): HookContext
    {
        if ($this->executionStartedAt === null) {
            return $context;
        }

        $now = new DateTimeImmutable();
        $elapsedSeconds = (float) $now->format('U.u') - (float) $this->executionStartedAt->format('U.u');
        if ($elapsedSeconds < $this->maxSeconds) {
            return $context;
        }

        $state = $context->state()->withStopSignal($this->createSignal($elapsedSeconds));
        return $context->withState($state);
    }

    private function createSignal(float $elapsedSeconds): StopSignal
    {
        $reason = sprintf('Execution time limit reached: %.2fs/%.2fs', $elapsedSeconds, $this->maxSeconds);

        return new StopSignal(
            reason: StopReason::TimeLimitReached,
            message: $reason,
            context: [
                'elapsedSeconds' => $elapsedSeconds,
                'maxSeconds' => $this->maxSeconds,
            ],
            source: self::class,
        );
    }
}
