<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\AppendReducer;

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
