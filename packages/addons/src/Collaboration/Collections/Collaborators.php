<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Collections;

use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;

final class Collaborators
{
    /** @var CanCollaborate[] */
    private array $collaborators;

    public function __construct(CanCollaborate ...$collaborators) {
        $this->collaborators = $collaborators;
    }

    public function add(CanCollaborate ...$collaborators): self {
        return new self(...array_merge($this->collaborators, $collaborators));
    }

    public function count(): int {
        return count($this->collaborators);
    }

    public function isEmpty(): bool {
        return $this->collaborators === [];
    }

    public function at(int $index): ?CanCollaborate {
        return $this->collaborators[$index] ?? null;
    }

    /** @return CanCollaborate[] */
    public function all(): array {
        return $this->collaborators;
    }

    /** @return string[] */
    public function names(): array {
        return array_map(fn(CanCollaborate $p) => $p->name(), $this->collaborators);
    }
}
