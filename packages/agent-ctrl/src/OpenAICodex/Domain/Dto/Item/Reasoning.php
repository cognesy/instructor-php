<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

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
            id: Normalize::toString($data['id'] ?? ''),
            status: Normalize::toString($data['status'] ?? 'completed', 'completed'),
            text: Normalize::toString($data['text'] ?? ''),
        );
    }
}
