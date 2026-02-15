<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization;

use Cognesy\Agents\Capability\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Agents\Capability\Summarization\Events\MessageBufferSummarized;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

/**
 * Hook that summarizes the message buffer when it exceeds the token limit.
 */
final readonly class SummarizeBufferHook implements HookInterface
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
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        // Check if buffer token limit is exceeded
        $buffer = $state->store()
            ->section($this->bufferSection)
            ->get()
            ->messages()
            ->toString();

        $tokens = Tokenizer::tokenCount($buffer);
        if ($tokens <= $this->maxBufferTokens) {
            return $context;
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
            default => $summaryMessages->appendMessages($bufferMessages),
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

        return $context->withState($state->withMessageStore($newStore));
    }
}
