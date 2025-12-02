<?php

declare(strict_types=1);

namespace Cognesy\Auxiliary\Beads\Infrastructure\Parser;

use Cognesy\Auxiliary\Beads\Domain\Model\Comment;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\Agent;
use Cognesy\Auxiliary\Beads\Domain\ValueObject\TaskId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Comment Parser
 *
 * Converts bd JSON output to Comment domain entities.
 */
final class CommentParser
{
    /**
     * Parse single comment from bd JSON
     *
     * @param  array<string, mixed>  $data
     */
    public function parse(array $data): Comment
    {
        $this->validate($data);

        return Comment::create(
            id: (int) $data['id'],
            taskId: new TaskId((string) $data['issue_id']),
            author: new Agent((string) $data['author']),
            text: (string) $data['text'],
            createdAt: new DateTimeImmutable((string) $data['created_at']),
        );
    }

    /**
     * Parse multiple comments from bd JSON
     *
     * @param  array<mixed>  $dataArray
     * @return array<Comment>
     */
    public function parseMany(array $dataArray): array
    {
        $comments = [];

        foreach ($dataArray as $data) {
            if (! is_array($data)) {
                continue;
            }

            $comments[] = $this->parse($data);
        }

        return $comments;
    }

    /**
     * Validate required fields
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InvalidArgumentException
     */
    private function validate(array $data): void
    {
        $required = ['id', 'issue_id', 'author', 'text', 'created_at'];

        foreach ($required as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }
    }
}
