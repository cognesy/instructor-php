<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Utils\Result\Result;

final class LLMParticipant implements CanParticipateInChat
{
    public function __construct(
        private readonly string $id = 'assistant',
        private readonly ?Inference $inference = null,
        private readonly ?string $model = null,
        private readonly array $options = [],
        private readonly array $sectionOrder = ['summary', 'buffer', 'main'],
        private readonly ?LLMProvider $llmProvider = null,
        private readonly ?string $llmPreset = null,
    ) {}

    public function id() : string { return $this->id; }

    public function act(ChatState $state) : ChatStep {
        $messages = $state->script()->select($this->sectionOrder)->toMessages();
        $inference = $this->inference ?? new Inference();
        $inference = match (true) {
            !is_null($this->llmProvider) => $inference->withLLMProvider($this->llmProvider),
            !is_null($this->llmPreset) => $inference->using($this->llmPreset),
            default => $inference,
        };

        $result = Result::try(fn() => $inference->with(
            messages: $messages->toArray(),
            model: (string) ($this->model ?? ''),
            options: $this->options,
            mode: OutputMode::Text,
        )->get());

        $assistant = $result
            ->map(fn($text) => Messages::fromString((string) $text, role: 'assistant'))
            ->valueOr(Messages::empty());

        return new ChatStep(
            participantId: $this->id,
            messages: $assistant,
        );
    }
}
