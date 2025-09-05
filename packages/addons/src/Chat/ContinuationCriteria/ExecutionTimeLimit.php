<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinue;
use Cognesy\Addons\Chat\Data\ChatState;

final class ExecutionTimeLimit implements CanDecideToContinue
{
    public function __construct(private readonly int $maxSeconds) {}

    public function canContinue(ChatState $state): bool {
        $elapsed = time() - $state->startedAt()->getTimestamp();
        return $elapsed < $this->maxSeconds;
    }
}

