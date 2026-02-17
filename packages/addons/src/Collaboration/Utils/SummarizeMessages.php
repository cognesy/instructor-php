<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Utils;

use Cognesy\Addons\Collaboration\Contracts\CanSummarizeMessages;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class SummarizeMessages implements CanSummarizeMessages
{
    private string $prompt = 'Summarize the content of following sequence of messages for further reference:';
    private string $model;

    public function __construct(
        private CanCreateInference $inference,
        string $prompt = '',
        string $model = '',
    ) {
        $this->prompt = $prompt ?: $this->prompt;
        $this->model = $model;
    }

    #[\Override]
    public function summarize(Messages $messages, int $tokenLimit): string {
        $request = new InferenceRequest(
            messages: $messages->prependMessages(new Message(content: $this->prompt)),
            model: $this->model,
            options: ['max_tokens' => $tokenLimit],
            mode: OutputMode::Text,
        );

        return $this->inference->create($request)->get();
    }
}
