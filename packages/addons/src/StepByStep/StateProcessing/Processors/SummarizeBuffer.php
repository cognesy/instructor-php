<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Events\MessageBufferSummarized;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

/**
 * @implements CanProcessAnyState<object>
 */
final readonly class SummarizeBuffer implements CanProcessAnyState
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
    public function canProcess(object $state): bool {
        return $state instanceof HasMessageStore;
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof HasMessageStore);

        $buffer = $newState->store()
            ->section($this->bufferSection)
            ->get()
            ->messages();
        $summaryMessages = $newState->store()
            ->section($this->summarySection)
            ->get()
            ->messages();

        if (!$this->shouldProcess($buffer->toString())) {
            return $newState;
        }

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

    private function shouldProcess(string $buffer): bool {
        $tokens = Tokenizer::tokenCount($buffer);
        return $tokens > $this->maxBufferTokens;
    }
}
