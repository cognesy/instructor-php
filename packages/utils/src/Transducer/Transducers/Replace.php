<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\ReplaceReducer;

final readonly class Replace implements Transducer
{
    public function __construct(private array $replacementMap) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ReplaceReducer($this->replacementMap, $reducer);
    }
}
