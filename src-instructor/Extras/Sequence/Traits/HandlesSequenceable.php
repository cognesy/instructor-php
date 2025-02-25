<?php

namespace Cognesy\Instructor\Extras\Sequence\Traits;

trait HandlesSequenceable
{
    private string $name;
    private string $description;
    private string $class;

    public array $list = [];

    public static function of(string $class, string $name = '', string $description = '') : static {
        return new self($class, $name, $description);
    }

    public function toArray() : array {
        return $this->list;
    }

    public function all() : array {
        return $this->list;
    }

    public function count(): int {
        return count($this->list);
    }

    public function get(int $index) : mixed {
        return $this->list[$index] ?? null;
    }

    public function first() : mixed {
        return $this->list[0] ?? null;
    }

    public function last() : mixed {
        if (empty($this->list)) {
            return null;
        }
        $count = count($this->list);
        return $this->list[$count - 1];
    }

    public function push(mixed $item) : void {
        $this->list[] = $item;
    }

    public function pop() : mixed {
        return array_pop($this->list);
    }

    public function isEmpty() : bool {
        return empty($this->list);
    }
}