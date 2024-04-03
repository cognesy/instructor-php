<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ToolCallResultReady extends Event
{
    public function __construct(
        public mixed $response
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->response);
    }
}