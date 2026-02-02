<?php declare(strict_types=1);

namespace Cognesy\Agents\Hooks\Guards;

use Cognesy\Agents\Core\Stop\StopReason;
use Cognesy\Agents\Core\Stop\StopSignal;
use Cognesy\Agents\Hooks\HookContext;
use Cognesy\Agents\Hooks\HookInterface;

final readonly class TokenUsageLimitHook implements HookInterface
{
    public function __construct(
        private int $maxTotalTokens,
    ) {}

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        $totalTokens = $state->usage()->total();
        if ($totalTokens < $this->maxTotalTokens) {
            return $context;
        }

        return $context->withState($state->withStopSignal($this->createSignal($totalTokens)));
    }

    private function createSignal(int $totalTokens): StopSignal
    {
        $reason = sprintf('Token limit reached: %d/%d', $totalTokens, $this->maxTotalTokens);

        return new StopSignal(
            reason: StopReason::TokenLimitReached,
            message: $reason,
            context: [
                'totalTokens' => $totalTokens,
                'maxTotalTokens' => $this->maxTotalTokens,
            ],
            source: self::class,
        );
    }
}
