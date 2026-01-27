<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\AgentHooks\Enums\HookType;
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

        // These hooks must run AFTER AppendStepMessagesHook (priority -100)
        // which appends the step output to state.messages().
        // Lower priority = runs later in the AfterStep phase.

        // MoveMessagesToBuffer should run first (after messages are appended)
        $builder->addHook(HookType::AfterStep, new MoveMessagesToBufferHook(
            maxTokens: $policy->maxMessageTokens,
            bufferSection: $policy->bufferSection,
        ), priority: -200);

        // SummarizeBuffer should run after moving messages
        $builder->addHook(HookType::AfterStep, new SummarizeBufferHook(
            maxBufferTokens: $policy->maxBufferTokens,
            maxSummaryTokens: $policy->maxSummaryTokens,
            bufferSection: $policy->bufferSection,
            summarySection: $policy->summarySection,
            summarizer: $summarizer,
        ), priority: -210);
    }
}
