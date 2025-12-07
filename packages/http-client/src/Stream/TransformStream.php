<?php declare(strict_types=1);

namespace Cognesy\Http\Stream;

use Closure;

class TransformStream implements StreamInterface {
    /**
     * @param Closure(string): string $transformFn
     */
    public function __construct(
        private StreamInterface $source,
        private Closure $transformFn,
    ) {}

    #[\Override]
    public function getIterator(): \Traversable {
        foreach ($this->source as $chunk) {
            yield ($this->transformFn)($chunk);
        }
    }

    #[\Override]
    public function isCompleted(): bool {
        return $this->source->isCompleted();
    }
}