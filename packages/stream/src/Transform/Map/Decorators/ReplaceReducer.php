<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Map\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class ReplaceReducer implements Reducer
{
    public function __construct(
        private Reducer $inner,
        private array $replacementMap,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        $value = $this->replacementMap[$reducible] ?? $reducible;
        return $this->inner->step($accumulator, $value);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        return $this->inner->complete($accumulator);
    }
}
