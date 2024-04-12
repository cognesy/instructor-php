<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseDeserialized extends Event
{
    public function __construct(
        public mixed $object
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return Json::encode($this->object);
    }
}