<?php declare(strict_types=1);

namespace Cognesy\Instructor\Collections;

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Collection\ArrayList;
use Traversable;

/** @implements \IteratorAggregate<int, StructuredOutputAttempt> */
final readonly class StructuredOutputAttemptList implements \Countable, \IteratorAggregate
{
    /** @var ArrayList<StructuredOutputAttempt> */
    private ArrayList $attempts;

    public function __construct(
        ?ArrayList $attempts = null,
    ) {
        $this->attempts = $attempts ?? ArrayList::empty();
    }

    public static function empty(): self {
        return new self();
    }

    public static function of(StructuredOutputAttempt ...$attempts): self {
        return new self(ArrayList::of(...$attempts));
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    /** @return list<StructuredOutputAttempt> */
    public function all(): array {
        return $this->attempts->all();
    }

    public function first(): ?StructuredOutputAttempt {
        return $this->attempts->first();
    }

    public function last(): ?StructuredOutputAttempt {
        return $this->attempts->last();
    }

    public function isEmpty(): bool {
        return $this->attempts->isEmpty();
    }

    #[\Override]
    public function count(): int {
        return count($this->attempts);
    }

    /** @return Traversable<int, StructuredOutputAttempt> */
    #[\Override]
    public function getIterator(): Traversable {
        return $this->attempts->getIterator();
    }

    // MUTATORS /////////////////////////////////////////////////////////////////

    public function withNewAttempt(StructuredOutputAttempt $attempt) : self {
        return new self(attempts: $this->attempts->withAppended($attempt));
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(fn($attempt) => $attempt->toArray(), $this->attempts->all());
    }

    public static function fromArray(array $data): self {
        $list = isset($data['attempts']) && is_array($data['attempts']) ? $data['attempts'] : $data;
        $responses = array_map(
            fn(array $r) => StructuredOutputAttempt::fromArray($r),
            $list
        );
        return new self(ArrayList::fromArray($responses));
    }

    public function usage(): Usage {
        $total = Usage::none();
        foreach ($this->attempts->all() as $attempt) {
            if ($attempt->isFinalized()) {
                $total = $total->withAccumulated($attempt->usage());
            }
        }
        return $total;
    }
}
