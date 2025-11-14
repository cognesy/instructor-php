<?php declare(strict_types=1);

namespace Cognesy\Http\Collections;

use Cognesy\Http\Data\HttpRequest;
use Cognesy\Utils\Collection\ArrayList;
use Traversable;

/** @implements \IteratorAggregate<int, HttpRequest> */
final readonly class HttpRequestList implements \Countable, \IteratorAggregate
{
    /** @var ArrayList<HttpRequest> */
    private ArrayList $requests;

    public function __construct(
        ?ArrayList $requests = null,
    ) {
        $this->requests = $requests ?? ArrayList::empty();
    }

    // FACTORIES ////////////////////////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    public static function of(HttpRequest ...$requests): self {
        return new self(ArrayList::of(...$requests));
    }

    /** @param array<HttpRequest> $requests */
    public static function fromArray(array $requests): self {
        return new self(ArrayList::fromArray($requests));
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    /** @return list<HttpRequest> */
    public function all(): array {
        return $this->requests->all();
    }

    public function first(): ?HttpRequest {
        return $this->requests->first();
    }

    public function last(): ?HttpRequest {
        return $this->requests->last();
    }

    public function isEmpty(): bool {
        return $this->requests->isEmpty();
    }

    #[\Override]
    public function count(): int {
        return count($this->requests);
    }

    /** @return Traversable<int, HttpRequest> */
    #[\Override]
    public function getIterator(): Traversable {
        return $this->requests->getIterator();
    }

    // MUTATORS /////////////////////////////////////////////////////////////////

    public function withAppended(HttpRequest $request): self {
        return new self($this->requests->withAppended($request));
    }

    public function withPrepended(HttpRequest $request): self {
        return new self($this->requests->withInserted(0, $request));
    }

    /** @param callable(HttpRequest): bool $predicate */
    public function filter(callable $predicate): self {
        return new self($this->requests->filter($predicate));
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(fn($request) => $request->toArray(), $this->requests->all());
    }

    public static function fromSerializedArray(array $data): self {
        $requests = array_map(
            fn(array $r) => HttpRequest::fromArray($r),
            $data
        );
        return new self(ArrayList::fromArray($requests));
    }
}
