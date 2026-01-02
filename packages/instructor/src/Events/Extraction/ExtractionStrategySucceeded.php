<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Extraction;

use Cognesy\Instructor\Events\StructuredOutputEvent;

/**
 * Dispatched when a specific extraction strategy succeeds.
 *
 * Data includes:
 * - strategy: Name of the successful strategy
 * - content_length: Length of extracted content
 */
final class ExtractionStrategySucceeded extends StructuredOutputEvent {}
