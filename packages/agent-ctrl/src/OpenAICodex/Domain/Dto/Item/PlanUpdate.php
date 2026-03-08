<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Plan modification item
 *
 * Example: {"id":"item_11","type":"plan_update","plan":"1. Review code\n2. Implement fix\n3. Test","status":"completed"}
 */
final readonly class PlanUpdate extends Item
{
    public function __construct(
        string $id,
        string $status,
        public string $plan,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'plan_update';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Normalize::toString($data['id'] ?? ''),
            status: Normalize::toString($data['status'] ?? 'completed', 'completed'),
            plan: Normalize::toString($data['plan'] ?? ''),
        );
    }
}
