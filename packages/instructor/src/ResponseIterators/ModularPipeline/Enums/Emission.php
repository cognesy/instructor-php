<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums;

/**
 * Explicit emission decision for a PartialFrame.
 *
 * Replaces boolean flags with exhaustive cases, making control flow
 * self-documenting and eliminating implicit branching.
 */
enum Emission
{
    /**
     * Do not emit this frame (no changes, intermediate state).
     */
    case None;

    /**
     * Object is ready and changed (emit PartialResponseGenerated event).
     */
    case ObjectReady;

    /**
     * Stream finished but no object (emit StreamedResponseReceived only).
     */
    case FinishOnly;

    /**
     * Driver provided value directly (emit with driver value).
     */
    case DriverValue;

    public function shouldEmit(): bool {
        return $this !== self::None;
    }

    public function reason(): string {
        return match ($this) {
            self::None => 'skipped',
            self::ObjectReady => 'object_ready',
            self::FinishOnly => 'finish_only',
            self::DriverValue => 'driver_value',
        };
    }
}
