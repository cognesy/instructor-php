<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Pipeline\Contracts\CanProcessState;
use Cognesy\Utils\Result\Result;
use Generator;
use RuntimeException;
use Throwable;

/**
 * PendingExecution supports ProcessingState-aware operations.
 *
 * This class extends the lazy evaluation pattern to work with ProcessingState objects,
 * providing multiple ways to extract results while preserving tags and metadata.
 */
class PendingExecution
{
    private ProcessingState $initialState;
    private CanProcessState $pipeline;

    private ?ProcessingState $cachedOutput = null;

    public function __construct(
        ProcessingState $initialState,
        CanProcessState $pipeline,
    ) {
        $this->initialState = $initialState;
        $this->pipeline = $pipeline;
    }

    public function for(mixed $value, array $tags = []): self {
        $this->initialState = ProcessingState::with($value, $tags);
        $this->cachedOutput = null; // Reset cached output
        return $this;
    }

    /**
     * @param iterable<mixed> $inputs
     * @return Generator<PendingExecution>
     */
    public function each(iterable $inputs, array $tags = []): Generator {
        foreach ($inputs as $item) {
            yield $this->for($item, $tags);
        }
    }

    public function execute(): ProcessingState {
        return $this->executeOnce($this->initialState, $this->pipeline);
    }

    public function state(): ProcessingState {
        return $this->execute();
    }

    public function result(): Result {
        return $this->execute()->result();
    }

    public function value(): mixed {
        $state = $this->execute();
        return match(true) {
            $state->isFailure() => throw new RuntimeException(
                'Cannot extract value from a failed state: ' . $state->result()->errorMessage()
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
        return $this->execute()->exceptionOr(null);
    }

    // INTERNAL ////////////////////////////////////////////////////

    private function executeOnce(ProcessingState $state, CanProcessState $pipeline): ProcessingState {
        if (is_null($this->cachedOutput)) {
            $this->cachedOutput = $pipeline->process($state);
        }
        return $this->cachedOutput;
    }
}