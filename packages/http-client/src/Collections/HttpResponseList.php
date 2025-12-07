<?php declare(strict_types=1);

namespace Cognesy\Http\Collections;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Utils\Collection\ArrayList;
use Cognesy\Utils\Result\Result;
use Traversable;

/**
 * Collection of Result objects containing HttpResponse or exceptions.
 *
 * @implements \IteratorAggregate<int, Result>
 */
final readonly class HttpResponseList implements \Countable, \IteratorAggregate
{
    /** @var ArrayList<Result> */
    private ArrayList $responses;

    public function __construct(
        ?ArrayList $responses = null,
    ) {
        $this->responses = $responses ?? ArrayList::empty();
    }

    // FACTORIES ////////////////////////////////////////////////////////////////

    public static function empty(): self {
        return new self();
    }

    /** @param Result ...$responses */
    public static function of(Result ...$responses): self {
        return new self(ArrayList::of(...$responses));
    }

    /** @param array<Result> $responses */
    public static function fromArray(array $responses): self {
        return new self(ArrayList::fromArray($responses));
    }

    // ACCESSORS ////////////////////////////////////////////////////////////////

    /** @return list<Result> */
    public function all(): array {
        return $this->responses->all();
    }

    public function first(): ?Result {
        return $this->responses->first();
    }

    public function last(): ?Result {
        return $this->responses->last();
    }

    public function isEmpty(): bool {
        return $this->responses->isEmpty();
    }

    #[\Override]
    public function count(): int {
        return count($this->responses);
    }

    /** @return Traversable<int, Result> */
    #[\Override]
    public function getIterator(): Traversable {
        return $this->responses->getIterator();
    }

    // QUERY METHODS ////////////////////////////////////////////////////////////

    /** @return list<HttpResponse> */
    public function successful(): array {
        return array_map(
            fn(Result $r) => $r->unwrap(),
            array_filter($this->responses->all(), fn(Result $r) => $r->isSuccess())
        );
    }

    /** @return list<mixed> */
    public function failed(): array {
        return array_map(
            fn(Result $r) => $r->error(),
            array_filter($this->responses->all(), fn(Result $r) => $r->isFailure())
        );
    }

    public function hasFailures(): bool {
        foreach ($this->responses as $response) {
            if ($response->isFailure()) {
                return true;
            }
        }
        return false;
    }

    public function hasSuccesses(): bool {
        foreach ($this->responses as $response) {
            if ($response->isSuccess()) {
                return true;
            }
        }
        return false;
    }

    public function successCount(): int {
        return count(array_filter($this->responses->all(), fn(Result $r) => $r->isSuccess()));
    }

    public function failureCount(): int {
        return count(array_filter($this->responses->all(), fn(Result $r) => $r->isFailure()));
    }

    // MUTATORS /////////////////////////////////////////////////////////////////

    public function withAppended(Result $response): self {
        return new self($this->responses->withAppended($response));
    }

    /** @param callable(Result): bool $predicate */
    public function filter(callable $predicate): self {
        return new self($this->responses->filter($predicate));
    }

    /** @param callable(Result): Result $mapper */
    public function map(callable $mapper): self {
        $mapped = $this->responses->map($mapper);
        return new self($mapped instanceof ArrayList ? $mapped : ArrayList::fromArray($mapped->toArray()));
    }

    // SERIALIZATION ////////////////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            fn(Result $result) => match(true) {
                $result->isSuccess() => ['success' => true, 'data' => $result->unwrap()->toArray()],
                default => ['success' => false, 'error' => (string) $result->error()],
            },
            $this->responses->all()
        );
    }

    public static function fromSerializedArray(array $data): self {
        $responses = array_map(
            fn(array $item) => match($item['success']) {
                true => Result::success(HttpResponse::fromArray($item['data'])),
                false => Result::failure(new \Exception($item['error'])),
            },
            $data
        );
        return new self(ArrayList::fromArray($responses));
    }
}
