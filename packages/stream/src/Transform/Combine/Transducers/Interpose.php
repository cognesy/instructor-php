<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Combine\Decorators\InterposeReducer;

final class Interpose implements Transducer
{
    public function __construct(private mixed $separator) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new InterposeReducer(
            inner: $reducer,
            separator: $this->separator,
        );
    }
}