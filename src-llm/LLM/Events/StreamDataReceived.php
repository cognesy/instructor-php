<?php

namespace Cognesy\LLM\LLM\Events;

use Cognesy\Instructor\Events\Event;

class StreamDataReceived extends Event
{
    public function __construct(
        public string $content,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->content;
    }
}
