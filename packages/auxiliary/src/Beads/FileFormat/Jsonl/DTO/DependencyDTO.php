<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO;

use Cognesy\Auxiliary\Beads\FileFormat\Jsonl\Enums\DependencyTypeEnum;
use DateTimeImmutable;

final readonly class DependencyDTO
{
    public function __construct(
        public string $issueId,
        public string $dependsOnId,
        public DependencyTypeEnum $type,
        public DateTimeImmutable $createdAt,
        public string $createdBy,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            issueId: $data['issue_id'],
            dependsOnId: $data['depends_on_id'],
            type: DependencyTypeEnum::from($data['type']),
            createdAt: new DateTimeImmutable($data['created_at']),
            createdBy: $data['created_by'],
        );
    }

    public function toArray(): array
    {
        return [
            'issue_id' => $this->issueId,
            'depends_on_id' => $this->dependsOnId,
            'type' => $this->type->value,
            'created_at' => $this->createdAt->format('c'),
            'created_by' => $this->createdBy,
        ];
    }
}
