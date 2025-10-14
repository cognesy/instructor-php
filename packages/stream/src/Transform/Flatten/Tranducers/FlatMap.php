<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Flatten\Tranducers;

use Closure;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Map\Transducers\Map;

/**
 * Transducer that maps each input value to an iterable
 * and flattens the result into a single sequence.
 *
 * Example:
 * [1, 2, 3] with mapFn = fn($x) => [$x, $x * 10]
 * becomes [1, 10, 2, 20, 3, 30]
 */
final readonly class FlatMap implements Transducer
{
    /**
     * @param Closure(mixed): mixed $mapFn
     */
    public function __construct(private Closure $mapFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        $map = new Map($this->mapFn);
        $cat = new Cat();
        return $cat($map($reducer));
    }
}
