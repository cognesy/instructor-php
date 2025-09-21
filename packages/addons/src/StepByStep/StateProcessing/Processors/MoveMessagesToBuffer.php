<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\StateProcessing\Processors;

use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\MessagesMovedToBuffer;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Addons\StepByStep\StateProcessing\CanProcessAnyState;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Utils\Tokenizer;

final readonly class MoveMessagesToBuffer implements CanProcessAnyState
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxTokens,
        private string $bufferSection,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $events ?? EventBusResolver::using($events);
    }

    public function canProcess(object $state): bool {
        return $state instanceof ChatState
            && $this->shouldProcess($state->messages()->toString());
    }

    public function process(object $state, ?callable $next = null): object {
        [$keep, $overflow] = (new SplitMessages)->split($state->messages(), $this->maxTokens);
        $this->events->dispatch(new MessagesMovedToBuffer([
            'overflow' => $overflow->toArray(),
            'keep' => $keep->toArray(),
        ]));

        assert($state instanceof ChatState);

        $newMessageStore = $state->store()
            ->section($this->bufferSection)
            ->appendMessages($overflow)
            ->section(ChatState::DEFAULT_SECTION)
            ->setMessages($keep);
        $newState = $state->withMessageStore($newMessageStore);

//        $newState = $state
//            ->withMessages($keep)
//            ->section($this->bufferSection)
//            ->replaceMessages($overflow);

        return $next ? $next($newState) : $newState;
    }

    private function shouldProcess(string $text): bool {
        $tokens = Tokenizer::tokenCount($text);
        return $tokens > $this->maxTokens;
    }
}
