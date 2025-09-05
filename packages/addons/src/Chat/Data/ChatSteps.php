<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

final class ChatSteps
{
    /** @var ChatStep[] */
    private array $items;

    public function __construct(ChatStep ...$items)
    {
        $this->items = $items;
    }

    public function add(ChatStep ...$steps) : self
    {
        return new self(...array_merge($this->items, $steps));
    }

    public function count() : int { return count($this->items); }

    public function isEmpty() : bool { return $this->items === []; }

    public function last() : ?ChatStep
    {
        if ($this->isEmpty()) { return null; }
        return $this->items[count($this->items) - 1] ?? null;
    }

    /** @return ChatStep[] */
    public function all() : array { return $this->items; }
}

