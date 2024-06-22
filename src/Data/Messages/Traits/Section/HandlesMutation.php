<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Section;

trait HandlesMutation
{
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
}