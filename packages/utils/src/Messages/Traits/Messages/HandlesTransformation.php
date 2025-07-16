<?php declare(strict_types=1);

namespace Cognesy\Utils\Messages\Traits\Messages;

use Cognesy\Utils\Messages\Enums\MessageRole;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesTransformation
{
    public function toMergedPerRole() : Messages {
        if ($this->isEmpty()) {
            return $this;
        }

        $messages = new Messages();

        $role = $this->firstRole();
        $newMessage = new Message($role);
        foreach ($this->all() as $message) {
            if ($message->role()->isNot($role)) {
                $messages->appendMessage($newMessage);
                $role = $message->role();
                $newMessage = new Message($role);
            }
            $newMessage->addContentFrom($message);
        }
        $messages->appendMessage($newMessage);

        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function forRoles(array $roles) : Messages {
        $messages = new Messages();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if ($message->role()->oneOf(...$roleEnums)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function exceptRoles(array $roles) : Messages {
        $messages = new Messages();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function headWithRoles(array $roles) : Messages {
        $messages = new Messages();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                break;
            }
            $messages->appendMessage($message);
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function tailAfterRoles(array $roles) : Messages {
        $messages = new Messages();
        $inHead = true;
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if ($inHead && $message->role()->oneOf(...$roleEnums)) {
                continue;
            }
            if ($inHead && !$message->role()->oneOf(...$roleEnums)) {
                $inHead = false;
            }
            $messages->appendMessage($message);
        }
        return $messages;
    }

//    public function toRoleString() : string {
//        $text = '';
//        foreach ($this->messages as $message) {
//            $text .= $message->toRoleString() . "\n";
//        }
//        return $text;
//    }

    public function remapRoles(array $mapping) : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            $role = $message->role()->value;
            $messages->appendMessage($message->withRole($mapping[$role] ?? $role));
        }
        return $messages;
    }

    public function contentParts() : array {
        $parts = [];
        foreach ($this->messages as $message) {
            foreach($message->contentParts() as $part) {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    public function reversed() : Messages {
        $messages = new Messages();
        $messages->messages = array_reverse($this->messages);
        return $messages;
    }

    public function trimmed() : Messages {
        $messages = new Messages();
        foreach ($this->messages as $message) {
            if (!$message->isEmpty()) {
                $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    // INTERNAL /////////////////////////////////////////////////////////////////////////

//    private function mergeRolesFlat() : Messages {
//        $role = $this->firstRole()->value;
//        $messages = new Messages();
//        $content = [];
//        foreach ($this->messages as $message) {
//            if ($role !== $message->role()->value || $message->isComposite()) {
//                $messages->appendMessage(new Message(
//                    role: $role,
//                    content: implode("\n\n", array_filter($content)),
//                ));
//                $role = $message->role()->value;
//                $content = [];
//
//                if ($message->isComposite()) {
//                    $messages->appendMessage($message);
//                    continue;
//                }
//            }
//            $content[] = $message->content();
//        }
//        // append remaining content
//        if (!empty($content)) {
//            $messages->appendMessage(new Message(
//                role: $role,
//                content: implode("\n", array_filter($content)),
//            ));
//        }
//        return $messages;
//    }
}
