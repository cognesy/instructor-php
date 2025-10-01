<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Collections;

use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Utils\Collection\ArrayList;

class InferenceResponseList implements \IteratorAggregate, \Countable
{
    /** @var ArrayList<InferenceResponse> */
    private ArrayList $responses;

    private function __construct(?ArrayList $responses = null) {
        $this->responses = $responses ?? ArrayList::empty();
    }

    // CONSTRUCTION /////////////////////////////////////////////////////

    public static function of(InferenceResponse ...$responses): self {
        return new self(ArrayList::of($responses));
    }

    // ACCESSORS ////////////////////////////////////////////////////////

    public function all(): array {
        return $this->responses->toArray();
    }

    public function count(): int {
        return $this->responses->count();
    }

    public function getIterator(): \Traversable {
        return $this->responses->getIterator();
    }

    // MUTATORS /////////////////////////////////////////////////////////

    // SERIALIZATION ////////////////////////////////////////////////////

    // INTERNAL /////////////////////////////////////////////////////////
}