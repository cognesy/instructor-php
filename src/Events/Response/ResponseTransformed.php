<?php

namespace Cognesy\Instructor\Events\Response;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Json;

class ResponseTransformed extends Event
{
    public function __construct(
        public mixed $result
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->result);
    }
}
