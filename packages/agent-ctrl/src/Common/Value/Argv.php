<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Value;

final readonly class Argv
{
    /** @var list<string> */
    private array $items;

    /**
     * @param list<string> $items
     */
    private function __construct(array $items) {
        $this->items = array_values($items);
    }

    /**
     * @param list<string> $items
     */
    public static function of(array $items) : self {
        return new self($items);
    }

    public function with(string $value) : self {
        $items = $this->items;
        $items[] = $value;
        return new self($items);
    }

    /**
     * @return list<string>
     */
    public function toArray() : array {
        return $this->items;
    }
}
