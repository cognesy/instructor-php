<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Closure;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Contracts\CanRespondWithMessage;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Addons\Chat\Events\ChatResponseRequested;
use Cognesy\Addons\Core\MessageCompilation\AllSections;
use Cognesy\Addons\Core\MessageCompilation\CanCompileMessages;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Events\EventBusResolver;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Message;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;

final class ExternalParticipant implements CanParticipateInChat
{
    private CanRespondWithMessage $provider;
    private CanCompileMessages $compiler;
    private CanHandleEvents $events;

    public function __construct(
        private readonly string $name = 'external',
        CanRespondWithMessage|callable|null $provider = null,
        ?CanCompileMessages $compiler = null,
        ?CanHandleEvents $events = null,
    ) {
        $this->provider = $this->makeProvider($provider);
        $this->compiler = $compiler ?? new AllSections();
        $this->events = $events ?? EventBusResolver::using($events);
    }

    public function name() : string { return $this->name; }

    public function act(ChatState $state) : ChatStep {
        $this->emitChatResponseRequested($this->provider, $state);
        $response = $this->provider->respond($state);
        $this->emitChatResponseReceived($response);

        return new ChatStep(
            participantName: $this->name,
            inputMessages: $this->compiler->compile($state),
            outputMessage: $response,
            usage: Usage::none(),
            inferenceResponse: null,
            finishReason: InferenceFinishReason::Other,
        );
    }

    private function makeProvider(callable|CanProvideMessage|null $provider) : CanRespondWithMessage {
        return match(true) {
            $provider instanceof CanRespondWithMessage => $provider,
            is_callable($provider) => new class($provider) implements CanRespondWithMessage {
                public function __construct(private readonly Closure $provider) {}
                public function respond(ChatState $state) : Message {
                    return ($this->provider)($state);
                }
            },
            default => new class implements CanRespondWithMessage {
                public function respond(ChatState $state) : Message {
                    return new Message(role: 'user', content: '');
                }
            },
        };
    }

    private function emitChatResponseRequested(CanRespondWithMessage $provider, ChatState $state) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'provider' => get_class($provider),
            'state' => $state->toArray(),
        ]));
    }

    private function emitChatResponseReceived(Message $outputMessage) : void {
        $this->events->dispatch(new ChatResponseRequested([
            'participant' => $this->name,
            'response' => $outputMessage->toArray(),
        ]));
    }
}
