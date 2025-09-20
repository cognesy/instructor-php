<?php declare(strict_types=1);

namespace Cognesy\Addons\Core\Processors;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\MessageBufferSummarized;
use Cognesy\Addons\Core\Contracts\CanProcessAnyState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Messages;
use Cognesy\Utils\Tokenizer;

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

    public function canProcess(object $state): bool
    {
        return $state instanceof ChatState;
    }

    public function process(object $state, ?callable $next = null): ChatState
    {
        $buffer = $state->store()->section($this->bufferSection)->get()?->messages() ?? Messages::empty();
        if (!$this->shouldProcess($buffer->toString())) {
            return $next ? $next($state) : $state;
        }

        $summary = $this->summarizer->summarize($buffer, $this->maxSummaryTokens);
        $this->events->dispatch(new MessageBufferSummarized([
            'summary' => $summary,
            'buffer' => $buffer->toArray(),
        ]));
        $newStore = $state->store()
            ->section($this->bufferSection)->setMessages(Messages::empty())
            ->section($this->summarySection)->setMessages(Messages::fromString($summary));
        $newState = $state->withMessageStore($newStore);

        return $next ? $next($newState) : $newState;
    }

    private function shouldProcess(string $buffer): bool
    {
        $tokens = Tokenizer::tokenCount($buffer);
        return $tokens > $this->maxBufferTokens;
    }
}
