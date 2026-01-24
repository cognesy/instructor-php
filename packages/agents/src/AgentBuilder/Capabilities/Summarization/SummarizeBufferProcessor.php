<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\Agent\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Events\MessageBufferSummarized;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

/**
 * Summarizes the message buffer when it exceeds the token limit.
 */
final readonly class SummarizeBufferProcessor implements CanProcessAgentState
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxBufferTokens,
        private int $maxSummaryTokens,
        private string $bufferSection,
        private string $summarySection,
        private CanSummarizeMessages $summarizer,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $events ?? EventBusResolver::using($events);
    }

    #[\Override]
    public function canProcess(AgentState $state): bool {
        $buffer = $state->store()
            ->section($this->bufferSection)
            ->get()
            ->messages()
            ->toString();

        $tokens = Tokenizer::tokenCount($buffer);
        return $tokens > $this->maxBufferTokens;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        $buffer = $newState->store()
            ->section($this->bufferSection)
            ->get()
            ->messages();
        $summaryMessages = $newState->store()
            ->section($this->summarySection)
            ->get()
            ->messages();

        $summarizedInput = match (true) {
            $summaryMessages->isEmpty() => $buffer,
            $buffer->isEmpty() => $summaryMessages,
            default => $buffer->appendMessages($summaryMessages),
        };

        $summary = $this->summarizer->summarize($summarizedInput, $this->maxSummaryTokens);

        $this->events->dispatch(new MessageBufferSummarized([
            'summary' => $summary,
            'buffer' => $buffer->toArray(),
        ]));

        $newStore = $newState
            ->store()
            ->section($this->bufferSection)->setMessages(Messages::empty())
            ->section($this->summarySection)->setMessages(Messages::fromString($summary));

        return $newState->withMessageStore($newStore);
    }
}
