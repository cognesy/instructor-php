<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;

/**
 * Claim Task Command
 *
 * Command to claim a task for an agent.
 */
final readonly class ClaimTaskCommand
{
    public function __construct(
        public TaskId $taskId,
        public Agent $agent,
    ) {}
}
