<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Utils\SplitMessages;
use Cognesy\Utils\Tokenizer;

final readonly class MoveMessagesToBuffer implements CanProcessChatStep
{
    public function __construct(
        private int $maxTokens,
        private string $bufferVariable,
    ) {}

    public function process(ChatStep $step, ChatState $state): ChatState {
        if (! $this->shouldProcess($state->messages()->toString())) {
            return $state;
        }
        [$keep, $overflow] = (new SplitMessages)->split($state->messages(), $this->maxTokens);
        $newBuffer = $state->variable($this->bufferVariable, '') . $overflow->toString();
        return $state
            ->withMessages($keep)
            ->withVariable($this->bufferVariable, $newBuffer);
    }

    private function shouldProcess(string $text): bool {
        $tokens = Tokenizer::tokenCount($text);
        return $tokens > $this->maxTokens;
    }
}
