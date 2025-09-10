<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatState;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Events\MessagesMovedToBuffer;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Utils\Tokenizer;

final readonly class MoveMessagesToBuffer implements CanProcessChatState
{
    private CanHandleEvents $events;

    public function __construct(
        private int $maxTokens,
        private string $bufferSection,
        ?CanHandleEvents $events = null,
    ) {
        $this->events = $events ?? EventBusResolver::using($events);
    }

    public function process(ChatState $state, ?callable $next = null): ChatState {
        if (!$this->shouldProcess($state->messages()->toString())) {
            return $next ? $next($state) : $state;
        }

        [$keep, $overflow] = (new SplitMessages)->split($state->messages(), $this->maxTokens);
        $this->events->dispatch(new MessagesMovedToBuffer([
            'overflow' => $overflow->toArray(),
            'keep' => $keep->toArray(),
        ]));
        $newState = $state
            ->withMessages($keep)->applyTo($this->bufferSection)->replaceMessages($overflow);

        return $next ? $next($newState) : $newState;
    }

    private function shouldProcess(string $text): bool {
        $tokens = Tokenizer::tokenCount($text);
        return $tokens > $this->maxTokens;
    }
}
