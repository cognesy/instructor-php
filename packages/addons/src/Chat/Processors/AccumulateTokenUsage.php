<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Polyglot\Inference\Data\Usage;

final class AccumulateTokenUsage implements CanProcessChatState
{
    public function process(ChatState $state, ?callable $next = null): ChatState {
        $newState = $state->accumulateUsage(
            $state->currentStep()?->usage() ?? Usage::none()
        );

        return $next ? $next($newState) : $newState;
    }
}
