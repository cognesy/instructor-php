<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use RuntimeException;

class UpdateAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdClock $clock,
        private readonly TbdInputMapper $map,
    ) {}

    public function __invoke(
        string $filePath,
        string $id,
        ?string $title,
        ?string $description,
        ?string $status,
        ?string $priority,
        ?string $assignee,
        array $labels,
        ?string $acceptanceCriteria,
        ?string $notes,
        ?int $estimatedMinutes,
        ?string $closeReason,
        ?string $updatedAt,
        ?string $design,
        ?string $externalRef,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);

        $found = false;
        foreach ($issues as $idx => $issue) {
            if ($issue->id !== $id) {
                continue;
            }
            $found = true;
            $issues[$idx] = $this->applyUpdate(
                $issue,
                $title,
                $description,
                $status,
                $priority,
                $assignee,
                $labels,
                $acceptanceCriteria,
                $notes,
                $estimatedMinutes,
                $closeReason,
                $updatedAt,
                $design,
                $externalRef,
            );
            $this->store->save($filePath, $issues);
            return $issues[$idx];
        }

        if (!$found) {
            throw new RuntimeException("Issue not found: {$id}");
        }
        return $issues[0]; // unreachable, kept for type-safety
    }

    private function applyUpdate(
        IssueDTO $issue,
        ?string $title,
        ?string $description,
        ?string $status,
        ?string $priority,
        ?string $assignee,
        array $labels,
        ?string $acceptanceCriteria,
        ?string $notes,
        ?int $estimatedMinutes,
        ?string $closeReason,
        ?string $updatedAt,
        ?string $design,
        ?string $externalRef,
    ): IssueDTO {
        $newStatus = $status !== null ? $this->map->status($status) : $issue->status;
        $closedAt = $issue->closedAt;
        $closeReasonFinal = $issue->closeReason;
        if ($newStatus === \Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum::CLOSED) {
            $closedAt = $issue->closedAt ?? $this->clock->parse($updatedAt);
            $closeReasonFinal = $closeReason ?? $issue->closeReason ?? 'completed';
        }

        return new IssueDTO(
            id: $issue->id,
            title: $title ?? $issue->title,
            description: $description ?? $issue->description,
            status: $newStatus,
            priority: $priority !== null ? $this->map->priority($priority) : $issue->priority,
            issueType: $issue->issueType,
            createdAt: $issue->createdAt,
            updatedAt: $this->clock->parse($updatedAt),
            assignee: $assignee ?? $issue->assignee,
            design: $design ?? $issue->design,
            acceptanceCriteria: $acceptanceCriteria ?? $issue->acceptanceCriteria,
            notes: $notes ?? $issue->notes,
            estimatedMinutes: $estimatedMinutes ?? $issue->estimatedMinutes,
            closedAt: $closedAt,
            closeReason: $closeReasonFinal,
            externalRef: $externalRef ?? $issue->externalRef,
            labels: empty($labels) ? $issue->labels : $labels,
            compactionLevel: $issue->compactionLevel,
            compactedAt: $issue->compactedAt,
            compactedAtCommit: $issue->compactedAtCommit,
            originalSize: $issue->originalSize,
            dependencies: $issue->dependencies,
            comments: $issue->comments,
        );
    }
}
