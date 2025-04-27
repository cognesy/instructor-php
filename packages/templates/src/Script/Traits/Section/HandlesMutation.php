<?php

namespace Cognesy\Template\Script\Traits\Section;

use Cognesy\Template\Script\Section;
use Cognesy\Utils\Messages\Message;
use Cognesy\Utils\Messages\Messages;

trait HandlesMutation
{
    public function clear() : static {
        $this->messages = new Messages();
        return $this;
    }

    public function withName(string $newName) : static {
        $this->name = $newName;
        return $this;
    }

    public function withMessages(Messages $messages) : static {
        $this->messages = $messages;
        return $this;
    }

    public function prependMessage(array|Message $message) : static {
        $this->messages->prependMessages($message);
        return $this;
    }

    public function prependMessageIf(array|Message $message, callable $condition) : static {
        if ($condition($this)) {
            $this->prependMessage($message);
        }
        return $this;
    }

    public function prependMessageIfEmpty(array|Message $message) : static {
        if ($this->messages->isEmpty()) {
            $this->prependMessage($message);
        }
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

    public function copyFrom(Section $section, bool $withMetadata = true) : static {
        //$this->withName($section->name());
        $this->withMessages($section->messages());
        $this->withHeader($section->header());
        $this->withFooter($section->footer());
        if ($withMetadata) {
            $this->withMetadata($section->metadata());
        }
        return $this;
    }
}