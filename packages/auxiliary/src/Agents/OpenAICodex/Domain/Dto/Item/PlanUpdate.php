<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Dto\Item;

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
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'completed'),
            plan: (string)($data['plan'] ?? ''),
        );
    }
}
