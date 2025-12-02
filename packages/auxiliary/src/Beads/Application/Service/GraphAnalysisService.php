<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Service;

use Cognesy\Auxiliary\Beads\Domain\Repository\GraphRepositoryInterface;

/**
 * Graph Analysis Service
 *
 * Application service for task graph analysis using bv.
 */
final readonly class GraphAnalysisService
{
    public function __construct(
        private GraphRepositoryInterface $graphRepository,
    ) {}

    /**
     * Get graph insights (PageRank, betweenness, critical path, etc.)
     *
     * @return array<mixed>
     */
    public function getInsights(): array
    {
        return $this->graphRepository->getInsights();
    }

    /**
     * Get execution plan with parallel tracks
     *
     * @return array<mixed>
     */
    public function getExecutionPlan(): array
    {
        return $this->graphRepository->getExecutionPlan();
    }

    /**
     * Get priority recommendations
     *
     * @return array<mixed>
     */
    public function getPriorityRecommendations(): array
    {
        return $this->graphRepository->getPriorityRecommendations();
    }

    /**
     * Get all blocked tasks
     *
     * @return array<mixed>
     */
    public function getBlockedTasks(): array
    {
        $tasks = $this->graphRepository->getAllBlocked();

        // Convert TaskCollection to array of task data
        return array_map(
            fn ($task) => [
                'id' => $task->id()->value,
                'title' => $task->title(),
                'type' => $task->type()->value,
                'status' => $task->status()->value,
                'priority' => $task->priority()->value,
            ],
            $tasks->toArray()
        );
    }

    /**
     * Get top N high-impact tasks from insights
     *
     * @return array<string>
     */
    public function getHighImpactTasks(int $limit = 5): array
    {
        $insights = $this->getInsights();

        if (! isset($insights['top_pagerank']) || ! is_array($insights['top_pagerank'])) {
            return [];
        }

        $taskIds = [];
        foreach ($insights['top_pagerank'] as $item) {
            if (isset($item['id']) && is_string($item['id'])) {
                $taskIds[] = $item['id'];
                if (count($taskIds) >= $limit) {
                    break;
                }
            }
        }

        return $taskIds;
    }
}
