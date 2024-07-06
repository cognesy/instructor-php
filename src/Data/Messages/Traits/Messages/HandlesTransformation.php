<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesTransformation
{
    public function toMergedPerRole() : Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        $role = $this->firstRole()->value;
        $messages = new Messages();
        $content = [];
        foreach ($this->messages as $message) {
            if ($role !== $message->role()->value || $message->isComposite()) {
                $messages->appendMessage(new Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)), // TODO: check if content is array, needs different strategy then
                ));
                $role = $message->role()->value;
                $content = [];

                if ($message->isComposite()) {
                    $messages->appendMessage($message);
                    continue;
                }
            }
            $content[] = $message->content();
        }
        // append remaining content
        if (!empty($content)) {
            $messages->appendMessage(new Message(
                role: $role,
                content: implode("\n", array_filter($content)), // TODO: see above
            ));
        }
        return $messages;
    }

    public function forRoles(array $roles) : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            if (in_array($message->role()->value, $roles)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    public function exceptRoles(array $roles) : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            if (!in_array($message->role()->value, $roles)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }
}
