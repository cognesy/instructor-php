<?php

namespace Cognesy\Addons\Chat\Utils;

use Cognesy\Addons\Chat\Contracts\CanSummarizeMessages;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

class SummarizeMessages implements CanSummarizeMessages
{
    private string $prompt = 'Summarize the content of following sequence of messages for further reference:';
    private string $model;
    private LLMProvider $llm;
    private int $tokenLimit;

    public function __construct(string $prompt = '', ?LLMProvider $llm = null, string $model = '', int $tokenLimit = 1024) {
        $this->prompt = $prompt ?: $this->prompt;
        $this->llm = $llm ?? LLMProvider::new();
        $this->model = $model;
        $this->tokenLimit = $tokenLimit;
    }

    public function summarize(Messages $messages, ?int $tokenLimit = null): string {
        return (new Inference)->withLLMProvider($this->llm)->with(
            messages: $messages->prependMessage(new Message(content: $this->prompt))->toArray(),
            model: $this->model,
            options: ['max_tokens' => $tokenLimit ?? $this->tokenLimit],
            mode: OutputMode::Text,
        )->get();
    }
}
