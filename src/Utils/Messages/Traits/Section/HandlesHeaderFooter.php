<?php

namespace Cognesy\Instructor\Utils\Messages\Traits\Section;

use Cognesy\Instructor\Utils\Messages\Message;
use Cognesy\Instructor\Utils\Messages\Messages;

trait HandlesHeaderFooter
{
    public function header() : Messages {
        return $this->header;
    }

    public function footer() : Messages {
        return $this->footer;
    }

    public function withHeader(string|array|Message|Messages $message) : static {
        $this->header->withMessages(Messages::fromAny($message));
        return $this;
    }

    public function withFooter(string|array|Message|Messages $message) : static {
        $this->footer->withMessages(Messages::fromAny($message));
        return $this;
    }
}