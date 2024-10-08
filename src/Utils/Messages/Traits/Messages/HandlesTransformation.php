<?php
namespace Cognesy\Instructor\Utils\Messages\Traits\Messages;

use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

trait HandlesTransformation
{
    public function toMergedPerRole() : Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        if ($this->hasComposites()) {
            $messages = $this->mergeRolesComposites();
        } else {
            $messages = $this->mergeRolesFlat();
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

    // INTERNAL /////////////////////////////////////////////////////////////////////////

    private function mergeRolesFlat() : Messages {
        $role = $this->firstRole()->value;
        $messages = new Messages();
        $content = [];
        foreach ($this->messages as $message) {
            if ($role !== $message->role()->value || $message->isComposite()) {
                $messages->appendMessage(new Message(
                    role: $role,
                    content: implode("\n\n", array_filter($content)),
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
                content: implode("\n", array_filter($content)),
            ));
        }
        return $messages;
    }

    private function mergeRolesComposites() : Messages {
        $composites = $this->toAllComposites();

        $messages = new Messages();
        $role = $composites->firstRole()->value;
        $message = new Message($role, []);
        foreach ($composites->all() as $composite) {
            if ($role !== $composite->role) {
                $messages->appendMessage($message);
                $role = $composite->role()->value;
                $message = new Message($role, []);
            }
            $message->addContentPart($composite->content(), $role);
        }
        $messages->appendMessage($message);
        return $messages;
    }

    private function toAllComposites() : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            $messages->appendMessage(match(true) {
                $message->isComposite() => $message,
                default => $message->toCompositeMessage(),
            });
        }
        return $messages;
    }
}
