<?php

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;

/**
 * Example 3: Minimal custom extractor template.
 *
 * Copy this class and implement your parsing rules.
 */
class MyCustomExtractor implements CanExtractResponse
{
    public function extract(ExtractionInput $input): array
    {
        // Your extraction logic here
        // Return an array on success
        // Throw ExtractionException on failure
        throw new ExtractionException('Not implemented');
    }

    public function name(): string
    {
        return 'my_custom';
    }
}
