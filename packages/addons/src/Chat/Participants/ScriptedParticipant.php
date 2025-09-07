<?php

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Message;

class ScriptedParticipant implements CanParticipateInChat
{
    private int $index = 0;
    private int $count;

    public function __construct(
        private readonly string $name,
        private readonly array $messages,
    ) {
        $this->count = count($messages);
    }

    public function name() : string { return $this->name; }

    public function act(ChatState $state) : ChatStep {
        return new ChatStep(
            participantName: $this->name,
            inputMessages: $state->messages(),
            outputMessage: $this->next(),
            usage: null,
            inferenceResponse: null,
            finishReason: 'scripted',
        );
    }

    private function next() : Message {
        $message = $this->messages[$this->index] ?? '';
        $this->index = ($this->index + 1) % $this->count;
        return new Message(
            role: 'user',
            content: $message,
            name: $this->name,
        );
    }
}