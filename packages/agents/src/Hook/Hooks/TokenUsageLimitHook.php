<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook\Hooks;

use Cognesy\Agents\Continuation\StopReason;
use Cognesy\Agents\Continuation\StopSignal;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;

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
