<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ToolCallResponseConvertedToObject extends Event
{
    public function __construct(
        public mixed $object
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->object);
    }
}