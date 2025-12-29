<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

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
            id: (string)($data['id'] ?? ''),
            status: (string)($data['status'] ?? 'unknown'),
            rawType: (string)($data['type'] ?? 'unknown'),
            rawData: $data,
        );
    }
}
