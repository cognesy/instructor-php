<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Support;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Iterative\Transduction;
use Cognesy\Utils\Transducer\Iterative\TransductionState;
use Iterator;
use SplQueue;

/**
 * Produces an iterator that drives a transducer pipeline and
 * yields values enqueued by a sink reducer.
 */
final class TransducedIterator
{
    public static function from(iterable $base, Reducer $reducer, SplQueue $queue): Iterator {
        $state = TransductionState::fromReducer($base, $reducer, $queue);
        while (Transduction::hasNext($state)) {
            $state = Transduction::step($state);
            foreach ($state->emissions() as $item) {
                yield $item;
            }
        }
        $state = Transduction::finalize($state);
        foreach ($state->emissions() as $item) {
            yield $item;
        }
    }
}
