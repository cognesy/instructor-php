<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Participants;

use Cognesy\Addons\Chat\Contracts\CanParticipateInChat;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Addons\Chat\Data\ChatStep;
use Cognesy\Messages\Messages;
use Cognesy\Addons\ToolUse\ToolUse;

final class LLMWithToolsParticipant implements CanParticipateInChat
{
    /** @var null|callable(ChatState): ToolUse */
    private $factory;

    public function __construct(
        private readonly string $id = 'assistant-tools',
        private readonly ?ToolUse $toolUse = null,
        ?callable $toolUseFactory = null,
    ) {
        $this->factory = $toolUseFactory;
    }

    public function id() : string { return $this->id; }

    public function act(ChatState $state) : ChatStep {
        $messages = $state->script()->select(['summary', 'buffer', 'main'])->toMessages();
        $toolUse = $this->toolUse ?? (is_callable($this->factory) ? ($this->factory)($state) : null);
        if (!$toolUse) {
            return new ChatStep(participantId: $this->id, messages: Messages::empty());
        }
        $toolUse->withMessages($messages);
        $step = $toolUse->finalStep();
        return new ChatStep(
            participantId: $this->id,
            messages: $step->messages(),
            usage: $step->usage(),
        );
    }
}
