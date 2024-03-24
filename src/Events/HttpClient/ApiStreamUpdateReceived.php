<?php

namespace Cognesy\Instructor\Events\HttpClient;

use Cognesy\Instructor\Events\Event;

class ApiStreamUpdateReceived extends Event
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