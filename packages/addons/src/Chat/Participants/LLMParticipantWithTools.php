<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatToolUseCompleted;
use Cognesy\Addons\Chat\Events\ChatToolUseStarted;
use Cognesy\Addons\ToolUse\Data\ToolUseState;
use Cognesy\Addons\ToolUse\Data\ToolUseStep;
use Cognesy\Addons\ToolUse\ToolUse;
use Cognesy\Addons\ToolUse\ToolUseFactory;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

/**
 * LLM participant with tool-calling capabilities.
 * Integrates with the ToolUse system to provide AI responses with tool access.
 */
final readonly class LLMParticipantWithTools implements CanParticipateInChat
{
    private CanHandleEvents $events;
    private string $name;
    private string $systemPrompt;
    private ToolUse $toolUse;

    public function __construct(
        string $name = 'assistant-with-tools',
        ?string $systemPrompt = null,
        ?ToolUse $toolUse = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->name = $name;
        $this->systemPrompt = $systemPrompt ?? '';
        $this->events = $events ?? EventBusResolver::using($events);
        $this->toolUse = $toolUse ?? ToolUseFactory::default(events: $this->events);
    }

    public function name(): string {
        return $this->name;
    }

    public function act(ChatState $state): ChatStep {
        $messages = $this->prepareMessages($state);
        $toolUseState = (new ToolUseState)->withMessages($messages);

        $this->emitChatToolUseStarted($messages);

        $finalState = $this->toolUse->finalStep($toolUseState);
        $toolStep = $finalState->currentStep();

        $stepMessages = $toolStep?->outputMessages() ?? Messages::empty();
        $normalizedMessages = new Messages(...array_map(
            fn(Message $message): Message => $message->role()->is(MessageRole::Assistant)
                ? $message->withName($this->name)
                : $message,
            $stepMessages->all(),
        ));

        $outputMessages = $normalizedMessages->notEmpty()
            ? $normalizedMessages
            : new Messages(new Message(
                role: MessageRole::Assistant->value,
                content: '',
                name: $this->name,
            ));

        $this->emitChatToolUseCompleted($toolStep);

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $messages,
            outputMessages: $outputMessages,
            usage: $toolStep?->usage() ?? Usage::none(),
            finishReason: $toolStep?->finishReason() ?? InferenceFinishReason::Other,
            meta: [
                'hasToolCalls' => $toolStep?->hasToolCalls() ? true : false,
                'toolsUsed' => $toolStep?->toolCalls()->toString() ?? '',
                'toolErrors' => count($toolStep?->errors() ?? []),
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

    // EVENTS ////////////////////////////////////////////

    private function emitChatToolUseStarted(Messages $messages) : void {
        $this->events->dispatch(new ChatToolUseStarted([
            'participant' => $this->name,
            'messages' => $messages->toArray(),
            'tools' => $this->toolUse->tools()->toToolSchema()
        ]));
    }

    private function emitChatToolUseCompleted(?ToolUseStep $toolStep) : void {
        $this->events->dispatch(new ChatToolUseCompleted([
            'participant' => $this->name,
            'response' => $toolStep?->toString() ?? '',
            'errors' => $toolStep?->errorsAsString() ?? '',
        ]));
    }
}
