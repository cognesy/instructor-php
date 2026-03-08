<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Text message from the agent
 *
 * Example: {"id":"item_3","type":"agent_message","text":"Repo contains docs, sdk, and examples directories."}
 */
final readonly class AgentMessage extends Item
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
        return 'agent_message';
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
