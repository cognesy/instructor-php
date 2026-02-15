<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization;

use Cognesy\Agents\Capability\Summarization\Events\MessagesMovedToBuffer;
use Cognesy\Agents\Capability\Summarization\Utils\SplitMessages;
use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Utils\Tokenizer;

/**
 * Hook that moves overflow messages to a buffer section when token limit is exceeded.
 */
final readonly class MoveMessagesToBufferHook implements HookInterface
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxTokens,
        private string $bufferSection,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function handle(HookContext $context): HookContext
    {
        $state = $context->state();
        // Check if token limit is exceeded
        $tokens = Tokenizer::tokenCount($state->messages()->toString());
        if ($tokens <= $this->maxTokens) {
            return $context;
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
            ->section(ContextSections::DEFAULT)
            ->setMessages($keep);

        return $context->withState($state->withMessageStore($newMessageStore));
    }
}
