<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\Item;

/**
 * Internal reasoning step
 *
 * Example: {"id":"item_13","type":"reasoning","text":"Analyzing the error message to determine root cause...","status":"completed"}
 */
final readonly class Reasoning extends Item
{
    public function __construct(
        string $id,
        string $status,
        public string $text,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'reasoning';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'completed'),
            text: (string)($data['text'] ?? ''),
        );
    }
}
