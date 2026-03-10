<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Pipeline\Enums\ErrorStrategy;
use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;
use RuntimeException;

final class PipelineBuilder
{
    /** @var list<Closure(CanCarryState):CanCarryState> */
    private array $steps = [];
    /** @var list<Closure(CanCarryState):void> */
    private array $failureHandlers = [];
    /** @var list<Closure(CanCarryState):CanCarryState> */
    private array $finalizers = [];

    public function __construct(
        private ErrorStrategy $onError = ErrorStrategy::ContinueWithFailure,
    ) {}

    public function throughAll(callable ...$operations): static {
        foreach ($operations as $operation) {
            $this->through($operation);
        }

        return $this;
    }

    public function through(callable $operation): static {
        $this->steps[] = function (CanCarryState $state) use ($operation): CanCarryState {
            if ($state->isFailure()) {
                return $state;
            }

            return $this->normalize($state, $operation($state->value()), true);
        };

        return $this;
    }

    public function map(callable $operation): static {
        return $this->through($operation);
    }

    public function when(callable $condition, callable $then, ?callable $otherwise = null): static {
        $this->steps[] = function (CanCarryState $state) use ($condition, $then, $otherwise): CanCarryState {
            if ($state->isFailure()) {
                return $state;
            }

            if ($condition($state->value())) {
                return $this->normalize($state, $then($state->value()), false);
            }

            if ($otherwise === null) {
                return $state;
            }

            return $this->normalize($state, $otherwise($state->value()), false);
        };

        return $this;
    }

    public function tap(callable $operation): static {
        $this->steps[] = function (CanCarryState $state) use ($operation): CanCarryState {
            if ($state->isFailure()) {
                return $state;
            }

            $operation($state->value());

            return $state;
        };

        return $this;
    }

    public function filter(callable $condition, string $message = 'Value filter condition failed'): static {
        $this->steps[] = function (CanCarryState $state) use ($condition, $message): CanCarryState {
            if ($state->isFailure()) {
                return $state;
            }

            return match ($condition($state->value())) {
                true => $state,
                false => $state->failWith($message),
            };
        };

        return $this;
    }

    public function onFailure(callable $operation): static {
        $this->failureHandlers[] = $operation(...);

        return $this;
    }

    public function finally(callable $operation): static {
        $this->finalizers[] = function (CanCarryState $state) use ($operation): CanCarryState {
            return $this->normalize($state, $operation($state), false);
        };

        return $this;
    }

    public function create(): Pipeline {
        return new Pipeline(
            steps: $this->steps,
            failureHandlers: $this->failureHandlers,
            finalizers: $this->finalizers,
            onError: $this->onError,
        );
    }

    public function executeWith(CanCarryState $state): PendingExecution {
        return $this->create()->executeWith($state);
    }

    private function normalize(CanCarryState $priorState, mixed $output, bool $failOnNull): CanCarryState {
        if ($output instanceof CanCarryState) {
            return $output->applyTo($priorState);
        }

        if ($output instanceof Result) {
            return $priorState->withResult($output);
        }

        if ($output !== null) {
            return $priorState->withResult(Result::from($output));
        }

        return match ($failOnNull) {
            true => $priorState->failWith(new RuntimeException('Null value encountered')),
            false => $priorState->withResult(Result::success(null)),
        };
    }
}
