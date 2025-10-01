<?php declare(strict_types=1);

namespace Cognesy\Instructor\Collections;

use Cognesy\Instructor\Data\StructuredOutputAttempt;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Utils\Collection\ArrayList;
use Traversable;

final readonly class StructuredOutputAttempts implements \Countable, \IteratorAggregate
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
        return new self(ArrayList::of($attempts));
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    public function all(): array {
        return $this->attempts->all();
    }

    public function first(): ?StructuredOutputAttempt {
        return $this->attempts->first();
    }

    public function last(): ?StructuredOutputAttempt {
        return $this->attempts->last();
    }

    public function hasAny(): bool {
        return count($this->attempts) > 0;
    }

    public function count(): int {
        return count($this->attempts);
    }

    public function getIterator(): Traversable {
        return $this->attempts->getIterator();
    }

    // MUTATORS /////////////////////////////////////////////////////////////////

    public function withNewAttempt(StructuredOutputAttempt $attempt) : self {
        return new self(attempts: $this->attempts->withAppended($attempt));
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'attempts' => array_map(fn($attempt) => $attempt->toArray(), $this->attempts->all()),
        ];
    }

    public static function fromArray(array $data): self {
        $responses = array_map(
            fn(array $r) => StructuredOutputAttempt::fromArray($r),
            $data ?? []
        );
        return new self(ArrayList::fromArray($responses));
    }

    public function usage(): Usage {
        $total = Usage::none();
        foreach ($this->attempts->all() as $attempt) {
            $total = $total->withAccumulated($attempt->usage());
        }
        return $total;
    }
}
