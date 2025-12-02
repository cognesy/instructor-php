<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Domain\Collection;

use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\DecodedObject;

final readonly class DecodedObjectCollection
{
    /** @var list<DecodedObject> */
    private array $items;

    /**
     * @param list<DecodedObject> $items
     */
    private function __construct(array $items) {
        $this->items = array_values($items);
    }

    public static function empty() : self {
        return new self([]);
    }

    /**
     * @param list<DecodedObject> $items
     */
    public static function of(array $items) : self {
        return new self($items);
    }

    /**
     * @return list<DecodedObject>
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
