<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\StreamEvent;

use Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item\Item;

/**
 * Event emitted when an item starts processing
 *
 * Example: {"type":"item.started","item":{"id":"item_1","type":"command_execution","command":"bash -lc ls","status":"in_progress"}}
 */
final readonly class ItemStartedEvent extends StreamEvent
{
    public function __construct(
        public Item $item,
    ) {}

    public function type(): string
    {
        return 'item.started';
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
