<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors;

use Cognesy\Addons\Chat\Contracts\CanProcessChatStep;
use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Utils\Tokenizer;

final readonly class SummarizeBuffer implements CanProcessChatStep
{
    public function __construct(
        private int $maxBufferTokens,
        private int $maxSummaryTokens,
        private string $bufferVariable,
        private CanSummarizeMessages $summarizer,
    ) {}

    public function process(ChatStep $step, ChatState $state): ChatState {
        $buffer = implode('\n', $state->variable($this->bufferVariable, []));
        if (!$this->shouldProcess($buffer)) {
            return $state;
        }
        $summary = $this->summarizer->summarize($state->messages(), $this->maxSummaryTokens);
        return  $state->withVariable($this->bufferVariable, [$summary]);
    }

    private function shouldProcess(string $buffer): bool {
        $tokens = Tokenizer::tokenCount($buffer);
        return $tokens > $this->maxBufferTokens;
    }
}
