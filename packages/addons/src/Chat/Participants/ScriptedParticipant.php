<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Compilers\AllSections;
use Cognesy\Addons\Chat\Contracts\CanCompileMessages;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatResponseRequested;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Data\Usage;

final class ScriptedParticipant implements CanParticipateInChat
{
    private CanCompileMessages $compiler;
    private CanHandleEvents $events;
    private int $index = 0;
    private int $count;

    public function __construct(
        private readonly string $name,
        private readonly array $messages,
        private readonly MessageRole $defaultRole = MessageRole::User,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->count = count($messages);
        $this->compiler = $compiler ?? new AllSections();
        $this->events = $events ?? EventBusResolver::using($events);
    }

    public function name() : string {
        return $this->name;
    }

    public function act(ChatState $state) : ChatStep {
        $compiledMessages = $this->compiler->compile($state);
        $currentIndex = $this->index; // Store current index before incrementing
        $this->emitChatResponseRequested($state, $currentIndex);
        
        $outputMessage = $this->next();
        $this->emitChatResponseReceived($outputMessage, $currentIndex);
        
        return new ChatStep(
            participantName: $this->name,
            inputMessages: $compiledMessages,
            outputMessage: $outputMessage,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: 'scripted',
        );
    }

    private function next() : Message {
        if ($this->count === 0) {
            return new Message(
                role: $this->defaultRole->value,
                content: '',
                name: $this->name,
            );
        }
        
        $message = $this->messages[$this->index] ?? '';
        $this->index = ($this->index + 1) % $this->count;
        return new Message(
            role: $this->defaultRole->value,
            content: $message,
            name: $this->name,
        );
    }

    // EVENTS ////////////////////////////////////////////////////////

    private function emitChatResponseRequested(ChatState $state, int $currentIndex) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'state' => $state->toArray(),
            'scriptIndex' => $currentIndex,
            'totalMessages' => $this->count,
        ]));
    }

    private function emitChatResponseReceived(Message $outputMessage, int $currentIndex) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'response' => $outputMessage->toArray(),
            'scriptIndex' => $currentIndex,
        ]));
    }
}