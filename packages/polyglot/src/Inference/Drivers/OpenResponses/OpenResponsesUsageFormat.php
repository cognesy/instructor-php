<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Polyglot\Inference\Contracts\CanMapUsage;
use Cognesy\Polyglot\Inference\Data\Usage;

/**
 * Extracts usage information from OpenResponses API responses.
 *
 * OpenResponses uses the same usage format as Chat Completions:
 * - prompt_tokens → inputTokens
 * - completion_tokens → outputTokens
 * - prompt_tokens_details.cached_tokens → cacheReadTokens
 * - completion_tokens_details.reasoning_tokens → reasoningTokens
 */
class OpenResponsesUsageFormat implements CanMapUsage
{
    #[\Override]
    public function fromData(array $data): Usage {
        $usage = $data['usage'] ?? ($data['response']['usage'] ?? []);

        // Handle both nested and flat usage structures
        $promptTokens = $usage['prompt_tokens'] ?? $usage['input_tokens'] ?? 0;
        $completionTokens = $usage['completion_tokens'] ?? $usage['output_tokens'] ?? 0;

        // Get detailed token counts if available
        $promptDetails = $usage['prompt_tokens_details'] ?? $usage['input_tokens_details'] ?? [];
        $completionDetails = $usage['completion_tokens_details'] ?? $usage['output_tokens_details'] ?? [];

        $cacheReadTokens = $promptDetails['cached_tokens'] ?? 0;
        $reasoningTokens = $completionDetails['reasoning_tokens'] ?? 0;

        return new Usage(
            inputTokens: (int) $promptTokens,
            outputTokens: (int) $completionTokens,
            cacheWriteTokens: 0,
            cacheReadTokens: (int) $cacheReadTokens,
            reasoningTokens: (int) $reasoningTokens,
        );
    }
}
