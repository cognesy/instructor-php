<?php
namespace Cognesy\Instructor\Data\Messages\Traits\Messages;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesTransformation
{
    /**
     * @return array<string, string|array>
     */
    public function toArray() : array {
        $result = [];
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result[] = $message->toArray();
        }
        return $result;
    }

    public function toString(string $separator = "\n") : string {
        $result = '';
        foreach ($this->messages as $message) {
            if ($message->isEmpty()) {
                continue;
            }
            $result .= $message->toString() . $separator;
        }
        return $result;
    }

    public function toRoleString(string $role, string $separator = "\n") : string {
        $result = '';
        foreach ($this->messages as $message) {
            if ($message->isEmpty() || $message->role() !== $role) {
                continue;
            }
            $result .= $message->toRoleString() . $separator;
        }
        return $result;
    }

    public function toAlternatingRoles() : Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        $role = $this->firstRole()->value;
        $messages = new Messages();
        $content = [];
        foreach ($this->messages as $message) {
            $content[] = $message->content();
            if ($role !== $message->role()->value) {
                $messages->appendMessage(new Message(
                    $role,
                    implode("\n", array_filter($content)),
                ));
                $role = $message->role()->value;
                $content = [];
            }
        }
        // append remaining content
        if (!empty($content)) {
            $messages->appendMessage(new Message(
                $role,
                implode("\n", array_filter($content)),
            ));
        }
        return $messages;
    }
}
