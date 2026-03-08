<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

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
            id: Normalize::toString($data['id'] ?? ''),
            status: Normalize::toString($data['status'] ?? 'completed', 'completed'),
            path: Normalize::toString($data['path'] ?? ''),
            action: Normalize::toString($data['action'] ?? 'modify', 'modify'),
            content: Normalize::toNullableString($data['content'] ?? null),
            diff: Normalize::toNullableString($data['diff'] ?? null),
        );
    }
}
