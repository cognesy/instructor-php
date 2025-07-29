<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Utils\Result\Result;

/**
 * Envelope wraps a Result with stamps (metadata) for cross-cutting concerns.
 *
 * The Envelope maintains separation of concerns:
 * - Result: Handles computation success/failure and type-safe payload access
 * - Envelope: Manages metadata, observability, and cross-cutting concerns
 *
 * Key features:
 * - Immutable - every modification creates a new instance
 * - Stamps survive success/failure transitions
 * - Type-safe stamp retrieval
 * - Compatible with Symfony Messenger patterns
 *
 * Example:
 * ```php
 * $envelope = new Envelope(Result::success($data), StampMap::create([
 *     new TraceStamp('trace-123'),
 *     new TimestampStamp(microtime(true))
 * ]));
 *
 * $newEnvelope = $envelope
 *     ->with(new MetricsStamp($duration))
 *     ->without(TimestampStamp::class);
 * ```
 */
final readonly class Envelope
{
    private Result $result;
    private StampMap $stamps;

    /**
     * @param Result $result The computation result (success or failure)
     * @param StampMap|null $stamps Collection of stamps for metadata management
     */
    public function __construct(
        Result $result,
        ?StampMap $stamps = null,
    ) {
        $this->result = $result;
        $this->stamps = $stamps ?? StampMap::empty();
    }

    /**
     * Wrap a payload in an Envelope, adding optional stamps.
     *
     * If the payload is already a Result, it's used directly.
     * Otherwise, it's wrapped in Result::success().
     */
    public static function wrap(mixed $payload, array $stamps = []): self {
        return new self(
            result: Result::from($payload),
            stamps: StampMap::create($stamps)
        );
    }

    /**
     * Get the Result containing the computation.
     */
    public function result(): Result {
        return $this->result;
    }

    public function payload(): mixed {
        return $this->result->unwrap();
    }

    public function isSuccess(): bool {
        return $this->result->isSuccess();
    }

    public function isFailure(): bool {
        return $this->result->isFailure();
    }

    /**
     * Create a new Envelope with a different Result.
     *
     * This preserves all stamps while changing the computation output.
     */
    public function withResult(Result $result): self {
        return new self($result, $this->stamps);
    }

    /**
     * Create a new Envelope with additional stamps.
     *
     * @param StampInterface ...$stamps
     */
    public function with(StampInterface ...$stamps): self {
        return new self($this->result, $this->stamps->with(...$stamps));
    }

    /**
     * Create a new Envelope without stamps of the specified type(s).
     *
     * @param string ...$stampClasses
     */
    public function without(string ...$stampClasses): self {
        return new self($this->result, $this->stamps->without(...$stampClasses));
    }

    /**
     * Get all stamps, optionally filtered by class.
     *
     * @param string|null $stampClass Optional class filter
     * @return StampInterface[]
     */
    public function all(?string $stampClass = null): array {
        return $this->stamps->all($stampClass);
    }

    /**
     * Get the last (most recent) stamp of a specific type.
     *
     * @template T of StampInterface
     * @param class-string<T> $stampClass
     * @return StampInterface|null
     */
    public function last(string $stampClass): ?StampInterface {
        return $this->stamps->last($stampClass);
    }

    /**
     * Get the first stamp of a specific type.
     *
     * @template T of StampInterface
     * @param class-string<T> $stampClass
     * @return StampInterface|null
     */
    public function first(string $stampClass): ?StampInterface {
        return $this->stamps->first($stampClass);
    }

    /**
     * Check if the envelope has stamps of a specific type.
     */
    public function has(string $stampClass): bool {
        return $this->stamps->has($stampClass);
    }

    /**
     * Get count of stamps of a specific type.
     */
    public function count(?string $stampClass = null): int {
        return $this->stamps->count($stampClass);
    }

}