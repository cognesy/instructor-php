<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanDeserializeJson;
use Cognesy\Instructor\Events\Event;

class CustomResponseDeserializationAttempt extends Event
{
    public function __construct(
        public CanDeserializeJson $instance,
        public string $json)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode([
            'object' => $this->instance,
            'json' => $this->json
        ]);
    }
}