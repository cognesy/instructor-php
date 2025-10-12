<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream;

use Cognesy\Utils\Stream\Support\QueueYieldReducer;
use Cognesy\Utils\Stream\Support\TransducedIterator;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Transducers\Compose;
use Iterator;
use SplQueue;

/**
 * @implements Stream<int, mixed>
 */
final readonly class TransducedStream implements Stream
{
    /** @var Transducer[] */
    private array $transducers;

    public function __construct(
        private iterable $base,
        Transducer ...$transducers,
    ) {
        $this->transducers = $transducers;
    }

    public static function from(iterable $base, Transducer ...$transducers): self {
        return new self($base, ...$transducers);
    }

    #[\Override]
    public function getIterator(): Iterator {
        $queue = new SplQueue();
        $yieldReducer = new QueueYieldReducer($queue);
        $stack = new Compose($this->transducers);
        $fn = $stack($yieldReducer);
        return TransducedIterator::from($this->base, $fn, $queue);
    }
}
