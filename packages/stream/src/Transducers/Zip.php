<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\ZipReducer;

final readonly class Zip implements Transducer
{
    private array $iterables;

    public function __construct(iterable ...$iterables) {
        $this->iterables = $iterables;
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ZipReducer($reducer, ...$this->iterables);
    }
}
