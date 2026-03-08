<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Http\Data\HttpResponse;

/**
 * Typed delta payload parsed from one streaming event.
 */
final readonly class PartialInferenceDelta
{
    public function __construct(
        public string $contentDelta = '',
        public string $reasoningContentDelta = '',
        public ToolCallId|string|null $toolId = null,
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public ?Usage $usage = null,
        public bool $usageIsCumulative = false,
        public ?HttpResponse $responseData = null,
        public mixed $value = null,
    ) {}
}
