<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Data;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;

final class Participants
{
    /** @var CanParticipateInChat[] */
    private array $items;

    public function __construct(CanParticipateInChat ...$items)
    {
        $this->items = $items;
    }

    public function add(CanParticipateInChat ...$items) : self
    {
        return new self(...array_merge($this->items, $items));
    }

    public function count() : int { return count($this->items); }

    public function isEmpty() : bool { return $this->items === []; }

    public function at(int $index) : ?CanParticipateInChat
    {
        return $this->items[$index] ?? null;
    }

    /** @return CanParticipateInChat[] */
    public function all() : array { return $this->items; }
}

