<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Extraction;

use Cognesy\Instructor\Events\StructuredOutputEvent;

/**
 * Dispatched when a specific extraction strategy fails.
 *
 * Data includes:
 * - strategy: Name of the failed strategy
 * - error: Error message describing why it failed
 */
final class ExtractionStrategyFailed extends StructuredOutputEvent {}
