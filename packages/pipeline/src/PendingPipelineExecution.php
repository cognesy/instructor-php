<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Utils\Result\Result;
use Generator;

/**
 * Extended PendingExecution that supports Envelope-aware operations.
 *
 * This class extends the lazy evaluation pattern to work with Envelope objects,
 * providing multiple ways to extract results while preserving stamps and metadata.
 *
 * Supports all original PendingExecution operations plus:
 * - envelope() for full Envelope with stamps
 * - Envelope-aware transformations
 */
class PendingPipelineExecution
{
    private mixed $computation;
    private bool $executed = false;
    private mixed $cachedResult = null;

    public function __construct(callable $computation) {
        $this->computation = $computation;
    }

    /**
     * Execute and return the raw, unwrapped value.
     *
     * Extracts the payload from Result and ignores all stamps.
     */
    public function value(): mixed {
        $envelope = $this->executeOnce();
        if ($envelope instanceof Envelope) {
            $result = $envelope->getResult();
            return $result->isSuccess() ? $result->unwrap() : null;
        }
        // Fallback for non-envelope results
        if ($envelope instanceof Result) {
            return $envelope->isSuccess() ? $envelope->unwrap() : null;
        }
        return $envelope;
    }

    /**
     * Execute and return the Result object.
     *
     * Extracts the Result from Envelope, ignoring stamps.
     */
    public function result(): Result {
        $envelope = $this->executeOnce();
        if ($envelope instanceof Envelope) {
            return $envelope->getResult();
        }
        // Fallback for non-envelope results
        if ($envelope instanceof Result) {
            return $envelope;
        }
        return Result::success($envelope);
    }

    /**
     * Execute and return the full Envelope with stamps.
     *
     * This preserves all metadata and cross-cutting concerns.
     */
    public function envelope(): Envelope {
        $result = $this->executeOnce();
        if ($result instanceof Envelope) {
            return $result;
        }
        // Wrap non-envelope results
        return Envelope::wrap($result);
    }

    /**
     * Execute and return as a stream/generator.
     *
     * Streams the unwrapped values, ignoring envelope metadata.
     */
    public function stream(): Generator {
        $envelope = $this->executeOnce();
        $result = $this->getResultFromEnvelope($envelope);
        // Handle failure
        if ($result->isFailure()) {
            return;
        }
        $value = $result->unwrap();
        // Handle iterable results
        if (is_iterable($value)) {
            foreach ($value as $item) {
                yield $item;
            }
        } else {
            yield $value;
        }
    }

    /**
     * Execute and return boolean indicating success.
     */
    public function success(): bool {
        try {
            $envelope = $this->executeOnce();
            if ($envelope instanceof Envelope) {
                return $envelope->getResult()->isSuccess();
            }
            if ($envelope instanceof Result) {
                return $envelope->isSuccess();
            }
            return $envelope !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Execute and return the failure reason if computation failed.
     */
    public function failure(): mixed {
        try {
            $envelope = $this->executeOnce();
            if ($envelope instanceof Envelope) {
                $result = $envelope->getResult();
                return $result->isFailure() ? $result->error() : null;
            }
            if ($envelope instanceof Result && $envelope->isFailure()) {
                return $envelope->error();
            }
            return null;
        } catch (\Throwable $e) {
            return $e;
        }
    }

    /**
     * Transform the pending execution with a callback that receives the Envelope.
     *
     * Returns a new MessagePendingExecution that applies the transformation
     * when executed.
     */
    public function mapEnvelope(callable $transformer): self {
        return new self(function () use ($transformer) {
            $envelope = $this->executeOnce();
            // Ensure we have an Envelope
            if (!$envelope instanceof Envelope) {
                $envelope = Envelope::wrap($envelope);
            }
            return $transformer($envelope);
        });
    }

    /**
     * Transform the pending execution with a callback that receives the raw value.
     *
     * Preserves the envelope structure and stamps.
     */
    public function map(callable $transformer): self {
        return new self(function () use ($transformer) {
            $envelope = $this->executeOnce();
            if ($envelope instanceof Envelope) {
                $result = $envelope->getResult();
                if ($result->isFailure()) {
                    return $envelope; // Preserve failure
                }
                $newValue = $transformer($result->unwrap());
                $newResult = $newValue instanceof Result ? $newValue : Result::success($newValue);
                return $envelope->withMessage($newResult);
            }
            // Fallback for non-envelope results
            if ($envelope instanceof Result) {
                if ($envelope->isFailure()) {
                    return $envelope;
                }
                $newValue = $transformer($envelope->unwrap());
                return $newValue instanceof Result ? $newValue : Result::success($newValue);
            }
            return $transformer($envelope);
        });
    }

    /**
     * Chain another computation after this one.
     *
     * The next computation receives the unwrapped value, preserving envelope.
     */
    public function then(callable $next): self {
        return new self(function () use ($next) {
            $envelope = $this->executeOnce();
            if ($envelope instanceof Envelope) {
                $result = $envelope->getResult();
                if ($result->isFailure()) {
                    return $envelope; // Short-circuit on failure
                }
                $nextResult = $next($result->unwrap());
                $newResult = $nextResult instanceof Result ? $nextResult : Result::success($nextResult);
                return $envelope->withMessage($newResult);
            }
            // Fallback handling
            if ($envelope instanceof Result) {
                if ($envelope->isFailure()) {
                    return $envelope;
                }
                return $next($envelope->unwrap());
            }
            return $next($envelope);
        });
    }

    // INTERNAL ////////////////////////////////////////////////////

    /**
     * Execute the computation only once, caching the result.
     */
    private function executeOnce(): mixed {
        if (!$this->executed) {
            $computation = $this->computation;
            $this->cachedResult = $computation();
            $this->executed = true;
        }
        return $this->cachedResult;
    }

    private function getResultFromEnvelope(mixed $envelope): Result {
        return match (true) {
            $envelope instanceof Envelope => $envelope->getResult(),
            $envelope instanceof Result => $envelope,
            default => Result::success($envelope),
        };
    }
}