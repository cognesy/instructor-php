<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Summarization\Utils;

use Cognesy\Agents\Capability\Summarization\Contracts\CanSummarizeMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;

class SummarizeMessages implements CanSummarizeMessages
{
    private string $prompt = 'Summarize the content of following sequence of messages for further reference:';
    private string $model;
    private LLMProvider $llm;

    public function __construct(
        string $prompt = '',
        ?LLMProvider $llm = null,
        string $model = '',
    ) {
        $this->prompt = $prompt ?: $this->prompt;
        $this->llm = $llm ?? LLMProvider::new();
        $this->model = $model;
    }

    #[\Override]
    public function summarize(Messages $messages, int $tokenLimit): string {
        return (new Inference)->withLLMProvider($this->llm)->with(
            messages: $messages->prependMessages(new Message(content: $this->prompt))->toArray(),
            model: $this->model,
            options: ['max_tokens' => $tokenLimit],
            mode: OutputMode::Text,
        )->get();
    }
}
