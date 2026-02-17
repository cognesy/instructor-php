<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatInferenceRequested;
use Cognesy\Addons\Chat\Events\ChatInferenceResponseReceived;
use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Contracts\CanCreateInference;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

final readonly class LLMParticipant implements CanParticipateInChat
{
    private CanHandleEvents $events;
    private CanCompileMessages $compiler;

    public function __construct(
        private CanCreateInference $inference,
        private string $name = 'assistant',
        private ?string $systemPrompt = null,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->compiler = $compiler ?? new SelectedSections(['summary', 'buffer', 'messages']);
        $this->events = EventBusResolver::using($events);
    }

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function act(ChatState $state): ChatStep {
        $messages = $this->prepareMessages($state);
        $this->emitChatInferenceRequested($messages);

        $request = new InferenceRequest(
            messages: $messages,
            mode: OutputMode::Text,
        );
        $response = $this->inference->create($request)->response();
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
            finishReason: $response->finishReason(),
        );
    }

    protected function prepareMessages(ChatState $state): Messages {
        $compiledMessages = $this->compiler->compile($state);
        $newMessages = new Messages(...$compiledMessages->map(fn(Message $m) => $this->mapRole($m)));
        if (!$this->systemPrompt) {
            return $newMessages;
        }
        return $newMessages->prependMessages(new Message(
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
