<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\ReplaceReducer;

final readonly class Replace implements Transducer
{
    public function __construct(private array $replacementMap) {}

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ReplaceReducer($this->replacementMap, $reducer);
    }
}
