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
    private string $prompt = 'Summarize the following text';
    private string $model = '';
    private LLM $llm;

    public function __construct(string $prompt = '', LLM $llm = null, string $model = '') {
        $this->prompt = $prompt ?: $this->prompt;
        $this->llm = $llm ?? new LLM();
        $this->model = $model;
    }

    public function summarize(Messages $messages, int $tokenLimit): string {
        return (new Inference)->withLLM($this->llm)->create(
            messages: $messages->prependMessage(new Message($this->prompt))->toArray(),
            model: $this->model,
            options: ['maxTokens' => $tokenLimit],
            mode: Mode::Text,
        )->toText();
    }
}
