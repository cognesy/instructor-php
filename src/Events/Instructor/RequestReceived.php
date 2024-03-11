<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Core\Data\Request;
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
        return json_encode($this->request);
    }
}