<?php declare(strict_types=1);

namespace Cognesy\Http\Extras\Support\RecordReplay;

use Cognesy\Http\Extras\Support\RecordReplay\Redaction\RequestRedactor;
use Cognesy\Http\Extras\Support\RecordReplay\Redaction\ResponseRedactor;

/**
 * Sanitizes the request and response projections persisted in a cassette.
 *
 * Stream-aware transformation keeps credential matching correct across chunk
 * boundaries without requiring a complete streamed body in a PHP array.
 */
interface FixtureSanitizer extends RequestRedactor, ResponseRedactor
{
    /**
     * @param iterable<string> $chunks
     * @return iterable<string>
     */
    public function redactStream(iterable $chunks): iterable;
}
