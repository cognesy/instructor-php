<?php declare(strict_types=1);

namespace Cognesy\Stream\Iterator;

use ArrayIterator;
use InvalidArgumentException;
use Iterator;
use Traversable;

final class IteratorUtils
{
    private function __construct() {}

    public static function toIterator(iterable $iterable): Iterator {
        if ($iterable instanceof Iterator) {
            return $iterable;
        }
        if (is_array($iterable)) {
            return new ArrayIterator($iterable);
        }
        if ($iterable instanceof Traversable) {
            return (function () use ($iterable): Iterator {
                foreach ($iterable as $item) {
                    yield $item;
                }
            })();
        }
        throw new InvalidArgumentException('Expected iterable');
    }
}
