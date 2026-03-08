<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenAICodex\Domain\Dto\Item;

use Cognesy\AgentCtrl\Common\Value\Normalize;

/**
 * Web search result item
 *
 * Example: {"id":"item_9","type":"web_search","query":"rust async patterns","status":"completed"}
 */
final readonly class WebSearch extends Item
{
    /**
     * @param list<array<string, mixed>>|null $results
     */
    public function __construct(
        string $id,
        string $status,
        public string $query,
        public ?array $results = null,
    ) {
        parent::__construct($id, $status);
    }

    public function itemType(): string
    {
        return 'web_search';
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Normalize::toString($data['id'] ?? ''),
            status: Normalize::toString($data['status'] ?? 'in_progress', 'in_progress'),
            query: Normalize::toString($data['query'] ?? ''),
            results: isset($data['results']) && is_array($data['results']) ? $data['results'] : null,
        );
    }
}
