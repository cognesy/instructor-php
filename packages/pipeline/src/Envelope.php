<?php declare(strict_types=1);

namespace Cognesy\Pipeline;

use Cognesy\Utils\Arrays;
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
 * $envelope = new Envelope(Result::success($data), [
 *     new TraceStamp('trace-123'),
 *     new TimestampStamp(microtime(true))
 * ]);
 *
 * $newEnvelope = $envelope
 *     ->with(new MetricsStamp($duration))
 *     ->withoutAll(TimestampStamp::class);
 * ```
 */
final readonly class Envelope
{
    /**
     * @param Result $payload The computation result (success or failure)
     * @param StampInterface[] $stamps Stamps indexed by class name
     */
    public function __construct(
        private Result $payload,
        private array $stamps = [],
    ) {}

    /**
     * Wrap a message in an Envelope, adding optional stamps.
     *
     * If the message is already a Result, it's used directly.
     * Otherwise, it's wrapped in Result::success().
     */
    public static function wrap(mixed $message, array $stamps = []): self {
        $result = $message instanceof Result ? $message : Result::success($message);
        return new self($result, self::indexStamps($stamps));
    }

    /**
     * Get the Result containing the computation.
     */
    public function getResult(): Result {
        return $this->payload;
    }

    /**
     * Create a new Envelope with additional stamps.
     *
     * @param StampInterface ...$stamps
     */
    public function with(StampInterface ...$stamps): self {
        $newStamps = $this->stamps;
        foreach ($stamps as $stamp) {
            $class = $stamp::class;
            $newStamps[$class] = $newStamps[$class] ?? [];
            $newStamps[$class][] = $stamp;
        }
        return new self($this->payload, $newStamps);
    }

    /**
     * Create a new Envelope with a different Result message.
     *
     * This preserves all stamps while changing the computation result.
     */
    public function withMessage(Result $message): self {
        return new self($message, $this->stamps);
    }

    /**
     * Create a new Envelope without stamps of the specified type(s).
     *
     * @param string ...$stampClasses
     */
    public function without(string ...$stampClasses): self {
        $newStamps = $this->stamps;
        foreach ($stampClasses as $class) {
            unset($newStamps[$class]);
        }
        return new self($this->payload, $newStamps);
    }

    /**
     * Get all stamps, optionally filtered by class.
     *
     * @param string|null $stampClass Optional class filter
     * @return StampInterface[]|StampInterface
     */
    public function all(?string $stampClass = null): array {
        return match(true) {
            $stampClass === null => Arrays::flatten($this->stamps),
            default => $this->stamps[$stampClass] ?? [],
        };
    }

    /**
     * Get the last (most recent) stamp of a specific type.
     *
     * @template T of StampInterface
     * @param class-string<T> $stampClass
     * @return StampInterface|null
     */
    public function last(string $stampClass): ?StampInterface {
        $stamps = $this->stamps[$stampClass] ?? [];
        return empty($stamps) ? null : end($stamps);
    }

    /**
     * Get the first stamp of a specific type.
     *
     * @template T of StampInterface
     * @param class-string<T> $stampClass
     * @return StampInterface|null
     */
    public function first(string $stampClass): ?StampInterface {
        $stamps = $this->stamps[$stampClass] ?? [];
        return empty($stamps) ? null : reset($stamps);
    }

    /**
     * Check if the envelope has stamps of a specific type.
     */
    public function has(string $stampClass): bool {
        return !empty($this->stamps[$stampClass]);
    }

    /**
     * Get count of stamps of a specific type.
     */
    public function count(?string $stampClass = null): int {
        if ($stampClass === null) {
            return array_sum(array_map('count', $this->stamps));
        }

        return count($this->stamps[$stampClass] ?? []);
    }

    /**
     * Index stamps by their class name for efficient retrieval.
     *
     * @param StampInterface[] $stamps
     * @return StampInterface
     */
    private static function indexStamps(array $stamps): array {
        $indexed = [];

        foreach ($stamps as $stamp) {
            $class = $stamp::class;
            $indexed[$class] = $indexed[$class] ?? [];
            $indexed[$class][] = $stamp;
        }

        return $indexed;
    }
}