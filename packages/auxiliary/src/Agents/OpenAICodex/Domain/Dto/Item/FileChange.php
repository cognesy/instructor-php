<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\Item;

/**
 * File modification item
 *
 * Example: {"id":"item_5","type":"file_change","path":"src/main.rs","action":"modify","status":"completed"}
 */
final readonly class FileChange extends Item
{
    public function __construct(
        string $id,
        string $status,
        public string $path,
        public string $action,
        public ?string $content = null,
        public ?string $diff = null,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'file_change';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'completed'),
            path: (string)($data['path'] ?? ''),
            action: (string)($data['action'] ?? 'modify'),
            content: isset($data['content']) ? (string)$data['content'] : null,
            diff: isset($data['diff']) ? (string)$data['diff'] : null,
        );
    }
}
