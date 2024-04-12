<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;

class ResponseDeserializationFailed extends Event
{
    public function __construct(
        public string $errors
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->errors;
    }
}