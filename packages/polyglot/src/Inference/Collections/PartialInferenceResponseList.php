<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Collections;

use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;
use Cognesy\Utils\Collection\ArrayList;
use Traversable;

final readonly class PartialInferenceResponseList implements \Countable, \IteratorAggregate
{
    /** @param ArrayList<PartialInferenceResponse> $responses */
    private ArrayList $responses;

    private function __construct(?ArrayList $responses = null) {
        $this->responses = $responses ?? ArrayList::empty();
    }

    public static function empty(): self {
        return new self();
    }

    public static function of(PartialInferenceResponse ...$responses): self {
        return new self(ArrayList::of(...$responses));
    }

    // ACCESSORS ////////////////////////////////////////////////////

    public function all(): array {
        return $this->responses->toArray();
    }

    public function getIterator(): Traversable {
        return $this->responses->getIterator();
    }

    public function count(): int {
        return $this->responses->count();
    }

    public function isEmpty(): bool {
        return $this->responses->isEmpty();
    }

    public function last(): ?PartialInferenceResponse {
        return $this->responses->last();
    }

    public function first(): ?PartialInferenceResponse {
        return $this->responses->first();
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withNewPartialResponse(PartialInferenceResponse $response): self {
        return new self($this->responses->withAppended($response));
    }

    // SERIALIZATION ////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(
            fn(PartialInferenceResponse $r) => $r->toArray(),
            $this->responses->toArray()
        );
    }

    public static function fromArray(array $data): self {
        $items = array_map(
            fn(array $item) => PartialInferenceResponse::fromArray($item),
            $data
        );
        return new self(ArrayList::fromArray($items));
    }
}
