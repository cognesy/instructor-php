<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class LLMParticipant implements CanParticipateInChat
{
    public function __construct(
        private string $name = 'assistant',
        private ?Inference $inference = null,
        private ?LLMProvider $llmProvider = null,
        private ?string $systemPrompt = null,
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function act(ChatState $state): ChatStep {
        $inference = $this->inference ?? new Inference;
        if ($this->llmProvider) {
            $inference = $inference->withLLMProvider($this->llmProvider);
        }

        $messages = $this->prepareMessages($state);
        $response = $inference->with(
            messages: $messages->toArray(),
            mode: OutputMode::Text,
        )->response();

        $outputMessage = new Message(
            role: 'assistant',
            content: $response->content(),
            name: $this->name,
        );

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $messages,
            outputMessage: $outputMessage,
            usage: $response->usage(),
            inferenceResponse: $response,
            finishReason: $response->finishReason()->value,
        );
    }

    protected function prepareMessages(ChatState $state): Messages {
        $newMessages = new Messages(...$state->messages()->map(fn(Message $m) => $this->mapRole($m)));
        if (!$this->systemPrompt) {
            return $newMessages;
        }
        return $newMessages->prependMessage(new Message(
            role: 'system',
            content: $this->systemPrompt,
        ));
    }

    protected function mapRole(Message $message): Message {
        return match (true) {
            $message->name() === $this->name => $message->withRole(MessageRole::Assistant),
            $message->role()->is(MessageRole::Assistant) && ($message->name() !== $this->name) => $message->withRole(MessageRole::User),
            default => $message,
        };
    }
}
