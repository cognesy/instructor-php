<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Processor;

use Closure;
use Cognesy\Pipeline\Enums\NullStrategy;
use Cognesy\Pipeline\ProcessingState;
use Cognesy\Pipeline\Traits\HandlesOutput;
use RuntimeException;

class ValueProcessor implements ProcessorInterface
{
    use HandlesOutput;

    private function __construct(
        private readonly Closure $callable,
        private NullStrategy $nullStrategy = NullStrategy::Allow,
    ) {}

    public static function from(
        callable $callable,
        NullStrategy $nullStrategy = NullStrategy::Allow
    ): self {
        return new self($callable, $nullStrategy);
    }

    public function process(ProcessingState $state): ProcessingState {
        return match(true) {
            $state->isSuccess() => $this->asProcessingState(
                input: ($this->callable)($state->result()->unwrap()),
                prior: $state,
                onNull: $this->nullStrategy
            ),
            $state->isFailure() => $state,
            default => throw new RuntimeException('Invalid processing state provided to ValueProcessor'),
        };
    }
}