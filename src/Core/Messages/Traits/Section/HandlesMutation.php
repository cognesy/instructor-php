<?php

namespace Cognesy\Instructor\Core\Messages\Traits\Section;

use Cognesy\Instructor\Core\Messages\Message;
use Cognesy\Instructor\Core\Messages\Messages;

trait HandlesMutation
{
    public function add(array|Message $message) : void {
        $this->messages->add($message);
    }

    public function appendMessages(array|Messages $messages) : void {
        $this->messages->appendMessages($messages);
    }

    public function prependMessages(array|Messages $messages) : void {
        $this->messages->prependMessages($messages);
    }
}