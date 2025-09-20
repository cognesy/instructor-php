<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatInferenceRequested;
use Cognesy\Addons\Chat\Events\ChatInferenceResponseReceived;
use Cognesy\Addons\Core\MessageCompilation\AllSections;
use Cognesy\Addons\Core\MessageCompilation\CanCompileMessages;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Inference;
use Cognesy\Polyglot\Inference\LLMProvider;

final readonly class LLMParticipant implements CanParticipateInChat
{
    private CanHandleEvents $events;
    private CanCompileMessages $compiler;

    public function __construct(
        private string $name = 'assistant',
        private ?string $systemPrompt = null,
        private ?Inference $inference = null,
        private ?LLMProvider $llmProvider = null,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->compiler = $compiler ?? new AllSections();
        $this->events = $events ?? EventBusResolver::using($events);
    }

    public function name(): string {
        return $this->name;
    }

    public function act(ChatState $state): ChatStep {
        $inference = $this->inference ?? new Inference;
        if ($this->llmProvider) {
            $inference = $inference->withLLMProvider($this->llmProvider);
        }

        $messages = $this->prepareMessages($state);
        $this->emitChatInferenceRequested($messages);

        $response = $inference->with(
            messages: $messages->toArray(),
            mode: OutputMode::Text,
        )->response();
        $this->emitChatInferenceResponseReceived($response);

        $outputMessages = new Messages(new Message(
            role: 'assistant',
            content: $response->content(),
            name: $this->name,
        ));

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $messages,
            outputMessages: $outputMessages,
            usage: $response->usage(),
            inferenceResponse: $response,
            finishReason: $response->finishReason() ?? InferenceFinishReason::Other,
        );
    }

    protected function prepareMessages(ChatState $state): Messages {
        $compiledMessages = $this->compiler->compile($state);
        $newMessages = new Messages(...$compiledMessages->map(fn(Message $m) => $this->mapRole($m)));
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
            $message->role()->is(MessageRole::Assistant)
                && ($message->name() !== $this->name) => $message->withRole(MessageRole::User),
            default => $message,
        };
    }

    // EVENTS ////////////////////////////////////////////////////////

    private function emitChatInferenceRequested(Messages $messages) : void {
        $this->events->dispatch(new ChatInferenceRequested([
            'participant' => $this->name,
            'messages' => $messages->toArray()
        ]));
    }

    private function emitChatInferenceResponseReceived(InferenceResponse $response) : void {
        $this->events->dispatch(new ChatInferenceResponseReceived([
            'participant' => $this->name,
            'response' => $response->toArray()
        ]));
    }
}
