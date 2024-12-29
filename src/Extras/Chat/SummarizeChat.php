<?php

namespace Cognesy\Instructor\Extras\Chat;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Features\LLM\LLM;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

class SummarizeChat implements CanSummarizeMessages
{
    const SUMMARIZATION_PROMPT = 'Summarize the following text';
    private LLM $llm;

    public function __construct(LLM $llm = null) {
        $this->llm = $llm ?? new LLM();
    }

    public function summarize(Messages $messages, int $tokenLimit): string {
        return (new Inference)->withLLM($this->llm)->create(
            messages: $messages->prependMessage(new Message(self::SUMMARIZATION_PROMPT))->toArray(),
            options: ['maxTokens' => $tokenLimit],
            mode: Mode::Text,
        )->toText();
    }
}
