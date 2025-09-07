<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

/**
 * LLM participant with tool-calling capabilities.
 * Integrates with the ToolUse system to provide AI responses with tool access.
 */
final readonly class LLMWithToolsParticipant implements CanParticipateInChat
{
    public function __construct(
        private string $name = 'assistant-with-tools',
        private ToolUse $toolUse,
        private ?string $systemPrompt = null,
    ) {}

    public function name(): string {
        return $this->name;
    }

    public function act(ChatState $state): ChatStep {
        $messages = $this->prepareMessages($state);
        $toolUse = $this->toolUse->withMessages($messages);
        $toolStep = $toolUse->finalStep();
        $outputMessage = new Message(
            role: 'assistant',
            content: $toolStep->response(),
            name: $this->name,
        );

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $messages,
            outputMessage: $outputMessage,
            usage: $toolStep->usage(),
            finishReason: $toolStep->finishReason()->value,
            meta: [
                'toolCalls' => $toolStep->hasToolCalls(),
                'toolsUsed' => $toolStep->toolCalls()->toArray(),
                'toolErrors' => count($toolStep->errors()),
            ],
        );
    }

    private function prepareMessages(ChatState $state): Messages {
        $messages = $state->messages();
        if (!$this->systemPrompt) {
            return $messages;
        }
        return $messages->prependMessage(new Message(
            role: 'system',
            content: $this->systemPrompt,
        ));
    }
}