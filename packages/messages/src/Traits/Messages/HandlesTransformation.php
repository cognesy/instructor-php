<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Messages;

use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

trait HandlesTransformation
{
    public function toMergedPerRole(): Messages {
        if ($this->isEmpty()) {
            return $this;
        }
        $messages = Messages::empty();
        $role = $this->firstRole();
        $newMessage = new Message($role);
        foreach ($this->all() as $message) {
            if ($message->role()->isNot($role)) {
                $messages = $messages->appendMessage($newMessage);
                $role = $message->role();
                $newMessage = new Message($role);
            }
            $newMessage = $newMessage->addContentFrom($message);
        }
        $messages = $messages->appendMessage($newMessage);
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function forRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if ($message->role()->oneOf(...$roleEnums)) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function exceptRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function headWithRoles(array $roles): Messages {
        $messages = Messages::empty();
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if (!$message->role()->oneOf(...$roleEnums)) {
                break;
            }
            $messages = $messages->appendMessage($message);
        }
        return $messages;
    }

    /** @param string[]|MessageRole[] $roles */
    public function tailAfterRoles(array $roles): Messages {
        $messages = Messages::empty();
        $inHead = true;
        $roleEnums = MessageRole::normalizeArray($roles);
        foreach ($this->messages as $message) {
            if ($inHead && $message->role()->oneOf(...$roleEnums)) {
                continue;
            }
            if ($inHead && !$message->role()->oneOf(...$roleEnums)) {
                $inHead = false;
            }
            $messages = $messages->appendMessage($message);
        }
        return $messages;
    }

    /** @param array<string, string|MessageRole> $mapping */
    public function remapRoles(array $mapping): Messages {
        $messages = Messages::empty();
        foreach ($this->messages as $message) {
            $role = $message->role()->value;
            $messages = $messages->appendMessage($message->withRole($mapping[$role] ?? $role));
        }
        return $messages;
    }

    /** @return ContentPart[] */
    public function contentParts(): array {
        $parts = [];
        foreach ($this->messages as $message) {
            foreach ($message->contentParts() as $part) {
                $parts[] = $part;
            }
        }
        return $parts;
    }

    public function reversed(): Messages {
        return new Messages(...array_reverse($this->messages));
    }

    public function trimmed(): Messages {
        $messages = Messages::empty();
        foreach ($this->messages as $message) {
            if (!$message->isEmpty()) {
                $messages = $messages->appendMessage($message);
            }
        }
        return $messages;
    }
}