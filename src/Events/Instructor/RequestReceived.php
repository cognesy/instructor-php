<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Data\Request;
use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class RequestReceived extends Event
{
    public function __construct(
        public Request $request,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->request);
    }
}