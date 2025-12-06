<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\DependencyDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use RuntimeException;

class DepAddAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdClock $clock,
        private readonly TbdInputMapper $map,
    ) {}

    public function __invoke(
        string $filePath,
        string $issueId,
        string $dependsOnId,
        ?string $type,
        ?string $createdBy,
        ?string $createdAt,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);
        $found = false;
        foreach ($issues as $idx => $issue) {
            if ($issue->id !== $issueId) {
                continue;
            }
            $found = true;
            $deps = $issue->dependencies ?? [];
            if ($this->depExists($deps, $dependsOnId)) {
                return $issue;
            }
            $deps[] = new DependencyDTO(
                issueId: $issueId,
                dependsOnId: $dependsOnId,
                type: $this->map->dependencyType($type),
                createdAt: $this->clock->parse($createdAt),
                createdBy: $createdBy ?? 'tbd',
            );
            $issues[$idx] = $this->withDependencies($issue, $deps);
            $this->store->save($filePath, $issues);
            return $issues[$idx];
        }
        if (!$found) {
            throw new RuntimeException("Issue not found: {$issueId}");
        }
        return $issues[0]; // unreachable
    }

    /**
     * @param DependencyDTO[] $deps
     */
    private function depExists(array $deps, string $dependsOnId): bool {
        foreach ($deps as $dep) {
            if ($dep->dependsOnId === $dependsOnId) {
                return true;
            }
        }
        return false;
    }

    private function withDependencies(IssueDTO $issue, array $deps): IssueDTO {
        return new IssueDTO(
            id: $issue->id,
            title: $issue->title,
            description: $issue->description,
            status: $issue->status,
            priority: $issue->priority,
            issueType: $issue->issueType,
            createdAt: $issue->createdAt,
            updatedAt: $this->clock->now(),
            assignee: $issue->assignee,
            design: $issue->design,
            acceptanceCriteria: $issue->acceptanceCriteria,
            notes: $issue->notes,
            estimatedMinutes: $issue->estimatedMinutes,
            closedAt: $issue->closedAt,
            closeReason: $issue->closeReason,
            externalRef: $issue->externalRef,
            labels: $issue->labels,
            compactionLevel: $issue->compactionLevel,
            compactedAt: $issue->compactedAt,
            compactedAtCommit: $issue->compactedAtCommit,
            originalSize: $issue->originalSize,
            dependencies: $deps,
            comments: $issue->comments,
        );
    }
}
