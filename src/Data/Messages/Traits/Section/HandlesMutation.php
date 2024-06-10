<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;
use Cognesy\Instructor\Data\Messages\Section;

trait HandlesMutation
{
    public function prependMessage(array|Message $message) : static {
        $this->messages()->prependMessages($message);
        return $this;
    }

    public function appendMessage(array|Message $message) : static {
        $this->messages()->appendMessage($message);
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        $this->messages()->appendMessages($messages);
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        $this->messages()->prependMessages($messages);
        return $this;
    }

    public function mergeSection(Section $section) : static {
        $this->appendMessages($section->messages());
        return $this;
    }
}