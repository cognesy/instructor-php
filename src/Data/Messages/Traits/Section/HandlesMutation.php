<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesMutation
{
    public function add(array|Message $message) : static {
        $this->messages->add($message);
        return $this;
    }

    public function appendMessages(array|Messages $messages) : static {
        $this->messages->appendMessages($messages);
        return $this;
    }

    public function prependMessages(array|Messages $messages) : static {
        $this->messages->prependMessages($messages);
        return $this;
    }
}