<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Messages;

final class HumanParticipant implements CanParticipateInChat
{
    /** @var null|callable(ChatState): (string|array|Messages) */
    private $provider;

    public function __construct(
        private readonly string $id = 'human',
        ?callable $messageProvider = null,
    ) {
        $this->provider = $messageProvider;
    }

    public function id() : string { return $this->id; }

    public function act(ChatState $state) : ChatStep {
        $messages = match (true) {
            is_callable($this->provider) => Messages::fromAny(($this->provider)($state)),
            default => Messages::empty(),
        };
        return new ChatStep(
            participantId: $this->id,
            messages: $messages,
        );
    }
}
