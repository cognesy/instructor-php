<?php

namespace Cognesy\Instructor\Events\ResponseHandler;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\Event;

class CustomResponseDeserializationAttempt extends Event
{
    public function __construct(
        public CanDeserializeSelf $instance,
        public string             $json)
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