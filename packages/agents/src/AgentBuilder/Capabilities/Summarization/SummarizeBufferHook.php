<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Events\MessageBufferSummarized;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

/**
 * Hook that summarizes the message buffer when it exceeds the token limit.
 */
final readonly class SummarizeBufferHook implements Hook
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
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        // Check if buffer token limit is exceeded
        $buffer = $state->store()
            ->section($this->bufferSection)
            ->get()
            ->messages()
            ->toString();

        $tokens = Tokenizer::tokenCount($buffer);
        if ($tokens <= $this->maxBufferTokens) {
            return $state;
        }

        $bufferMessages = $state->store()
            ->section($this->bufferSection)
            ->get()
            ->messages();
        $summaryMessages = $state->store()
            ->section($this->summarySection)
            ->get()
            ->messages();

        $summarizedInput = match (true) {
            $summaryMessages->isEmpty() => $bufferMessages,
            $bufferMessages->isEmpty() => $summaryMessages,
            default => $bufferMessages->appendMessages($summaryMessages),
        };

        $summary = $this->summarizer->summarize($summarizedInput, $this->maxSummaryTokens);

        $this->events->dispatch(new MessageBufferSummarized([
            'summary' => $summary,
            'buffer' => $bufferMessages->toArray(),
        ]));

        $newStore = $state
            ->store()
            ->section($this->bufferSection)->setMessages(Messages::empty())
            ->section($this->summarySection)->setMessages(Messages::fromString($summary));

        return $state->withMessageStore($newStore);
    }
}
