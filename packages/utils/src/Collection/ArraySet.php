<?php declare(strict_types=1);

namespace Cognesy\Utils\Collection;

use ArrayIterator;
use Cognesy\Utils\Collection\Contracts\SetInterface;
use Traversable;

/**
 * @template T
 * @implements SetInterface<T>
 */
final class ArraySet implements SetInterface
{
    /** @var array<string,T> */
    private array $byHash;

    /** @var callable(T):string */
    private $hashOf;

    /** @var callable(T,T):bool */
    private $equals;

    /**
     * @param callable(T):string $hashOf Stable hash for set membership (e.g., fn(User $u)=>$u->id()).
     * @param callable(T,T):bool $equals Equality check for safety (defaults to hash equality).
     * @param list<T> $values
     */
    private function __construct(callable $hashOf, callable $equals, array $values) {
        $this->hashOf = $hashOf;
        $this->equals = $equals;
        $map = [];
        foreach ($values as $v) {
            $map[$hashOf($v)] = $v;
        }
        $this->byHash = $map;
    }

    /**
     * @template U
     * @param callable(U):string $hashOf
     * @param callable(U,U):bool|null $equals
     * @return ArraySet<U>
     */
    public static function empty(callable $hashOf, ?callable $equals = null): self {
        /** @var callable(U,U):bool $eq */
        $eq = $equals ?? fn($a, $b) => $hashOf($a) === $hashOf($b);
        /** @var list<U> $values */
        $values = [];
        return new self($hashOf, $eq, $values);
    }

    /**
     * @template U
     * @param callable(U):string $hashOf
     * @param list<U> $values
     * @param callable(U,U):bool|null $equals
     * @return ArraySet<U>
     */
    public static function fromValues(callable $hashOf, array $values, ?callable $equals = null): self {
        /** @var callable(U,U):bool $eq */
        $eq = $equals ?? fn($a, $b) => $hashOf($a) === $hashOf($b);
        return new self($hashOf, $eq, $values);
    }

    public function count(): int {
        return count($this->byHash);
    }

    public function contains(mixed $item): bool {
        $h = ($this->hashOf)($item);
        if (!array_key_exists($h, $this->byHash)) return false;
        return ($this->equals)($this->byHash[$h], $item);
    }

    public function withAdded(mixed ...$items): static {
        $n = $this->byHash;
        foreach ($items as $i) {
            $n[($this->hashOf)($i)] = $i;
        }
        return new self($this->hashOf, $this->equals, array_values($n));
    }

    public function withRemoved(mixed ...$items): static {
        $n = $this->byHash;
        foreach ($items as $i) {
            $h = ($this->hashOf)($i);
            if (isset($n[$h]) && ($this->equals)($n[$h], $i)) {
                unset($n[$h]);
            }
        }
        return new self($this->hashOf, $this->equals, array_values($n));
    }

    public function union(SetInterface $other): static {
        $n = $this->byHash;
        foreach ($other as $i) {
            $n[($this->hashOf)($i)] = $i;
        }
        return new self($this->hashOf, $this->equals, array_values($n));
    }

    public function intersect(SetInterface $other): static {
        $n = [];
        foreach ($this->byHash as $h => $v) {
            if ($other->contains($v)) {
                $n[$h] = $v;
            }
        }
        return new self($this->hashOf, $this->equals, array_values($n));
    }

    public function diff(SetInterface $other): static {
        $n = [];
        foreach ($this->byHash as $h => $v) {
            if (!$other->contains($v)) {
                $n[$h] = $v;
            }
        }
        return new self($this->hashOf, $this->equals, array_values($n));
    }

    /** @return list<T> */
    public function values(): array {
        /** @var list<T> $v */
        $v = array_values($this->byHash);
        return $v;
    }

    /** @return Traversable<int,T> */
    public function getIterator(): Traversable {
        return new ArrayIterator(array_values($this->byHash));
    }
}