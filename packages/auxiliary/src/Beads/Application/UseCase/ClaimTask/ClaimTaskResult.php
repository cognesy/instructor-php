<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\ClaimTask;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;

/**
 * Claim Task Result
 *
 * Result of claiming a task.
 */
final readonly class ClaimTaskResult
{
    public function __construct(
        public bool $success,
        public ?Task $task = null,
        public ?string $error = null,
    ) {}

    public static function success(Task $task): self
    {
        return new self(true, $task, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, $error);
    }
}
