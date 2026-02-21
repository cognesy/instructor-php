<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Application\Dto;

use Cognesy\AgentCtrl\ClaudeCode\Domain\Dto\StreamEvent\StreamEvent;

final readonly class ClaudeEventCollection
{
    /** @var list<StreamEvent> */
    private array $items;

    /**
     * @param list<StreamEvent> $items
     */
    private function __construct(array $items) {
        $this->items = array_values($items);
    }

    public static function empty() : self {
        return new self([]);
    }

    /**
     * @param list<StreamEvent> $items
     */
    public static function of(array $items) : self {
        return new self($items);
    }

    /**
     * @return list<StreamEvent>
     */
    public function all() : array {
        return $this->items;
    }

    public function count() : int {
        return count($this->items);
    }

    public function isEmpty() : bool {
        return $this->count() === 0;
    }
}
