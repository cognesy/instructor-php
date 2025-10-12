<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Closure;
use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\DropUntilReducer;

final readonly class DropUntil implements Transducer
{
    /**
     * @param Closure(mixed): bool $conditionFn
     */
    public function __construct(private Closure $conditionFn) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new DropUntilReducer($this->conditionFn, $reducer);
    }
}
