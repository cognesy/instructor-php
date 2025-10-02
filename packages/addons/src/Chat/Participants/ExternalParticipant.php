<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Closure;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Contracts\CanRespondWithMessages;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatResponseRequested;
use Cognesy\Addons\StepByStep\MessageCompilation\CanCompileMessages;
use Cognesy\Addons\StepByStep\MessageCompilation\Compilers\SelectedSections;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final class ExternalParticipant implements CanParticipateInChat
{
    private CanRespondWithMessages $provider;
    private CanCompileMessages $compiler;
    private CanHandleEvents $events;

    public function __construct(
        private readonly string $name = 'external',
        CanRespondWithMessages|callable|null $provider = null,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->provider = $this->makeProvider($provider);
        $this->compiler = $compiler ?? new SelectedSections(['summary', 'buffer', 'messages']);
        $this->events = $events ?? EventBusResolver::using($events);
    }

    #[\Override]
    public function name() : string { return $this->name; }

    #[\Override]
    public function act(ChatState $state) : ChatStep {
        $this->emitChatResponseRequested($this->provider, $state);
        $response = $this->provider->respond($state);
        $this->emitChatResponseReceived($response);

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $this->compiler->compile($state),
            outputMessages: $response,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
        );
    }

    // INTERNAL //////////////////////////////////////////////////////////

    private function makeProvider(callable|CanProvideMessage|null $provider) : CanRespondWithMessages {
        return match(true) {
            $provider instanceof CanRespondWithMessages => $provider,
            is_callable($provider) => new class($provider) implements CanRespondWithMessages {
                public function __construct(private readonly Closure $provider) {}
                #[\Override]
                public function respond(ChatState $state) : Messages {
                    return Messages::fromAny(($this->provider)($state));
                }
            },
            default => new class implements CanRespondWithMessages {
                #[\Override]
                public function respond(ChatState $state) : Messages {
                    return new Messages(new Message(role: 'user', content: ''));
                }
            },
        };
    }

    private function emitChatResponseRequested(CanRespondWithMessages $provider, ChatState $state) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'provider' => get_class($provider),
            'state' => $state->toArray(),
        ]));
    }

    private function emitChatResponseReceived(Messages $outputMessages) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'outputMessages' => $outputMessages->toArray(),
        ]));
    }
}
