<?php

namespace Cognesy\Instructor\Utils\Messages\Traits\Section;

use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;
use Cognesy\Instructor\Utils\Messages\Section;

trait HandlesMutation
{
    public function clear() : static {
        $this->messages = new Messages();
        return $this;
    }

    public function setMessages(Messages $messages) : static {
        $this->messages = $messages;
        return $this;
    }

    public function prependMessageIfEmpty(array|Message $message) : static {
        if ($this->messages->isEmpty()) {
            $this->prependMessage($message);
        }
        return $this;
    }

    public function prependMessageIf(array|Message $message, callable $condition) : static {
        if ($condition($this)) {
            $this->prependMessage($message);
        }
        return $this;
    }

    public function prependMessage(array|Message $message) : static {
        $this->messages->prependMessages($message);
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        $this->messages->prependMessages($messages);
        return $this;
    }

    public function appendMessage(array|Message $message) : static {
        $this->messages->appendMessage($message);
        return $this;
    }

    public function appendMessageIfEmpty(array|Message $message) : static {
        if ($this->messages->isEmpty()) {
            $this->appendMessage($message);
        }
        return $this;
    }

    public function appendMessageIf(array|Message $message, callable $condition) : static {
        if ($condition($this)) {
            $this->appendMessage($message);
        }
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        $this->messages->appendMessages($messages);
        return $this;
    }

    public function mergeSection(Section $section) : static {
        $this->appendMessages($section->messages());
        return $this;
    }

    public function copyFrom(Section $section) : static {
        $this->setMessages($section->messages());
        return $this;
    }
}