<?php

namespace Cognesy\Instructor\Events\LLMClient;

use Cognesy\Instructor\Events\Event;

class LLMStreamUpdateReceived extends Event
{
    public function __construct(
        public string $streamedData
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->streamedData;
    }
}