<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Transform\Combine\Decorators\PrependReducer;

final readonly class Prepend implements Transducer
{
    private array $values;

    public function __construct(mixed ...$values) {
        $this->values = $values;
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new PrependReducer(
            inner: $reducer,
            values: $this->values,
        );
    }
}
