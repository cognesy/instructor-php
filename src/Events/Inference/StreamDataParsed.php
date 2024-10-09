<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;

class StreamDataParsed extends Event
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