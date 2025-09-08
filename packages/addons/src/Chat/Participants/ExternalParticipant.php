<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Closure;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Contracts\CanRespondWithMessage;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Message;

final class ExternalParticipant implements CanParticipateInChat
{
    private CanRespondWithMessage $provider;

    public function __construct(
        private readonly string $name = 'external',
        CanRespondWithMessage|callable|null $provider = null,
    ) {
        $this->provider = $this->makeProvider($provider);
    }

    public function name() : string { return $this->name; }

    public function act(ChatState $state) : ChatStep {
        return new ChatStep(
            participantName: $this->name,
            inputMessages: $state->compiledMessages(),
            outputMessage: $this->provider->respond($state),
            usage: null,
            inferenceResponse: null,
            finishReason: 'external',
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
}
