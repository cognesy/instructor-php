<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\AppendReducer;

final readonly class Append implements Transducer
{
    private array $values;

    public function __construct(mixed ...$values) {
        $this->values = $values;
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new AppendReducer($this->values, $reducer);
    }
}
