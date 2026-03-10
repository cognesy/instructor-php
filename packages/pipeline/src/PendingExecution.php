<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\StateContracts\CanCarryState;
use Cognesy\Utils\Result\Result;
use Generator;
use RuntimeException;
use Throwable;

/**
 * PendingExecution supports CanCarryState-aware operations.
 *
 * This class extends the lazy evaluation pattern to work with CanCarryState objects,
 * providing multiple ways to extract results while preserving tags and metadata.
 */
class PendingExecution
{
    private ?CanCarryState $cachedOutput = null;

    public function __construct(
        private CanCarryState $initialState,
        private Pipeline $pipeline,
    ) {}

    public function for(mixed $value, array $tags = []): self {
        $this->initialState = $this->initialState
            ->withResult(Result::from($value))
            ->replaceTags(...$tags);
        $this->cachedOutput = null;

        return $this;
    }

    /**
     * @param iterable<mixed> $inputs
     * @return Generator<PendingExecution>
     */
    public function each(iterable $inputs, array $tags = []): Generator {
        foreach ($inputs as $item) {
            yield (new self($this->initialState, $this->pipeline))
                ->for($item, $tags);
        }
    }

    public function execute(): CanCarryState {
        return $this->cachedOutput ??= $this->pipeline->process($this->initialState);
    }

    public function state(): CanCarryState {
        return $this->execute();
    }

    public function result(): Result {
        return $this->execute()->result();
    }

    public function value(): mixed {
        $state = $this->execute();
        return match(true) {
            $state->isFailure() => throw new RuntimeException(
                'Cannot extract value from a failed state: ' . (string) $state->result()->error()
            ),
            default => $state->result()->unwrap(),
        };
    }

    public function valueOr(mixed $default): mixed {
        return $this->execute()->valueOr($default);
    }

    /**
     * @return Generator<mixed>
     */
    public function stream(): Generator {
        $state = $this->execute();
        if ($state->isFailure()) {
            return;
        }
        $value = $state->valueOr(null);
        yield from match (true) {
            is_iterable($value) => $value,
            default => [$value],
        };
    }

    public function isSuccess(): bool {
        return $this->execute()->isSuccess();
    }

    public function isFailure(): bool {
        return $this->execute()->isFailure();
    }

    public function exception(): ?Throwable {
        $exception = $this->execute()->exceptionOr(null);

        return match (true) {
            $exception instanceof Throwable => $exception,
            default => null,
        };
    }

}
