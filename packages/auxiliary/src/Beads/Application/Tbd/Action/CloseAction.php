<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;
use RuntimeException;

class CloseAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdClock $clock,
    ) {}

    public function __invoke(
        string $filePath,
        string $id,
        ?string $reason,
        ?string $closedAt,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);

        foreach ($issues as $idx => $issue) {
            if ($issue->id !== $id) {
                continue;
            }
            $closedTime = $this->clock->parse($closedAt);
            $issues[$idx] = new IssueDTO(
                id: $issue->id,
                title: $issue->title,
                description: $issue->description,
                status: StatusEnum::CLOSED,
                priority: $issue->priority,
                issueType: $issue->issueType,
                createdAt: $issue->createdAt,
                updatedAt: $closedTime,
                assignee: $issue->assignee,
                design: $issue->design,
                acceptanceCriteria: $issue->acceptanceCriteria,
                notes: $issue->notes,
                estimatedMinutes: $issue->estimatedMinutes,
                closedAt: $closedTime,
                closeReason: $reason ?? $issue->closeReason ?? 'completed',
                externalRef: $issue->externalRef,
                labels: $issue->labels,
                compactionLevel: $issue->compactionLevel,
                compactedAt: $issue->compactedAt,
                compactedAtCommit: $issue->compactedAtCommit,
                originalSize: $issue->originalSize,
                dependencies: $issue->dependencies,
                comments: $issue->comments,
            );
            $this->store->save($filePath, $issues);
            return $issues[$idx];
        }

        throw new RuntimeException("Issue not found: {$id}");
    }
}
