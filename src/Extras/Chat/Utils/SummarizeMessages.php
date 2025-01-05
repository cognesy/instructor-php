<?php

namespace Cognesy\Instructor\Extras\Chat\Utils;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Features\LLM\LLM;
use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

class SummarizeMessages implements CanSummarizeMessages
{
    private string $prompt = 'Summarize the content of following sequence of messages for further reference:';
    private string $model;
    private LLM $llm;
    private int $tokenLimit;

    public function __construct(string $prompt = '', LLM $llm = null, string $model = '', int $tokenLimit = 1024) {
        $this->prompt = $prompt ?: $this->prompt;
        $this->llm = $llm ?? new LLM();
        $this->model = $model;
        $this->tokenLimit = $tokenLimit;
    }

    public function summarize(Messages $messages, int $tokenLimit = null): string {
        return (new Inference)->withLLM($this->llm)->create(
            messages: $messages->prependMessage(new Message(content: $this->prompt))->toArray(),
            model: $this->model,
            options: ['max_tokens' => $tokenLimit ?? $this->tokenLimit],
            mode: Mode::Text,
        )->toText();
    }
}
