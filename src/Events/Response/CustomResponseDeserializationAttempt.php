<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Contracts\CanDeserializeSelf;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

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
        return Json::encode([
            'object' => $this->instance,
            'json' => $this->json
        ]);
    }
}