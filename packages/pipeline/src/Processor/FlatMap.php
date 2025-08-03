<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Utils\Result\Result;

readonly class FlatMap implements CanProcessState {
    /**
     * @param Closure(mixed):mixed $mapper
     */
    public function __construct(
        private Closure $mapper,
    ) {}

    public function process(ProcessingState $state): ProcessingState {
        if ($state->isFailure()) {
            return $state;
        }

        $result = ($this->mapper)($state->value());
        return $result instanceof ProcessingState
            ? $state->combine($result)
            : $state->withResult(Result::from($result));
    }
}