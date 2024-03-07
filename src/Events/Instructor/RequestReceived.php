<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Events\Event;

class RequestReceived extends Event
{
    public function __construct(
        public Request $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->format(json_encode($this->request));
    }
}