<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\IssueTypeEnum;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\PriorityEnum;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;
use DateTimeImmutable;

final readonly class IssueDTO
{
    /**
     * @param DependencyDTO[] $dependencies
     * @param CommentDTO[] $comments
     * @param string[] $labels
     */
    public function __construct(
        // Required fields
        public string $id,
        public string $title,
        public string $description,
        public StatusEnum $status,
        public PriorityEnum $priority,
        public IssueTypeEnum $issueType,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,

        // Optional fields
        public ?string $assignee = null,
        public ?string $design = null,
        public ?string $acceptanceCriteria = null,
        public ?string $notes = null,
        public ?int $estimatedMinutes = null,
        public ?DateTimeImmutable $closedAt = null,
        public ?string $closeReason = null,
        public ?string $externalRef = null,
        public array $labels = [],
        public ?int $compactionLevel = null,
        public ?DateTimeImmutable $compactedAt = null,
        public ?string $compactedAtCommit = null,
        public ?int $originalSize = null,
        public array $dependencies = [],
        public array $comments = [],
    ) {
        $this->validateClosedInvariant();
        $this->validateEstimatedMinutes();
        $this->validateTitle();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            description: $data['description'],
            status: StatusEnum::from($data['status']),
            priority: PriorityEnum::from((int)$data['priority']),
            issueType: IssueTypeEnum::from($data['issue_type']),
            createdAt: new DateTimeImmutable($data['created_at']),
            updatedAt: new DateTimeImmutable($data['updated_at']),
            assignee: $data['assignee'] ?? null,
            design: $data['design'] ?? null,
            acceptanceCriteria: $data['acceptance_criteria'] ?? null,
            notes: $data['notes'] ?? null,
            estimatedMinutes: isset($data['estimated_minutes']) ? (int)$data['estimated_minutes'] : null,
            closedAt: isset($data['closed_at']) ? new DateTimeImmutable($data['closed_at']) : null,
            closeReason: $data['close_reason'] ?? null,
            externalRef: $data['external_ref'] ?? null,
            labels: $data['labels'] ?? [],
            compactionLevel: isset($data['compaction_level']) ? (int)$data['compaction_level'] : null,
            compactedAt: isset($data['compacted_at']) ? new DateTimeImmutable($data['compacted_at']) : null,
            compactedAtCommit: $data['compacted_at_commit'] ?? null,
            originalSize: isset($data['original_size']) ? (int)$data['original_size'] : null,
            dependencies: isset($data['dependencies'])
                ? array_map(fn($dep) => DependencyDTO::fromArray($dep), $data['dependencies'])
                : [],
            comments: isset($data['comments'])
                ? array_map(fn($comment) => CommentDTO::fromArray($comment), $data['comments'])
                : [],
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'issue_type' => $this->issueType->value,
            'created_at' => $this->createdAt->format('c'),
            'updated_at' => $this->updatedAt->format('c'),
        ];

        // Add optional fields only if they have values
        if ($this->assignee !== null) {
            $data['assignee'] = $this->assignee;
        }
        if ($this->design !== null) {
            $data['design'] = $this->design;
        }
        if ($this->acceptanceCriteria !== null) {
            $data['acceptance_criteria'] = $this->acceptanceCriteria;
        }
        if ($this->notes !== null) {
            $data['notes'] = $this->notes;
        }
        if ($this->estimatedMinutes !== null) {
            $data['estimated_minutes'] = $this->estimatedMinutes;
        }
        if ($this->closedAt !== null) {
            $data['closed_at'] = $this->closedAt->format('c');
        }
        if ($this->closeReason !== null) {
            $data['close_reason'] = $this->closeReason;
        }
        if ($this->externalRef !== null) {
            $data['external_ref'] = $this->externalRef;
        }
        if (!empty($this->labels)) {
            $data['labels'] = $this->labels;
        }
        if ($this->compactionLevel !== null) {
            $data['compaction_level'] = $this->compactionLevel;
        }
        if ($this->compactedAt !== null) {
            $data['compacted_at'] = $this->compactedAt->format('c');
        }
        if ($this->compactedAtCommit !== null) {
            $data['compacted_at_commit'] = $this->compactedAtCommit;
        }
        if ($this->originalSize !== null) {
            $data['original_size'] = $this->originalSize;
        }
        if (!empty($this->dependencies)) {
            $data['dependencies'] = array_map(fn($dep) => $dep->toArray(), $this->dependencies);
        }
        if (!empty($this->comments)) {
            $data['comments'] = array_map(fn($comment) => $comment->toArray(), $this->comments);
        }

        return $data;
    }

    private function validateClosedInvariant(): void
    {
        if ($this->status === StatusEnum::CLOSED && $this->closedAt === null) {
            throw new \InvalidArgumentException('Closed issues must have a closedAt timestamp');
        }
        if ($this->status !== StatusEnum::CLOSED && $this->closedAt !== null) {
            throw new \InvalidArgumentException('Non-closed issues must not have a closedAt timestamp');
        }
    }

    private function validateEstimatedMinutes(): void
    {
        if ($this->estimatedMinutes !== null && $this->estimatedMinutes < 0) {
            throw new \InvalidArgumentException('Estimated minutes must be non-negative');
        }
    }

    private function validateTitle(): void
    {
        if (empty($this->title)) {
            throw new \InvalidArgumentException('Title is required and cannot be empty');
        }
        if (strlen($this->title) > 500) {
            throw new \InvalidArgumentException('Title cannot exceed 500 characters');
        }
    }
}
