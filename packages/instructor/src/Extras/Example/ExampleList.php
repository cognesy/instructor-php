<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extras\Example;

use Cognesy\Utils\Collection\ArrayList;

final readonly class ExampleList
{
    private ArrayList $examples;

    public function __construct(Example ...$examples) {
        $this->examples = ArrayList::of(...$examples);
    }

    // ACCESSORS /////////////////////////////////////////////////////

    public function isEmpty() : bool {
        return $this->examples->isEmpty();
    }

    public function all(): array {
        return $this->examples->toArray();
    }

    // SERIALIZATION /////////////////////////////////////////////////

    public function toArray(): array {
        return array_map(fn(Example $e) => $e->toArray(), $this->examples->toArray());
    }

    public static function fromArray(array $data): self {
        $examples = array_map(fn(array $e) => Example::fromArray($e), $data);
        return new self(...$examples);
    }
}