<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Misc\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Composes multiple transducers
 *
 * Example:
 * f1(x) => x + 1
 * f2(x) => x * 2
 * composed:
 * fn(x) => f2(f1(x)) => (x + 1) * 2
 */
final readonly class Compose implements Transducer
{
    /**
     * @param Transducer[] $transducers
     */
    public function __construct(
        private array $transducers
    ) {}

    public static function from(Transducer ...$transducers): self {
        return new self($transducers);
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return array_reduce(
            array: array_reverse($this->transducers),
            callback: fn(Reducer $accumulatedReducerFn, Transducer $transducerFn) => $transducerFn($accumulatedReducerFn),
            initial: $reducer,
        );
    }
}