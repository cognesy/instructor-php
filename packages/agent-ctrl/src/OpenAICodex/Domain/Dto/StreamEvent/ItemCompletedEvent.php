<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\Item;

/**
 * Event emitted when an item completes processing
 *
 * Example: {"type":"item.completed","item":{"id":"item_3","type":"agent_message","text":"Repo contains docs, sdk, and examples directories."}}
 */
final readonly class ItemCompletedEvent extends StreamEvent
{
    public function __construct(
        public Item $item,
    ) {}

    public function type(): string
    {
        return 'item.completed';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $itemData = $data['item'] ?? [];

        return new self(
            item: Item::fromArray($itemData),
        );
    }
}
