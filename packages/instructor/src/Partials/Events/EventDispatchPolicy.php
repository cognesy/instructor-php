<?php declare(strict_types=1);

namespace Cognesy\Instructor\Partials\Events;

use Closure;

/**
 * Event dispatching policy for stream decoration.
 * Configures error handling, batching, and filtering.
 */
final readonly class EventDispatchPolicy
{
    public function __construct(
        public EventDispatchMode $mode = EventDispatchMode::Strict,
        public int $batchSize = 1,                    // Batch events before dispatch
        /** @var ?Closure(\Throwable): void $onError */
        public ?Closure $onError = null,              // Error handler
        /** @var ?Closure(object): bool $filter */
        public ?Closure $filter = null,               // Event filter predicate
    ) {}

    public static function strict(): self {
        return new self(mode: EventDispatchMode::Strict);
    }

    public static function lenient(): self {
        return new self(
            mode: EventDispatchMode::Lenient,
            onError: function(\Throwable $e): void {
                error_log('Event dispatch failed: ' . $e->getMessage());
            },
        );
    }

    public static function batched(int $size): self {
        return new self(batchSize: $size);
    }

    public static function silent(): self {
        return new self(mode: EventDispatchMode::Silent);
    }
}
