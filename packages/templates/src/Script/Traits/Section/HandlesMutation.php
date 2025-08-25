<?php declare(strict_types=1);

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Messages\Message;
use Cognesy\Messages\Messages;
use Cognesy\Template\Script\Section;

trait HandlesMutation
{
    public function clear() : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: Messages::empty(),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function withName(string $newName) : static {
        return new static(
            name: $newName,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages,
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function withMessages(Messages $messages) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $messages,
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function prependMessage(array|Message $message) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->prependMessage(Message::fromAny($message)),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function prependMessageIf(array|Message $message, callable $condition) : static {
        if ($condition($this)) {
            return $this->prependMessage($message);
        }
        return $this;
    }

    public function prependMessageIfEmpty(array|Message $message) : static {
        if ($this->messages->isEmpty()) {
            return $this->prependMessage($message);
        }
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->prependMessages($messages),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function appendMessage(array|Message $message) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->appendMessage(Message::fromAny($message)),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function appendMessageIfEmpty(array|Message $message) : static {
        if ($this->messages->isEmpty()) {
            return $this->appendMessage($message);
        }
        return $this;
    }

    public function appendMessageIf(array|Message $message, callable $condition) : static {
        if ($condition($this)) {
            return $this->appendMessage($message);
        }
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->appendMessages($messages),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function mergeSection(Section $section) : static {
        return $this->appendMessages($section->messages());
    }

    public function copyFrom(Section $section, bool $withMetadata = true) : static {
        return new static(
            name: $this->name, // Keep current name
            description: $section->description,
            metadata: $withMetadata ? $section->metadata : $this->metadata,
            messages: $section->messages,
            header: $section->header,
            footer: $section->footer,
        );
    }

    public function appendContentFields(array $fields) : static {
        $lastMessage = $this->messages->last();
        $newContent = $lastMessage->content()->appendContentFields($fields);
        $newMessage = $lastMessage->withContent($newContent);
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->removeTail()->appendMessage($newMessage),
            header: $this->header,
            footer: $this->footer,
        );
    }

    public function appendContentField(string $key, mixed $value) : static {
        $lastMessage = $this->messages->last();
        $newContent = $lastMessage->content()->appendContentField($key, $value);
        $newMessage = $lastMessage->withContent($newContent);
        return new static(
            name: $this->name,
            description: $this->description,
            metadata: $this->metadata,
            messages: $this->messages->removeTail()->appendMessage($newMessage),
            header: $this->header,
            footer: $this->footer,
        );
    }
}