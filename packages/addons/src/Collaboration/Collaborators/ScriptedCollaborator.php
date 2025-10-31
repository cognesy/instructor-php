<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Collaborators;

use Cognesy\Addons\Collaboration\Contracts\CanCollaborate;
use Cognesy\Addons\Collaboration\Data\CollaborationState;
use Cognesy\Addons\Collaboration\Data\CollaborationStep;
use Cognesy\Addons\Collaboration\Events\CollaborationResponseRequested;
use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final class ScriptedCollaborator implements CanCollaborate
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
        $this->compiler = $compiler ?? new SelectedSections(['summary', 'buffer', 'messages']);
        $this->events = $events ?? EventBusResolver::using($events);
    }

    #[\Override]
    public function name() : string {
        return $this->name;
    }

    #[\Override]
    public function act(CollaborationState $state) : CollaborationStep {
        $compiledMessages = $this->compiler->compile($state);
        $currentIndex = $this->index; // Store current index before incrementing
        $this->emitChatResponseRequested($state, $currentIndex);
        
        $outputMessages = $this->next();
        $this->emitChatResponseReceived($outputMessages, $currentIndex);
        
        return new CollaborationStep(
            collaboratorName: $this->name,
            inputMessages: $compiledMessages,
            outputMessages: $outputMessages,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
        );
    }

    private function next() : Messages {
        if ($this->count === 0) {
            return new Messages(new Message(
                role: $this->defaultRole->value,
                content: '',
                name: $this->name,
            ));
        }
        
        $message = $this->messages[$this->index] ?? '';
        $this->index = ($this->index + 1) % $this->count;
        return new Messages(new Message(
            role: $this->defaultRole->value,
            content: $message,
            name: $this->name,
        ));
    }

    // EVENTS ////////////////////////////////////////////////////////

    private function emitChatResponseRequested(CollaborationState $state, int $currentIndex) : void {
        $this->events->dispatch(new CollaborationResponseRequested([
            'participant' => $this->name,
            'state' => $state->toArray(),
            'scriptIndex' => $currentIndex,
            'totalMessages' => $this->count,
        ]));
    }

    private function emitChatResponseReceived(Messages $outputMessages, int $currentIndex) : void {
        $this->events->dispatch(new CollaborationResponseRequested([
            'participant' => $this->name,
            'response' => $outputMessages->toArray(),
            'scriptIndex' => $currentIndex,
        ]));
    }
}
