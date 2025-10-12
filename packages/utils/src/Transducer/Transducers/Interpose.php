<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\InterposeReducer;

final class Interpose implements Transducer
{
    public function __construct(private mixed $separator) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new InterposeReducer($this->separator, $reducer);
    }
}