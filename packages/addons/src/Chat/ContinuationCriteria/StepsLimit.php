<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinueChat;
use Cognesy\Addons\Chat\Data\ChatState;

final class StepsLimit implements CanDecideToContinueChat
{
    public function __construct(private readonly int $maxSteps) {}

    public function canContinue(ChatState $state): bool {
        return $state->stepCount() < $this->maxSteps;
    }
}

