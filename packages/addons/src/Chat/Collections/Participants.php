<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Collections;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;

final class Participants
{
    /** @var CanParticipateInChat[] */
    private array $participants;

    public function __construct(CanParticipateInChat ...$participants) {
        $this->participants = $participants;
    }

    public function add(CanParticipateInChat ...$participants): self {
        return new self(...array_merge($this->participants, $participants));
    }

    public function count(): int {
        return count($this->participants);
    }

    public function isEmpty(): bool {
        return $this->participants === [];
    }

    public function at(int $index): ?CanParticipateInChat {
        return $this->participants[$index] ?? null;
    }

    /** @return CanParticipateInChat[] */
    public function all(): array {
        return $this->participants;
    }

    /** @return string[] */
    public function names(): array {
        return array_map(static fn(CanParticipateInChat $p) => $p->name(), $this->participants);
    }
}
