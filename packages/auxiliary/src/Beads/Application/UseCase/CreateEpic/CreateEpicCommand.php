<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\UseCase\CreateEpic;

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

/**
 * Create Epic Command
 *
 * Command to create an epic (parent task) with subtasks.
 */
final readonly class CreateEpicCommand
{
    /**
     * @param  array<string>  $labels
     * @param  array<array{title: string, type: string, priority: int, description?: string}>  $subtasks
     */
    public function __construct(
        public string $title,
        public Priority $priority,
        public ?string $description = null,
        public array $labels = [],
        public ?Agent $assignee = null,
        public array $subtasks = [],
    ) {}
}
