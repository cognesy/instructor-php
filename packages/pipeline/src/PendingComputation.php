<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Closure;
use Cognesy\Utils\Result\Result;
use Generator;

/**
 * Extended PendingExecution that supports Computation-aware operations.
 *
 * This class extends the lazy evaluation pattern to work with Computation objects,
 * providing multiple ways to extract results while preserving tags and metadata.
 *
 * Supports all original PendingExecution operations plus:
 * - computation() for full Computation with tags
 * - Computation-aware transformations
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
     *
     * Extracts the value from Result and ignores all tags.
     */
    public function value(): mixed {
        $output = $this->executeOnce();
        return match(true) {
            $output instanceof Computation => $output->result()->valueOr(null),
            $output instanceof Result => $output->valueOr(null),
            default => $output,
        };
    }

    /**
     * Execute and return the Result object.
     *
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
     *
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
    public function success(): bool {
        try {
            $output = $this->executeOnce();
            return match(true) {
                $output instanceof Computation => $output->result()->isSuccess(),
                $output instanceof Result => $output->isSuccess(),
                default => $output !== null,
            };
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Execute and return the failure reason if computation failed.
     */
    public function failure(): mixed {
        try {
            $output = $this->executeOnce();
            return match(true) {
                $output instanceof Computation && $output->isFailure() => $output->result()->error(),
                $output instanceof Result && $output->isFailure() => $output->error(),
                default => null,
            };
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * Transform the pending execution with a callback that receives the Computation.
     *
     * Returns a new MessagePendingExecution that applies the transformation
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
            $this->cachedOutput = ($this->deferred)();
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