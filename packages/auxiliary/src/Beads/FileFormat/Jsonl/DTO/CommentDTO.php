<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\FileFormat\Jsonl\DTO;

use DateTimeImmutable;

final readonly class CommentDTO
{
    public function __construct(
        public int $id,
        public string $issueId,
        public string $author,
        public string $text,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int)$data['id'],
            issueId: $data['issue_id'],
            author: $data['author'],
            text: $data['text'],
            createdAt: new DateTimeImmutable($data['created_at']),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issue_id' => $this->issueId,
            'author' => $this->author,
            'text' => $this->text,
            'created_at' => $this->createdAt->format('c'),
        ];
    }
}
