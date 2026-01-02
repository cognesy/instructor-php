<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Extraction;

use Cognesy\Instructor\Events\StructuredOutputEvent;
use Psr\Log\LogLevel;

/**
 * Dispatched when all extraction strategies fail.
 *
 * Data includes:
 * - strategies_tried: List of strategy names that were attempted
 * - errors: Map of strategy name to error message
 */
final class ExtractionFailed extends StructuredOutputEvent
{
    public string $logLevel = LogLevel::WARNING;
}
