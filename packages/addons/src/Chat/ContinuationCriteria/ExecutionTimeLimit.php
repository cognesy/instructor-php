<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\ContinuationCriteria;

use Cognesy\Addons\Chat\Contracts\CanDecideToContinueChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Utils\Time\ClockInterface;

final class ExecutionTimeLimit implements CanDecideToContinueChat
{
    public function __construct(
        private readonly int $maxSeconds,
        private ClockInterface $clock,
    ) {}

    public function canContinue(ChatState $state): bool {
        $now = $this->clock->now();
        $elapsed = $now->getTimestamp() - $state->startedAt()->getTimestamp();
        return $elapsed < $this->maxSeconds;
    }
}
