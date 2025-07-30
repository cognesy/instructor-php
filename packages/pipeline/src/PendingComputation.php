<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Utils\Result\Result;
use Generator;
use Throwable;

/**
 * PendingComputation supports Computation-aware operations.
 *
 * This class extends the lazy evaluation pattern to work with Computation objects,
 * providing multiple ways to extract results while preserving tags and metadata.
 */
class PendingComputation
{
    private Closure $deferred;
    private bool $executed = false;
    private mixed $cachedOutput = null;

    public function __construct(callable $deferred) {
        $this->deferred = $deferred;
    }

    /**
     * Execute and return the raw, unwrapped value.
     * Extracts the value from Result and ignores all tags.
     */
    public function value(): mixed {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation => $output->valueOr(null),
            $output instanceof Result => $output->valueOr(null),
            default => $output,
        };
    }

    /**
     * Execute and return the Result object.
     * Extracts the Result from Computation, ignoring tags.
     */
    public function result(): Result {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation => $output->result(),
            $output instanceof Result => $output,
            default => Result::success($output),
        };
    }

    /**
     * Execute and return the full Computation with tags.
     * This preserves all metadata and cross-cutting concerns.
     */
    public function computation(): Computation {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation => $output,
            default => Computation::wrap($output),
        };
    }

    /**
     * Execute and return boolean indicating success.
     */
    public function isSuccess(): bool {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation => $output->isSuccess(),
            $output instanceof Result => $output->isSuccess(),
            default => ($output !== null),
        };
    }

    /**
     * Execute and return the failure reason if computation failed.
     */
    public function exception(): ?Throwable {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation && $output->isFailure() => $output->result()->exception(),
            $output instanceof Result && $output->isFailure() => $output->exception(),
            default => null,
        };
    }

    /**
     * Transform the pending execution with a callback that receives the Computation.
     * Returns a new PendingComputation that applies the transformation
     * when executed.
     */
    public function mapComputation(callable $transformer): self {
        return new self(function () use ($transformer) {
            $output = $this->executeOnce();
            return match(true) {
                $output instanceof Computation => $transformer($output),
                default => $transformer(Computation::wrap($output)),
            };
        });
    }

    /**
     * Transform the pending execution with a callback that receives the raw value.
     *
     * Preserves the computation structure and tags.
     */
    public function map(callable $transformer): self {
        return new self(function () use ($transformer) {
            $output = $this->executeOnce();
            return $this->applyToValue($output, $transformer);
        });
    }

    /**
     * Chain another computation after this one.
     *
     * The next computation receives the unwrapped value, preserving computation.
     */
    public function then(callable $next): self {
        return new self(function () use ($next) {
            $output = $this->executeOnce();
            return $this->applyToValue($output, $next);
        });
    }

    /**
     * Execute and return as a stream/generator.
     *
     * Streams the unwrapped data, ignoring computation metadata.
     */
    public function stream(): Generator {
        $output = $this->executeOnce();
        $result = $this->getResultFromOutput($output);
        if ($result->isFailure()) {
            return;
        }
        $value = $result->unwrap();
        if (is_iterable($value)) {
            foreach ($value as $item) {
                yield $item;
            }
        } else {
            yield $value;
        }
    }

    // INTERNAL ////////////////////////////////////////////////////

    /**
     * Execute the computation only once, caching the result.
     */
    private function executeOnce(): mixed {
        if (!$this->executed) {
            try {
                $this->cachedOutput = ($this->deferred)();
            } catch (Throwable $e) {
                $this->cachedOutput = Result::failure($e);
            }
            $this->executed = true;
        }
        return $this->cachedOutput;
    }

    private function getResultFromOutput(mixed $output): Result {
        return match (true) {
            $output instanceof Computation => $output->result(),
            $output instanceof Result => $output,
            default => Result::success($output),
        };
    }

    private function applyToValue(mixed $output, callable $mapper) {
        return match(true) {
            $output instanceof Computation && $output->isFailure() => $output,
            $output instanceof Result && $output->isFailure() => $output,
            $output instanceof Computation => $output->withResult(Result::from($mapper($output->result()->unwrap()))),
            $output instanceof Result => Result::from($mapper($output->unwrap())),
            default => $mapper($output),
        };
    }
}