<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ResponseTransformationStarted extends Event
{
    public function __construct(
        public mixed $object
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode($this->object));
    }
}