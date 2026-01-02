<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Extraction;

use Cognesy\Instructor\Events\StructuredOutputEvent;

/**
 * Dispatched when a specific extraction strategy is being attempted.
 *
 * Data includes:
 * - strategy: Name of the strategy being tried
 * - attempt_index: Index of this attempt in the strategy chain
 */
final class ExtractionStrategyAttempted extends StructuredOutputEvent {}
