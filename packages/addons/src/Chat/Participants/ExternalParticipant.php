<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Closure;
use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Contracts\CanProvideMessage;
use Cognesy\Messages\Message;

final class ExternalParticipant implements CanParticipateInChat
{
    private CanProvideMessage $provider;

    public function __construct(
        private readonly string $name = 'external',
        CanProvideMessage|callable|null $provider = null,
    ) {
        $this->provider = $this->makeProvider($provider);
    }

    public function name() : string { return $this->name; }

    public function act(ChatState $state) : ChatStep {
        return new ChatStep(
            participantName: $this->name,
            inputMessages: $state->messages(),
            outputMessage: $this->provider->toMessage(),
            usage: null,
            inferenceResponse: null,
            finishReason: 'external',
        );
    }

    private function makeProvider(callable|CanProvideMessage|null $provider) : CanProvideMessage {
        return match(true) {
            $provider instanceof CanProvideMessage => $provider,
            is_callable($provider) => new class($provider) implements CanProvideMessage {
                public function __construct(private readonly Closure $provider) {}
                public function toMessage() : Message {
                    return ($this->provider)();
                }
            },
            default => new class implements CanProvideMessage {
                public function toMessage() : Message {
                    return new Message(role: 'user', content: '');
                }
            },
        };
    }
}
