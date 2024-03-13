<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;

class PartialResponseGenerationFailed extends Event
{
    public function __construct(public array $errors)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->errors);
    }
}