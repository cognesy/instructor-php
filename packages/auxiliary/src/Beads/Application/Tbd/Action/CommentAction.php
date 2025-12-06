<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\CommentDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use RuntimeException;

class CommentAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdClock $clock,
    ) {}

    public function __invoke(
        string $filePath,
        string $issueId,
        string $author,
        string $text,
        ?string $createdAt,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: false);
        foreach ($issues as $idx => $issue) {
            if ($issue->id !== $issueId) {
                continue;
            }
            $comments = $issue->comments ?? [];
            $nextId = $this->nextCommentId($comments);
            $comment = new CommentDTO(
                id: $nextId,
                issueId: $issueId,
                author: $author,
                text: $text,
                createdAt: $this->clock->parse($createdAt),
            );
            $comments[] = $comment;
            $issues[$idx] = new IssueDTO(
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
                dependencies: $issue->dependencies,
                comments: $comments,
            );
            $this->store->save($filePath, $issues);
            return $issues[$idx];
        }

        throw new RuntimeException("Issue not found: {$issueId}");
    }

    /**
     * @param CommentDTO[] $comments
     */
    private function nextCommentId(array $comments): int {
        $max = 0;
        foreach ($comments as $comment) {
            if ($comment->id > $max) {
                $max = $comment->id;
            }
        }
        return $max + 1;
    }
}
