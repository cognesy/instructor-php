<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Fallback item for unrecognized item types
 */
final readonly class UnknownItem extends Item
{
    /**
     * @param array<string, mixed> $rawData
     */
    public function __construct(
        string $id,
        string $status,
        public string $rawType,
        public array $rawData,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return $this->rawType;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Normalize::toString($data['id'] ?? ''),
            status: Normalize::toString($data['status'] ?? 'unknown', 'unknown'),
            rawType: Normalize::toString($data['type'] ?? 'unknown', 'unknown'),
            rawData: $data,
        );
    }
}
