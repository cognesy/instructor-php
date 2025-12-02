<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Repository;

use Cognesy\Auxiliary\Beads\Domain\Collection\CommentCollection;
use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Exception\TaskNotFoundException;
use Cognesy\Auxiliary\Beads\Domain\Model\Task;
use Cognesy\Auxiliary\Beads\Domain\Model\TaskStatus;
use Cognesy\Auxiliary\Beads\Domain\Repository\TaskRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\CommentParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;

/**
 * Bd Task Repository
 *
 * Concrete implementation of TaskRepositoryInterface using BdClient.
 * Maps between domain entities and bd CLI format.
 */
final readonly class BdTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private BdClient $client,
        private TaskParser $taskParser,
        private CommentParser $commentParser,
    ) {}

    #[\Override]
    public function findById(TaskId $id): ?Task
    {
        try {
            $data = $this->client->show($id->value);

            if (empty($data)) {
                return null;
            }

            // bd show returns array of one task
            $taskData = is_array($data[0] ?? null) ? $data[0] : $data;

            return $this->taskParser->parse($taskData);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<TaskId>  $ids
     */
    #[\Override]
    public function findMany(array $ids): TaskCollection
    {
        $tasks = [];

        foreach ($ids as $id) {
            $task = $this->findById($id);
            if ($task !== null) {
                $tasks[] = $task;
            }
        }

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function findByStatus(TaskStatus $status): TaskCollection
    {
        $data = $this->client->list(['status' => $status->value]);
        $tasks = $this->taskParser->parseMany($data);

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function findByAssignee(Agent $agent): TaskCollection
    {
        $data = $this->client->list(['assignee' => $agent->id]);
        $tasks = $this->taskParser->parseMany($data);

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function findReady(int $limit = 10): TaskCollection
    {
        $data = $this->client->ready($limit);
        $tasks = $this->taskParser->parseMany($data);

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function findAll(): TaskCollection
    {
        $data = $this->client->list();
        $tasks = $this->taskParser->parseMany($data);

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function getComments(TaskId $taskId): CommentCollection
    {
        $data = $this->client->getComments($taskId->value);
        $comments = $this->commentParser->parseMany($data);

        return CommentCollection::from($comments);
    }

    #[\Override]
    public function save(Task $task): void
    {
        // NOTE: save() only handles updates
        // Use TaskFactory for creating new tasks via bd CLI

        $data = [];

        // Build update data
        $data['status'] = $task->status()->value;
        $data['priority'] = $task->priority()->value;

        $assignee = $task->assignee();
        $data['assignee'] = $assignee !== null ? $assignee->id : '';

        // Update via bd
        $this->client->update($task->id()->value, $data);
    }

    #[\Override]
    public function delete(TaskId $id): void
    {
        // bd doesn't have a delete command, so we close the task instead
        $task = $this->findById($id);

        if ($task === null) {
            throw TaskNotFoundException::forId($id);
        }

        if (! $task->isClosed()) {
            $this->client->close($id->value, 'Deleted via API');
        }
    }
}
