<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Broadcasting;

/**
 * Configuration for agent event broadcasting.
 *
 * Use static factory methods for common presets:
 * - BroadcastConfig::minimal() - Status events only, no streaming
 * - BroadcastConfig::standard() - Status + streaming chunks (default)
 * - BroadcastConfig::debug() - All events including continuation trace and tool args
 */
final readonly class BroadcastConfig
{
    public function __construct(
        public bool $includeStreamChunks = true,
        public bool $includeContinuationTrace = false,
        public bool $includeToolArgs = false,
        public int $maxArgLength = 100,
        public bool $autoStatusTracking = true,
    ) {}

    /**
     * Minimal configuration: status tracking only, no streaming.
     * Useful for status indicators without real-time text updates.
     */
    public static function minimal(): self
    {
        return new self(
            includeStreamChunks: false,
            includeContinuationTrace: false,
            includeToolArgs: false,
            autoStatusTracking: true,
        );
    }

    /**
     * Standard configuration: streaming + status tracking.
     * Default for chat applications requiring real-time text display.
     */
    public static function standard(): self
    {
        return new self(
            includeStreamChunks: true,
            includeContinuationTrace: false,
            includeToolArgs: false,
            autoStatusTracking: true,
        );
    }

    /**
     * Debug configuration: all events with full detail.
     * Useful for development, debugging, and observability dashboards.
     */
    public static function debug(): self
    {
        return new self(
            includeStreamChunks: true,
            includeContinuationTrace: true,
            includeToolArgs: true,
            maxArgLength: 500,
            autoStatusTracking: true,
        );
    }
}
