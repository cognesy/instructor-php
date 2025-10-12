<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\RandomSampleReducer;

final readonly class RandomSample implements Transducer
{
    public function __construct(private float $probability) {
        if ($probability < 0.0 || $probability > 1.0) {
            throw new \InvalidArgumentException('probability must be between 0.0 and 1.0');
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new RandomSampleReducer($this->probability, $reducer);
    }
}
