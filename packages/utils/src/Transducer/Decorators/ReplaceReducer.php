<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Decorators;

use Cognesy\Utils\Transducer\Contracts\Reducer;

final class ReplaceReducer implements Reducer
{
    public function __construct(
        private array $replacementMap,
        private Reducer $reducer,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->reducer->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $value = $this->replacementMap[$reducible] ?? $reducible;
        return $this->reducer->step($accumulator, $value);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->reducer->complete($accumulator);
    }
}
