<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Parser;

use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskType;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use InvalidArgumentException;

/**
 * Task Parser
 *
 * Converts bd JSON output to Task domain entities.
 */
final class TaskParser
{
    /**
     * Parse single task from bd JSON
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(array $data): Task
    {
        $this->validate($data);

        return Task::create(
            id: new TaskId((string) $data['id']),
            title: (string) $data['title'],
            type: TaskType::from((string) $data['issue_type']),
            priority: Priority::fromInt((int) $data['priority']),
            description: isset($data['description']) ? (string) $data['description'] : null,
            labels: isset($data['labels']) && is_array($data['labels']) ? $data['labels'] : [],
        );
    }

    /**
     * Parse multiple tasks from bd JSON
     *
     * @param  array<mixed>  $dataArray
     * @return array<Task>
     */
    public function parseMany(array $dataArray): array
    {
        $tasks = [];

        foreach ($dataArray as $data) {
            if (! is_array($data)) {
                continue;
            }

            $tasks[] = $this->parse($data);
        }

        return $tasks;
    }

    /**
     * Validate required fields
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $data): void
    {
        $required = ['id', 'title', 'issue_type', 'priority'];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}
