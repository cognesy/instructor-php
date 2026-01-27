<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Agents\Agent\StateProcessing\CanProcessAgentState;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Events\MessagesMovedToBuffer;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Utils\SplitMessages;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Utils\Tokenizer;

/**
 * Moves overflow messages to a buffer section when token limit is exceeded.
 */
final readonly class MoveMessagesToBufferProcessor implements CanProcessAgentState
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxTokens,
        private string $bufferSection,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $events ?? EventBusResolver::using($events);
    }

    #[\Override]
    public function canProcess(AgentState $state): bool {
        $tokens = Tokenizer::tokenCount($state->messages()->toString());
        return $tokens > $this->maxTokens;
    }

    #[\Override]
    public function process(AgentState $state, ?callable $next = null): AgentState {
        $newState = $next ? $next($state) : $state;

        [$keep, $overflow] = (new SplitMessages)->split(
            messages: $newState->messages(),
            tokenLimit: $this->maxTokens,
        );

        $this->events->dispatch(new MessagesMovedToBuffer([
            'overflow' => $overflow->toArray(),
            'keep' => $keep->toArray(),
        ]));

        $newMessageStore = $newState->store()
            ->section($this->bufferSection)
            ->appendMessages($overflow)
            ->section('messages')
            ->setMessages($keep);

        return $newState->withMessageStore($newMessageStore);
    }
}
