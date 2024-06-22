<?php

namespace Cognesy\Instructor\Data\Messages\Traits\Section;

use Cognesy\Instructor\Data\Messages\Message;
use Cognesy\Instructor\Data\Messages\Messages;

trait HandlesHeaderFooter
{
    public function header() : Messages {
        return $this->header->toMergedPerRole();
    }

    public function footer() : Messages {
        return $this->footer->toMergedPerRole();
    }

    public function withHeader(string|array|Message $message) : static {
        $this->header->setMessage($message);
        return $this;
    }

    public function withFooter(string|array|Message $message) : static {
        $this->footer->setMessage($message);
        return $this;
    }
}