<?php

namespace Cognesy\Addons\Chat\Utils;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Polyglot\LLM\Enums\Mode;
use Cognesy\Polyglot\LLM\Inference;
use Cognesy\Polyglot\LLM\LLM;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

class SummarizeMessages implements CanSummarizeMessages
{
    private string $prompt = 'Summarize the content of following sequence of messages for further reference:';
    private string $model;
    private LLM $llm;
    private int $tokenLimit;

    public function __construct(string $prompt = '', ?LLM $llm = null, string $model = '', int $tokenLimit = 1024) {
        $this->prompt = $prompt ?: $this->prompt;
        $this->llm = $llm ?? new LLM();
        $this->model = $model;
        $this->tokenLimit = $tokenLimit;
    }

    public function summarize(Messages $messages, ?int $tokenLimit = null): string {
        return (new Inference)->withLLM($this->llm)->create(
            messages: $messages->prependMessage(new Message(content: $this->prompt))->toArray(),
            model: $this->model,
            options: ['max_tokens' => $tokenLimit ?? $this->tokenLimit],
            mode: Mode::Text,
        )->toText();
    }
}
