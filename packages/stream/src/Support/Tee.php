<?php declare(strict_types=1);

namespace Cognesy\Stream\Support;

use Cognesy\Stream\Iterator\IteratorUtils;
use InvalidArgumentException;
use Iterator;

final readonly class Tee
{
    /**
     * Split a single-pass iterable into N independent iterators.
     * Branches can consume at different speeds. Data is buffered
     * until all active branches advance past it. No rewind() is used.
     *
     * Note: If a branch is abandoned early (consumer stops), its
     * iterator is closed and the buffer can evict based on remaining
     * active branches only.
     *
     * @return array<int, Iterator>
     */
    public static function split(iterable $source, int $branches = 2): array {
        if ($branches < 1) {
            throw new InvalidArgumentException('branches must be >= 1');
        }
        $state = new TeeState(IteratorUtils::toIterator($source), $branches);
        $out = [];
        for ($i = 0; $i < $branches; $i++) {
            $out[] = self::makeBranch($state, $i);
        }
        return $out;
    }

    // INTERNAL ////////////////////////////////////////

    private static function makeBranch(TeeState $state, int $id): Iterator {
        try {
            while ($state->hasValue($id)) {
                yield $state->nextValue($id);
            }
        } finally {
            $state->deactivate($id);
        }
    }
}