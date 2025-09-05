<?php declare(strict_types=1);

namespace Cognesy\Addons\Chat\Processors\Messages;

use Cognesy\Addons\Chat\Contracts\CanProcessMessage;
use Cognesy\Addons\Chat\Data\ChatState;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

final class NormalizeRolesForActiveParticipant implements CanProcessMessage
{
    public function beforeSend(Messages $messages, ChatState $state): Messages {
        $active = (string) $state->variable('active_participant_id', 'assistant');
        $mapped = Messages::empty();
        foreach ($messages->each() as $m) {
            if ($m->role()->is(MessageRole::System)) {
                $mapped = $mapped->appendMessage($m);
                continue;
            }
            $participant = (string)($m->meta('participantId') ?? '');
            $newRole = $participant === $active ? MessageRole::Assistant : MessageRole::User;
            $mm = $m->withMeta('participantId', $participant ?: $m->name());
            if ($participant !== '') { $mm = $mm->withName($participant); }
            $mapped = $mapped->appendMessage($mm->withRole($newRole));
        }
        // Re-stamp assistant messages with active id to preserve author in prompt
        $restamped = Messages::empty();
        foreach ($mapped->each() as $m) {
            if ($m->role()->is(MessageRole::Assistant)) {
                $mm = $m->withMeta('participantId', $active)->withName($active);
                $restamped = $restamped->appendMessage($mm);
            } else {
                $restamped = $restamped->appendMessage($m);
            }
        }
        return $restamped;
    }

    public function beforeAppend(Messages $messages, ChatState $state): Messages {
        return $messages;
    }
}
