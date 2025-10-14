<?php declare(strict_types=1);

namespace Cognesy\Stream\Transform\Combine\Decorators;

use Cognesy\Stream\Contracts\Reducer;

final class PrependReducer implements Reducer
{
    private bool $prepended = false;

    public function __construct(
        private Reducer $inner,
        private array $values,
    ) {}

    #[\Override]
    public function init(): mixed {
        return $this->inner->init();
    }

    #[\Override]
    public function step(mixed $accumulator, mixed $reducible): mixed {
        if (!$this->prepended) {
            foreach ($this->values as $value) {
                $accumulator = $this->inner->step($accumulator, $value);
            }
            $this->prepended = true;
        }
        return $this->inner->step($accumulator, $reducible);
    }

    #[\Override]
    public function complete(mixed $accumulator): mixed {
        // Handle case where no items were processed
        if (!$this->prepended) {
            foreach ($this->values as $value) {
                $accumulator = $this->inner->step($accumulator, $value);
            }
            $this->prepended = true;
        }
        return $this->inner->complete($accumulator);
    }
}
