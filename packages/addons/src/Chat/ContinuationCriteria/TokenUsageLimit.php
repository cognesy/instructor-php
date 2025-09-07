<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinueChat;
use Cognesy\Addons\Chat\Data\ChatState;

final class TokenUsageLimit implements CanDecideToContinueChat
{
    public function __construct(private readonly int $maxTokens) {}

    public function canContinue(ChatState $state): bool {
        return $state->usage()->total() < $this->maxTokens;
    }
}

