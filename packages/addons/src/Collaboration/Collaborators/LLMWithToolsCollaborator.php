<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Collaborators;

use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Events\CollaborationToolUseCompleted;
use Cognesy\Addons\Collaboration\Events\CollaborationToolUseStarted;
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
final readonly class LLMWithToolsCollaborator implements CanCollaborate
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

    #[\Override]
    public function name(): string {
        return $this->name;
    }

    #[\Override]
    public function act(CollaborationState $state): CollaborationStep {
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

        return new CollaborationStep(
            collaboratorName: $this->name,
            inputMessages: $messages,
            outputMessages: $outputMessages,
            usage: $toolStep?->usage() ?? Usage::none(),
            finishReason: $toolStep?->finishReason() ?? InferenceFinishReason::Other,
            metadata: [
                'hasToolCalls' => $toolStep?->hasToolCalls() ? true : false,
                'toolsUsed' => $toolStep?->toolCalls()->toString(),
                'toolErrors' => count($toolStep?->errors() ?? []),
            ],
        );
    }

    private function prepareMessages(CollaborationState $state): Messages {
        $messages = $state->messages();
        if (!$this->systemPrompt) {
            return $messages;
        }
        return $messages->prependMessages(new Message(
            role: 'system',
            content: $this->systemPrompt,
        ));
    }

    // EVENTS ////////////////////////////////////////////

    private function emitChatToolUseStarted(Messages $messages) : void {
        $this->events->dispatch(new CollaborationToolUseStarted([
            'participant' => $this->name,
            'messages' => $messages->toArray(),
            'tools' => $this->toolUse->tools()->toToolSchema()
        ]));
    }

    private function emitChatToolUseCompleted(?ToolUseStep $toolStep) : void {
        $this->events->dispatch(new CollaborationToolUseCompleted([
            'participant' => $this->name,
            'response' => $toolStep?->toString() ?? '',
            'errors' => $toolStep?->errorsAsString() ?? '',
        ]));
    }
}
