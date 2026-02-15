<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization;

use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Capability\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\Capability\Summarization\Utils\SummarizeMessages;
use Cognesy\Agents\Hook\Collections\HookTriggers;

class UseSummarization implements CanProvideAgentCapability
{
    public function __construct(
        private ?SummarizationPolicy $policy = null,
        private ?CanSummarizeMessages $summarizer = null,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_summarization';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $policy = $this->policy ?? new SummarizationPolicy();
        $summarizer = $this->summarizer ?? new SummarizeMessages();
        $hooks = $agent->hooks();

        // These hooks must run AFTER message hooks (priority -100..-130)
        // which append step output to state messages / execution buffer.
        // Lower priority = runs later in the AfterStep phase.

        // MoveMessagesToBuffer should run first (after messages are appended)
        $hooks = $hooks->with(
            hook: new MoveMessagesToBufferHook(
                maxTokens: $policy->maxMessageTokens,
                bufferSection: $policy->bufferSection,
            ),
            triggerTypes: HookTriggers::afterStep(),
            priority: -200,
        );

        // SummarizeBuffer should run after moving messages
        $hooks = $hooks->with(
            hook: new SummarizeBufferHook(
                maxBufferTokens: $policy->maxBufferTokens,
                maxSummaryTokens: $policy->maxSummaryTokens,
                bufferSection: $policy->bufferSection,
                summarySection: $policy->summarySection,
                summarizer: $summarizer,
            ),
            triggerTypes: HookTriggers::afterStep(),
            priority: -210,
        );
        return $agent->withHooks($hooks);
    }
}
