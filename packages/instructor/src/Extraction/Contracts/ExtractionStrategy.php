<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Contracts;

use Cognesy\Utils\Result\Result;

/**
 * Contract for JSON extraction strategies.
 *
 * Each strategy implements a specific approach to extracting JSON
 * from raw content (direct parsing, markdown blocks, bracket matching, etc.).
 *
 * Strategies are composable - JsonResponseExtractor uses a chain of strategies
 * with fallback behavior, trying each until one succeeds.
 */
interface ExtractionStrategy
{
    /**
     * Attempt to extract JSON from the given content.
     *
     * @param string $content Raw content that may contain JSON
     * @return Result<string, string> Success with extracted JSON string, or Failure with reason
     */
    public function extract(string $content): Result;

    /**
     * Strategy name for debugging and logging.
     */
    public function name(): string;
}
