<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\DependencyDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\DependencyTypeEnum;
use RuntimeException;

class DepRemoveAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdClock $clock,
    ) {}

    public function __invoke(
        string $filePath,
        string $issueId,
        string $dependsOnId,
        ?DependencyTypeEnum $type = null,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);
        foreach ($issues as $idx => $issue) {
            if ($issue->id !== $issueId) {
                continue;
            }
            $deps = array_values(array_filter(
                $issue->dependencies ?? [],
                fn(DependencyDTO $dep) => !($dep->dependsOnId === $dependsOnId && ($type === null || $dep->type === $type))
            ));
            $issues[$idx] = $this->withDependencies($issue, $deps);
            $this->store->save($filePath, $issues);
            return $issues[$idx];
        }
        throw new RuntimeException("Issue not found: {$issueId}");
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
