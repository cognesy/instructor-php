<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Repository;

use Cognesy\Auxiliary\Beads\Domain\Collection\TaskCollection;
use Cognesy\Auxiliary\Beads\Domain\Repository\GraphRepositoryInterface;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BdClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Client\BvClient;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\GraphParser;
use Cognesy\Auxiliary\Beads\Infrastructure\Parser\TaskParser;

/**
 * Bv Graph Repository
 *
 * Concrete implementation of GraphRepositoryInterface using BvClient.
 * Provides graph analysis and dependency insights.
 */
final readonly class BvGraphRepository implements GraphRepositoryInterface
{
    public function __construct(
        private BvClient $bvClient,
        private BdClient $bdClient,
        private GraphParser $graphParser,
        private TaskParser $taskParser,
    ) {}

    #[\Override]
    public function getBlockedBy(TaskId $taskId): TaskCollection
    {
        // Use bd dep command to get blockers
        // For now, return empty - this requires bd dep tree parsing
        return TaskCollection::empty();
    }

    #[\Override]
    public function getBlocking(TaskId $taskId): TaskCollection
    {
        // Use bd dep command to get blocking tasks
        // For now, return empty - this requires bd dep tree parsing
        return TaskCollection::empty();
    }

    #[\Override]
    public function getAllBlocked(): TaskCollection
    {
        $data = $this->bdClient->blocked();
        $tasks = $this->taskParser->parseMany($data);

        return TaskCollection::from($tasks);
    }

    #[\Override]
    public function getInsights(): array
    {
        $data = $this->bvClient->getInsights();

        return $this->graphParser->parseInsights($data);
    }

    #[\Override]
    public function getExecutionPlan(): array
    {
        $data = $this->bvClient->getExecutionPlan();

        return $this->graphParser->parseExecutionPlan($data);
    }

    #[\Override]
    public function getPriorityRecommendations(): array
    {
        $data = $this->bvClient->getPriorityRecommendations();

        return $this->graphParser->parsePriorityRecommendations($data);
    }

    #[\Override]
    public function getDependencyTree(TaskId $taskId): array
    {
        // This would require parsing bd dep tree output
        // For now, return minimal structure
        return [
            'task_id' => $taskId->value,
            'blockers' => [],
            'blocking' => [],
            'depth' => 0,
        ];
    }
}
