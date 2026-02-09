<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\Context\ContextSections;

final readonly class SummarizationPolicy
{
    public function __construct(
        public int $maxMessageTokens = 4096,
        public int $maxBufferTokens = 8192,
        public int $maxSummaryTokens = 512,
        public string $bufferSection = ContextSections::BUFFER,
        public string $summarySection = ContextSections::SUMMARY,
    ) {}
}
