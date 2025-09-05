<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors\Messages;

use Cognesy\Addons\Chat\Contracts\CanProcessMessage;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Messages;

final class StampParticipantOnAppend implements CanProcessMessage
{
    public function beforeSend(Messages $messages, ChatState $state): Messages {
        return $messages;
    }

    public function beforeAppend(Messages $messages, ChatState $state): Messages {
        $active = (string) $state->variable('active_participant_id', 'assistant');
        $stamped = Messages::empty();
        foreach ($messages->each() as $m) {
            // Preserve original role; only stamp participant metadata
            $stamped = $stamped->appendMessage(
                $m->withMeta('participantId', $active)
            );
        }
        return $stamped;
    }
}
