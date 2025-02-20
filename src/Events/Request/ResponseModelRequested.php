<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

class ResponseModelRequested extends Event
{
    public function __construct(
        public mixed $requestedModel
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return Json::encode($this->dumpVar($this->requestedModel));
    }
}