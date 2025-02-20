<?php

namespace Cognesy\LLM\LLM\Events;

class StreamDataReceived extends \Cognesy\Utils\Events\Event
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
