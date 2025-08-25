<?php declare(strict_types=1);

namespace Cognesy\Messages\Traits\Messages;

use Cognesy\Messages\Content;
use Cognesy\Messages\ContentPart;
use Cognesy\Messages\Enums\MessageRole;
use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;

trait HandlesMutation
{
    public function asSystem(string|array|Message|Content|ContentPart $message) : static {
        return $this->appendMessage(Message::fromAny($message, MessageRole::System));
    }

    public function asDeveloper(string|array|Message|Content|ContentPart $message) : static {
        return $this->appendMessage(Message::fromAny($message, MessageRole::Developer));
    }

    public function asUser(string|array|Message|Content|ContentPart $message) : static {
        return $this->appendMessage(Message::fromAny($message, MessageRole::User));
    }

    public function asAssistant(string|array|Message|Content|ContentPart $message) : static {
        return $this->appendMessage(Message::fromAny($message, MessageRole::Assistant));
    }

    public function withMessage(string|array|Message $message) : static {
        $newMessages = match (true) {
            is_string($message) => [Message::fromString($message)],
            is_array($message) => [Message::fromArray($message)],
            default => [$message],
        };
        return new static(...$newMessages);
    }

    public function withMessages(array|Messages $messages) : static {
        $newMessages = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        return new static(...$newMessages);
    }

    public function appendMessage(string|array|Message $message) : static {
        $newMessages = $this->messages;
        $newMessages[] = match (true) {
            is_string($message) => Message::fromString($message),
            is_array($message) => Message::fromArray($message),
            default => $message,
        };
        return $this->withMessages($newMessages);
    }

    public function appendMessages(array|Messages $messages) : static {
        if (Messages::becomesEmpty($messages)) {
            return $this;
        }
        $appended = match (true) {
            $messages instanceof Messages => $messages->messages,
            default => Messages::fromAnyArray($messages)->messages,
        };
        $newMessages = array_merge($this->messages, $appended);
        return $this->withMessages($newMessages);
    }

    public function prependMessages(array|Messages $messages) : static {
        $newMessages = match (true) {
            empty($messages) => $this->messages,
            $messages instanceof Messages => array_merge($messages->messages, $this->messages),
            default => array_merge(Messages::fromAnyArray($messages)->messages, $this->messages),
        };
        return $this->withMessages($newMessages);
    }

    public function prependMessage(Message $param) : static {
        return $this->prependMessages([$param]);
    }

    public function removeHead() : static {
        $newMessages = $this->messages;
        array_shift($newMessages);
        return $this->withMessages($newMessages);
    }

    public function removeTail() : static {
        $newMessages = $this->messages;
        array_pop($newMessages);
        return $this->withMessages($newMessages);
    }

    public function appendContentFields(array $fields) : static {
        $newMessages = $this->messages;
        $lastMessage = $newMessages[array_key_last($newMessages)] ?? Message::empty();
        $newContent = $lastMessage->content()->appendContentFields($fields);
        $messagesExceptLast = array_slice($newMessages, 0, -1);
        $messages = [...$messagesExceptLast, $lastMessage->withContent($newContent)];
        return new static(...$messages);
    }

    public function appendContentField(string $key, mixed $value) : static {
        $newMessages = $this->messages;
        $lastMessage = $newMessages[array_key_last($newMessages)] ?? Message::empty();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $messagesExceptLast = array_slice($newMessages, 0, -1);
        $messages = [...$messagesExceptLast, $lastMessage->withContent($newContent)];
        return new static(...$messages);
    }
}