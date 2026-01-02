<?php declare(strict_types=1);

namespace Cognesy\Instructor\Events\Extraction;

use Cognesy\Instructor\Events\StructuredOutputEvent;

/**
 * Dispatched when extraction pipeline begins processing content.
 */
final class ExtractionStarted extends StructuredOutputEvent {}
