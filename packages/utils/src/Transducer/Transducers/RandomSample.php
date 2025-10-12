<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\RandomSampleReducer;

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
