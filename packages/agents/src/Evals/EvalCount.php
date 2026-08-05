<?php declare(strict_types=1);

namespace Cognesy\Agents\Evals;

use Closure;

final readonly class EvalCount
{
    /** @param Closure(int): bool $predicate */
    private function __construct(private Closure $predicate) {}

    public static function atLeast(int $minimum): self {
        return new self(static fn (int $count): bool => $count >= $minimum);
    }

    public static function atMost(int $maximum): self {
        return new self(static fn (int $count): bool => $count <= $maximum);
    }

    public static function between(int $minimum, int $maximum): self {
        return new self(static fn (int $count): bool => $count >= $minimum && $count <= $maximum);
    }

    /** @param Closure(int): bool $predicate */
    public static function satisfies(Closure $predicate): self {
        return new self($predicate);
    }

    public function matches(int $count): bool {
        return (bool) ($this->predicate)($count);
    }
}
