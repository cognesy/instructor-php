<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\CatReducer;

final readonly class Cat implements Transducer
{
    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new CatReducer($reducer);
    }
}

