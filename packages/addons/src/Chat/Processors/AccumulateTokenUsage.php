<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Polyglot\Inference\Data\Usage;

final class AccumulateTokenUsage implements CanProcessAnyState
{
    public function process(object $state, ?callable $next = null): ChatState {
        $newState = $state->withAccumulatedUsage(
            $state->currentStep()?->usage() ?? Usage::none()
        );

        return $next ? $next($newState) : $newState;
    }

    public function canProcess(object $state): bool {
        return $state instanceof ChatState;
    }
}
