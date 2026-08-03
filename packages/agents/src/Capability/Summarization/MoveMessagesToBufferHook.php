<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization;

use Cognesy\Agents\Capability\Summarization\Events\MessagesMovedToBuffer;
use Cognesy\Agents\Capability\Summarization\Utils\SplitMessages;
use Cognesy\Agents\Context\ContextSections;
use Cognesy\Agents\Hook\Contracts\HookInterface;
use Cognesy\Agents\Hook\Data\HookContext;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenizer;
use Override;

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
        private ?CanCountTokens $tokenizer = null,
    ) {
        $this->events = $events ?? new EventDispatcher(name: 'agents.hook.move-messages-to-buffer');
    }

    #[Override]
    public function handle(HookContext $context): HookContext {
        $state = $context->state();
        // Check if token limit is exceeded
        $tokenizer = $this->tokenizer();
        $tokens = $tokenizer->tokenCount($state->messages()->toString());
        if ($tokens <= $this->maxTokens) {
            return $context;
        }

        [$keep, $overflow] = (new SplitMessages($tokenizer))->split(
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

    /**
     * Resolved on use rather than in the constructor: the default tokenizer loads
     * a multi-megabyte vocabulary, and a registered hook only counts once its
     * trigger fires. Tokenizer::default() memoizes, so repeating the call is free.
     */
    private function tokenizer(): CanCountTokens {
        return $this->tokenizer ?? Tokenizer::default();
    }
}
