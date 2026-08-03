<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\Chat\Events\MessagesMovedToBuffer;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Addons\StepByStep\State\Contracts\HasMessageStore;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Utils\Tokenization\Contracts\CanCountTokens;
use Cognesy\Utils\Tokenizer;

/**
 * @implements CanProcessAnyState<object>
 */
final readonly class MoveMessagesToBuffer implements CanProcessAnyState
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxTokens,
        private string $bufferSection,
        ?CanHandleEvents $events = null,
        private ?CanCountTokens $tokenizer = null,
    ) {
        $this->events = $events ?? new EventDispatcher(name: 'addons.processor.move-messages-to-buffer');
    }

    #[\Override]
    public function canProcess(object $state): bool {
        return $state instanceof HasMessageStore
            && $this->shouldProcess($state->messages()->toString());
    }

    #[\Override]
    public function process(object $state, ?callable $next = null): object {
        $newState = $next ? $next($state) : $state;

        assert($newState instanceof HasMessageStore);

        [$keep, $overflow] = (new SplitMessages($this->tokenizer()))->split(
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

    private function shouldProcess(string $text): bool {
        $tokens = $this->tokenizer()->tokenCount($text);
        return $tokens > $this->maxTokens;
    }

    /**
     * Resolved on use rather than in the constructor: the default tokenizer loads
     * a multi-megabyte vocabulary, and a processor that is wired into a pipeline
     * may never see a state it can process. Tokenizer::default() memoizes, so
     * repeating the call is free.
     */
    private function tokenizer(): CanCountTokens {
        return $this->tokenizer ?? Tokenizer::default();
    }
}
