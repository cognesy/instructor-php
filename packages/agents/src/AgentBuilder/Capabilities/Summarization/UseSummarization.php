<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Utils\SummarizeMessages;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

class UseSummarization implements AgentCapability
{
    public function __construct(
        private ?SummarizationPolicy $policy = null,
        private ?CanSummarizeMessages $summarizer = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $policy = $this->policy ?? new SummarizationPolicy();
        $summarizer = $this->summarizer ?? new SummarizeMessages();

        $builder->addPreProcessor(new SummarizeBufferProcessor(
            maxBufferTokens: $policy->maxBufferTokens,
            maxSummaryTokens: $policy->maxSummaryTokens,
            bufferSection: $policy->bufferSection,
            summarySection: $policy->summarySection,
            summarizer: $summarizer,
        ));

        $builder->addPreProcessor(new MoveMessagesToBufferProcessor(
            maxTokens: $policy->maxMessageTokens,
            bufferSection: $policy->bufferSection,
        ));
    }
}
