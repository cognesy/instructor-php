<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\Summarization;

use Cognesy\Agents\AgentHooks\Contracts\Hook;
use Cognesy\Agents\AgentHooks\Enums\HookType;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Events\MessagesMovedToBuffer;
use Cognesy\Agents\AgentBuilder\Capabilities\Summarization\Utils\SplitMessages;
use Cognesy\Agents\Core\Data\AgentState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Utils\Tokenizer;

/**
 * Hook that moves overflow messages to a buffer section when token limit is exceeded.
 */
final readonly class MoveMessagesToBufferHook implements Hook
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
    public function appliesTo(): array
    {
        return [HookType::AfterStep];
    }

    #[\Override]
    public function process(AgentState $state, HookType $event): AgentState
    {
        // Check if token limit is exceeded
        $tokens = Tokenizer::tokenCount($state->messages()->toString());
        if ($tokens <= $this->maxTokens) {
            return $state;
        }

        [$keep, $overflow] = (new SplitMessages)->split(
            messages: $state->messages(),
            tokenLimit: $this->maxTokens,
        );

        $this->events->dispatch(new MessagesMovedToBuffer([
            'overflow' => $overflow->toArray(),
            'keep' => $keep->toArray(),
        ]));

        $newMessageStore = $state->store()
            ->section($this->bufferSection)
            ->appendMessages($overflow)
            ->section('messages')
            ->setMessages($keep);

        return $state->withMessageStore($newMessageStore);
    }
}
