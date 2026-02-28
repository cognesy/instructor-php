<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\Enums;

/**
 * Explicit emission decision for a PartialFrame.
 *
 * Replaces boolean flags with exhaustive cases, making control flow
 * self-documenting and eliminating implicit branching.
 */
enum EmissionType
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
     * Driver provided value directly (emit with driver value).
     */
    case DriverValue;
}
