<?php

namespace Cognesy\Polyglot\LLM\Events;

class StreamDataParsed extends \Cognesy\Utils\Events\Event
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