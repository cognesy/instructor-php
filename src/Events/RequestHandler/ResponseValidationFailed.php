<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class ResponseValidationFailed extends Event
{
    public function __construct(
        public string $errors
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode($this->errors));
    }
}
