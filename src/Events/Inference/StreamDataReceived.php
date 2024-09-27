<?php

namespace Cognesy\Instructor\Events\Inference;

use Cognesy\Instructor\Events\Event;

class StreamDataReceived extends Event
{
    public function __construct(
        public string $raw,
        public string $parsed,
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->raw;
    }
}
