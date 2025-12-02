<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;

/**
 * Create Epic Result
 *
 * Result of creating an epic with subtasks.
 */
final readonly class CreateEpicResult
{
    public function __construct(
        public bool $success,
        public ?Task $epic = null,
        public ?TaskCollection $subtasks = null,
        public ?string $error = null,
    ) {}

    public static function success(Task $epic, TaskCollection $subtasks): self
    {
        return new self(true, $epic, $subtasks, null);
    }

    public static function failure(string $error): self
    {
        return new self(false, null, null, $error);
    }
}
