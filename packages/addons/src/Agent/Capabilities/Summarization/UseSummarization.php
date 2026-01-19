<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities\Summarization;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Utils\SummarizeMessages;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\MoveMessagesToBuffer;
use Cognesy\Addons\StepByStep\StateProcessing\Processors\SummarizeBuffer;

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

        $builder->addPreProcessor(new SummarizeBuffer(
            maxBufferTokens: $policy->maxBufferTokens,
            maxSummaryTokens: $policy->maxSummaryTokens,
            bufferSection: $policy->bufferSection,
            summarySection: $policy->summarySection,
            summarizer: $summarizer,
        ));

        $builder->addPreProcessor(new MoveMessagesToBuffer(
            maxTokens: $policy->maxMessageTokens,
            bufferSection: $policy->bufferSection,
        ));
    }
}
