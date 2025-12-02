<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateTask;

use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Create Task Command
 *
 * Command to create a new task in the system.
 */
final readonly class CreateTaskCommand
{
    /**
     * @param  array<string>  $labels
     */
    public function __construct(
        public string $title,
        public TaskType $type,
        public Priority $priority,
        public ?string $description = null,
        public array $labels = [],
        public ?Agent $assignee = null,
    ) {}
}
