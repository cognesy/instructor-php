<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Application\Tbd\Action;

use Cognesy\Auxiliary\Beads\Application\Tbd\TbdClock;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIdFactory;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdInputMapper;
use Cognesy\Auxiliary\Beads\Application\Tbd\TbdIssueStore;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO\IssueDTO;
use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\StatusEnum;
use DateTimeImmutable;
use RuntimeException;

class CreateAction
{
    public function __construct(
        private readonly TbdIssueStore $store,
        private readonly TbdIdFactory $ids,
        private readonly TbdClock $clock,
        private readonly TbdInputMapper $map,
    ) {}

    public function __invoke(
        string $filePath,
        string $title,
        string $description,
        ?string $type,
        ?string $priority,
        ?string $status,
        ?string $assignee,
        array $labels,
        ?string $id,
        ?string $createdAt,
        ?string $externalRef,
        ?string $acceptanceCriteria,
        ?string $notes,
        ?int $estimatedMinutes,
        ?string $design,
    ): IssueDTO {
        $issues = $this->store->load($filePath, allowMissing: true);
        $issueId = $id ?? $this->ids->generate();
        if ($this->exists($issues, $issueId)) {
            throw new RuntimeException("Issue already exists: {$issueId}");
        }

        $created = $this->clock->parse($createdAt);
        $dto = new IssueDTO(
            id: $issueId,
            title: $title,
            description: $description,
            status: $this->map->status($status),
            priority: $this->map->priority($priority),
            issueType: $this->map->type($type),
            createdAt: $created,
            updatedAt: $created,
            assignee: $assignee,
            design: $design,
            acceptanceCriteria: $acceptanceCriteria,
            notes: $notes,
            estimatedMinutes: $estimatedMinutes,
            closedAt: null,
            closeReason: null,
            externalRef: $externalRef,
            labels: $labels,
            compactionLevel: null,
            compactedAt: null,
            compactedAtCommit: null,
            originalSize: null,
            dependencies: [],
            comments: [],
        );

        $issues[] = $dto;
        $this->store->save($filePath, $issues);
        return $dto;
    }

    /**
     * @param IssueDTO[] $issues
     */
    private function exists(array $issues, string $id): bool {
        foreach ($issues as $issue) {
            if ($issue->id === $id) {
                return true;
            }
        }
        return false;
    }
}
