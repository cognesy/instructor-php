<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Factory;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;

/**
 * Task Factory
 *
 * Creates Task entities via bd CLI with proper ID assignment.
 * Handles the mismatch between domain entity IDs and bd-assigned IDs.
 */
final readonly class TaskFactory
{
    public function __construct(
        private BdClient $client,
        private TaskParser $parser,
    ) {}

    /**
     * Create new task via bd and return Task entity with bd-assigned ID
     *
     * @param  array<string>  $labels
     */
    public function create(
        string $title,
        TaskType $type,
        Priority $priority,
        ?string $description = null,
        array $labels = [],
    ): Task {
        // Create via bd CLI
        $bdData = $this->client->create([
            'title' => $title,
            'type' => $type->value,
            'priority' => $priority->value,
            'description' => $description,
        ]);

        // Parse response to get Task with bd-assigned ID
        // bd create returns array with single task
        $taskData = is_array($bdData[0] ?? null) ? $bdData[0] : $bdData;

        return $this->parser->parse($taskData);
    }

    /**
     * Create task with assignee
     */
    public function createWithAssignee(
        string $title,
        TaskType $type,
        Priority $priority,
        string $assigneeId,
        ?string $description = null,
    ): Task {
        $bdData = $this->client->create([
            'title' => $title,
            'type' => $type->value,
            'priority' => $priority->value,
            'assignee' => $assigneeId,
            'description' => $description,
        ]);

        $taskData = is_array($bdData[0] ?? null) ? $bdData[0] : $bdData;

        return $this->parser->parse($taskData);
    }
}
